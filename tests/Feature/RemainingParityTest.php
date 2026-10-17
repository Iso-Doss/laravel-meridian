<?php

use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Events\MasterSupervisorOutOfMemory;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Supervisor\Balancer;
use IsoDoss\Meridian\Supervisor\MasterSupervisor;
use IsoDoss\Meridian\Supervisor\SupervisorOptions;
use IsoDoss\Meridian\Tests\Fixtures\SilencedJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

it('lists the silenced jobs in their own tab', function () {
    SilencedJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(2);

    $this->get('/meridian/jobs/silenced')->assertInertia(fn (Assert $page) => $page
        ->where('status', 'silenced')
        ->where('counts.silenced', 1)
        ->where('counts.completed', 1)
        ->has('jobs.data', 1)
        ->where('jobs.data.0.status', 'silenced')
    );

    config()->set('meridian.ui', 'blade');
    $this->get('/meridian/jobs/silenced')->assertOk()->assertSee('Silenced jobs');
});

it('counts the silenced jobs in the metrics and prunes them like the completed ones', function () {
    SilencedJob::dispatch();
    $this->work();

    expect((int) app(MetricsRepository::class)->breakdown('name')->sole()->processed)->toBe(1);

    MonitoredJob::query()->update(['completed_at' => time() - 2 * 86400]);
    $this->artisan('meridian:prune')->assertSuccessful();

    expect(MonitoredJob::count())->toBe(0);
});

it('balances with the logarithmic strategy', function () {
    $options = SupervisorOptions::fromArray('supervisor-1', [
        'queue' => ['big', 'small'], 'balance' => 'auto', 'autoScalingStrategy' => 'log',
        'minProcesses' => 1, 'maxProcesses' => 10, 'balanceMaxShift' => 10,
    ]);
    $workload = ['big' => ['size' => 10000, 'runtime' => 1], 'small' => ['size' => 100, 'runtime' => 1]];

    $log = (new Balancer)->balance($options, $workload, ['big' => 1, 'small' => 1]);
    $options->autoScalingStrategy = 'size';
    $size = (new Balancer)->balance($options, $workload, ['big' => 1, 'small' => 1]);

    // By size, the big queue takes nearly everything; by log, the small one keeps a fair share.
    expect($size['small'])->toBe(1)
        ->and($log['small'])->toBeGreaterThanOrEqual(4)
        ->and(array_sum($log))->toBe(10);
});

it('warns about queues waiting more than 60 seconds by default', function () {
    Event::fake([LongWaitDetected::class]);
    config()->set('meridian.waits', ['database:emails' => 0]);

    SuccessfulJob::dispatch();
    SuccessfulJob::dispatch()->onQueue('emails');
    DB::table('jobs')->update(['available_at' => time() - 90]);

    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertDispatched(LongWaitDetected::class, fn ($event) => $event->queue === 'default');
    Event::assertNotDispatched(LongWaitDetected::class, fn ($event) => $event->queue === 'emails');
});

it('detects when the master exceeds its memory limit', function () {
    $master = new MasterSupervisor([SupervisorOptions::fromArray('supervisor-1', [])]);

    config()->set('meridian.memory_limit', 1);
    expect($master->exceedsMemoryLimit())->toBeTrue();

    config()->set('meridian.memory_limit', 0);
    expect($master->exceedsMemoryLimit())->toBeFalse();

    config()->set('meridian.memory_limit', 100000);
    expect($master->exceedsMemoryLimit())->toBeFalse()
        ->and(class_exists(MasterSupervisorOutOfMemory::class))->toBeTrue();
});
