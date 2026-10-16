<?php

use Kakodjenou\Meridian\Supervisor\Balancer;
use Kakodjenou\Meridian\Supervisor\SupervisorOptions;

function options(array $config = []): SupervisorOptions
{
    return SupervisorOptions::fromArray('supervisor-1', array_merge([
        'queue' => ['high', 'low'],
        'balance' => 'auto',
        'minProcesses' => 1,
        'maxProcesses' => 10,
        'balanceMaxShift' => 10,
    ], $config));
}

it('keeps the minimum processes when queues are empty', function () {
    $result = (new Balancer)->balance(options(), [], ['high' => 3, 'low' => 3]);

    expect($result)->toBe(['high' => 1, 'low' => 1]);
});

it('allocates processes proportionally to the workload', function () {
    $result = (new Balancer)->balance(
        options(),
        ['high' => ['size' => 80, 'runtime' => 100], 'low' => ['size' => 20, 'runtime' => 100]],
        ['high' => 1, 'low' => 1],
    );

    expect($result)->toBe(['high' => 7, 'low' => 3])
        ->and(array_sum($result))->toBe(10);
});

it('takes the runtime into account with the time strategy', function () {
    $result = (new Balancer)->balance(
        options(),
        ['high' => ['size' => 10, 'runtime' => 1000], 'low' => ['size' => 10, 'runtime' => 10]],
        ['high' => 1, 'low' => 1],
    );

    expect($result['high'])->toBeGreaterThan($result['low']);
});

it('moves by balanceMaxShift at most', function () {
    $result = (new Balancer)->balance(
        options(['balanceMaxShift' => 1]),
        ['high' => ['size' => 100, 'runtime' => 1], 'low' => ['size' => 0, 'runtime' => 1]],
        ['high' => 1, 'low' => 1],
    );

    expect($result)->toBe(['high' => 2, 'low' => 1]);
});

it('splits processes evenly with simple balancing', function () {
    $result = (new Balancer)->balance(options(['balance' => 'simple', 'maxProcesses' => 4]), [], ['high' => 0, 'low' => 0]);

    expect($result)->toBe(['high' => 2, 'low' => 2]);
});

it('uses a single pool when balancing is disabled', function () {
    $options = options(['balance' => false, 'maxProcesses' => 5]);

    expect($options->pools())->toBe(['high,low'])
        ->and((new Balancer)->balance($options, [], ['high,low' => 0]))->toBe(['high,low' => 5]);
});
