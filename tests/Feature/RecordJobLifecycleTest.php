<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Support\PendingJobsImporter;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\RetriedJob;
use IsoDoss\Meridian\Tests\Fixtures\SilencedJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use IsoDoss\Meridian\Tests\Fixtures\TaggedJob;
use IsoDoss\Meridian\Tests\Fixtures\User;

it('records a pending job when it is pushed', function () {
    $user = User::create(['email' => 'jane@example.com']);

    SuccessfulJob::dispatch($user)->onQueue('emails');

    $job = MonitoredJob::sole();

    expect($job)
        ->status->toBe(MonitoredJob::PENDING)
        ->queue->toBe('emails')
        ->connection->toBe('database')
        ->name->toBe(SuccessfulJob::class)
        ->tags->toBe([User::class.':'.$user->id])
        ->job_id->not->toBeNull();
});

it('uses the tags method of the job when present', function () {
    TaggedJob::dispatch();

    expect(MonitoredJob::sole()->tags)->toBe(['invoices', 'customer:42']);
});

it('records delayed jobs', function () {
    SuccessfulJob::dispatch()->delay(now()->addMinutes(10));

    $job = MonitoredJob::sole();

    expect($job->isDelayed())->toBeTrue()
        ->and($job->available_at->timestamp)->toBeGreaterThan(time() + 500);
});

it('marks the job as completed with its runtime', function () {
    SuccessfulJob::dispatch();

    $this->work();

    $job = MonitoredJob::sole();

    expect($job)
        ->status->toBe(MonitoredJob::COMPLETED)
        ->attempts->toBe(1)
        ->runtime->toBeInt()
        ->wait->toBeInt()
        ->worker->not->toBeNull()
        ->completed_at->not->toBeNull();
});

it('marks the job as failed with its exception', function () {
    FailingJob::dispatch();

    $this->work();

    $job = MonitoredJob::sole();

    expect($job->status)->toBe(MonitoredJob::FAILED)
        ->and($job->exception)->toContain('Boom from FailingJob')
        ->and($job->failed_at)->not->toBeNull();
});

it('puts a released job back to pending, then completes it', function () {
    RetriedJob::dispatch();

    $this->work();

    $job = MonitoredJob::sole();
    expect($job->status)->toBe(MonitoredJob::PENDING)
        ->and($job->exception)->toContain('First attempt fails');

    $this->work();

    expect($job->fresh())
        ->status->toBe(MonitoredJob::COMPLETED)
        ->attempts->toBe(2);
});

it('keeps silenced jobs apart from the completed ones', function () {
    SilencedJob::dispatch();
    $this->work();

    expect(MonitoredJob::sole())
        ->status->toBe(MonitoredJob::SILENCED)
        ->completed_at->not->toBeNull()
        ->and(MonitoredJob::status('completed')->count())->toBe(0)
        ->and(MonitoredJob::status('silenced')->count())->toBe(1);
});

it('silences jobs by class from the configuration', function () {
    config()->set('meridian.silenced', [TaggedJob::class]);

    TaggedJob::dispatch();
    $this->work();

    expect(MonitoredJob::sole()->status)->toBe(MonitoredJob::SILENCED);
});

it('silences jobs by tag from the configuration', function () {
    config()->set('meridian.silenced_tags', ['invoices']);

    TaggedJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(2);

    expect(MonitoredJob::where('status', 'silenced')->sole()->name)->toBe(TaggedJob::class)
        ->and(MonitoredJob::where('status', 'completed')->sole()->name)->toBe(SuccessfulJob::class);
});

it('records jobs pushed before meridian was installed', function () {
    SuccessfulJob::dispatch();
    MonitoredJob::query()->delete();

    $this->work();

    expect(MonitoredJob::sole())
        ->status->toBe(MonitoredJob::COMPLETED)
        ->name->toBe(SuccessfulJob::class);
});

it('marks a job retried with queue:retry as pending again', function () {
    FailingJob::dispatch();
    $this->work();

    $uuid = MonitoredJob::sole()->uuid;
    $this->artisan('queue:retry', ['id' => [$uuid]]);

    expect(MonitoredJob::sole())
        ->status->toBe(MonitoredJob::PENDING)
        ->retries->toBe(1);
});

it('ignores connections that are not watched', function () {
    config()->set('queue.connections.sync', ['driver' => 'sync']);

    SuccessfulJob::dispatch()->onConnection('sync');

    expect(MonitoredJob::count())->toBe(0);
});

it('stores the batch id', function () {
    $batch = Bus::batch([new SuccessfulJob, new SuccessfulJob])->dispatch();

    expect(MonitoredJob::where('batch_id', $batch->id)->count())->toBe(2);
});

it('lets the worker report its heartbeat', function () {
    SuccessfulJob::dispatch();

    $this->work();

    expect(Worker::sole())
        ->connection->toBe('database')
        ->queue->toBe('default')
        ->jobs_processed->toBe(1);
});

it('imports jobs pushed without event', function () {
    Queue::connection('database')->bulk([new TaggedJob, new SuccessfulJob], '', 'bulk');

    expect(MonitoredJob::count())->toBe(0);

    app(PendingJobsImporter::class)->import();

    expect(MonitoredJob::where('queue', 'bulk')->where('status', 'pending')->count())->toBe(2)
        ->and(MonitoredJob::where('name', TaggedJob::class)->first()->tags)->toBe(['invoices', 'customer:42']);

    // Idempotent.
    expect(app(PendingJobsImporter::class)->import())->toBe(0);
});
