<?php

use Illuminate\Support\Facades\Event;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

it('prunes old completed and failed jobs', function () {
    SuccessfulJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(2);

    MonitoredJob::query()->limit(1)->update(['completed_at' => time() - 2 * 86400]);

    $this->artisan('meridian:prune')->assertSuccessful();

    expect(MonitoredJob::count())->toBe(1);
});

it('stores metric snapshots', function () {
    SuccessfulJob::dispatch();
    $this->work();
    MonitoredJob::query()->update(['completed_at' => intdiv(time(), 300) * 300 - 10]);

    $this->artisan('meridian:snapshot')->assertSuccessful();

    expect(Meridian::table('metrics')->where('type', 'job')->value('name'))->toBe(SuccessfulJob::class)
        ->and(Meridian::table('metrics')->where('type', 'queue')->value('processed'))->toBe(1);
});

it('detects long wait times', function () {
    Event::fake([LongWaitDetected::class]);
    config()->set('meridian.waits', ['database:default' => 60]);

    SuccessfulJob::dispatch();
    DB::table('jobs')->update(['available_at' => time() - 120]);

    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertDispatched(LongWaitDetected::class, fn ($e) => $e->queue === 'default' && $e->seconds >= 120);
});

it('sends commands to the running masters', function () {
    Supervisor::create([
        'name' => 'host-abcd:supervisor-1', 'master' => 'host-abcd', 'hostname' => Meridian::hostname(),
        'pid' => 1, 'status' => 'running', 'processes' => ['default' => 1], 'options' => [],
        'started_at' => now(), 'last_seen_at' => now(),
    ]);

    $this->artisan('meridian:pause')->assertSuccessful();

    expect(Supervisor::sole()->command)->toBe('pause');
});

it('shows the status', function () {
    $this->artisan('meridian:status')->assertFailed(); // inactive
});
