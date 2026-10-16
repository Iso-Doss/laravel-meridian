<?php

use Kakodjenou\Meridian\Supervisor\SupervisorOptions;
use Kakodjenou\Meridian\Supervisor\WorkerCommand;
use Kakodjenou\Meridian\Tests\TestCase;

uses(TestCase::class);

it('builds the queue:work command line', function () {
    $command = WorkerCommand::for(SupervisorOptions::fromArray('supervisor-1', [
        'connection' => 'database', 'tries' => 3, 'timeout' => 30, 'force' => true,
    ]), 'high,low');

    expect($command)
        ->toContain('queue:work', 'database', '--name=supervisor-1', '--queue=high,low', '--tries=3', '--timeout=30', '--force');
});

it('merges defaults and environment configuration', function () {
    config()->set('meridian.defaults', ['supervisor-1' => ['queue' => ['a'], 'maxProcesses' => 2, 'tries' => 5]]);
    config()->set('meridian.environments.production', ['supervisor-1' => ['maxProcesses' => 8]]);

    [$options] = SupervisorOptions::forEnvironment('production');

    expect($options->queues)->toBe(['a'])
        ->and($options->maxProcesses)->toBe(8)
        ->and($options->tries)->toBe(5);
});
