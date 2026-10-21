<?php

use Illuminate\Support\Facades\DB;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\PrimaryKey;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

it('forgets a failed job', function () {
    FailingJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);
    $uuid = MonitoredJob::first()->uuid;

    $this->artisan('meridian:forget', ['uuid' => $uuid])->assertSuccessful();

    expect(MonitoredJob::count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(1);

    $this->artisan('meridian:forget', ['uuid' => $uuid])->assertFailed();
});

it('forgets every failed job', function () {
    FailingJob::dispatch();
    FailingJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(3);

    $this->artisan('meridian:forget', ['--all' => true])
        ->expectsOutputToContain('2 failed job(s) deleted.')
        ->assertSuccessful();

    expect(MonitoredJob::count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('clears the waiting jobs of a queue', function () {
    SuccessfulJob::dispatch()->onQueue('emails');
    SuccessfulJob::dispatch()->onQueue('emails');
    SuccessfulJob::dispatch();
    DB::table('jobs')->where('queue', 'emails')->limit(1)->update(['reserved_at' => time()]);

    $this->artisan('meridian:clear', ['--queue' => 'emails'])
        ->expectsOutputToContain('1 job(s) deleted from the [database:emails] queue.')
        ->assertSuccessful();

    expect(DB::table('jobs')->where('queue', 'emails')->count())->toBe(1)
        ->and(DB::table('jobs')->where('queue', 'default')->count())->toBe(1)
        ->and(MonitoredJob::where('queue', 'emails')->count())->toBe(0)
        ->and(MonitoredJob::where('queue', 'default')->count())->toBe(1);
});

it('refuses to clear a connection it does not watch', function () {
    config()->set('queue.connections.sync', ['driver' => 'sync']);

    $this->artisan('meridian:clear', ['connection' => 'sync'])->assertFailed();
});

it('clears the metrics', function () {
    Meridian::table('metrics')->insert(PrimaryKey::fill('meridian_metrics', ['type' => 'job', 'name' => 'A', 'bucket' => time(), 'period' => 300]));

    $this->artisan('meridian:clear-metrics')->expectsOutputToContain('1 metrics snapshot(s) deleted.')->assertSuccessful();

    expect(Meridian::table('metrics')->count())->toBe(0);
});
