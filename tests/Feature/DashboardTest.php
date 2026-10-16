<?php

use Kakodjenou\Meridian\Meridian;
use Kakodjenou\Meridian\Models\MonitoredJob;
use Kakodjenou\Meridian\Tests\Fixtures\FailingJob;
use Kakodjenou\Meridian\Tests\Fixtures\SuccessfulJob;
use Kakodjenou\Meridian\Tests\Fixtures\TaggedJob;

it('forbids access when the auth callback refuses', function () {
    Meridian::auth(fn () => false);

    $this->get('/meridian')->assertForbidden();
});

it('renders every page', function (string $uri) {
    SuccessfulJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);
    TaggedJob::dispatch();

    $this->get($uri)->assertOk();
})->with([
    '/meridian',
    '/meridian/jobs',
    '/meridian/jobs/pending',
    '/meridian/jobs/processing',
    '/meridian/jobs/completed',
    '/meridian/jobs/failed',
    '/meridian/batches',
    '/meridian/metrics',
    '/meridian/metrics/queues?period=60',
    '/meridian/metrics/jobs/'.urlencode(SuccessfulJob::class),
]);

it('shows the dashboard stats and workload', function () {
    SuccessfulJob::dispatch();
    SuccessfulJob::dispatch()->onQueue('emails');
    FailingJob::dispatch();
    $this->work();

    $this->getJson('/meridian/api/stats')
        ->assertOk()
        ->assertJson(['pendingJobs' => 2, 'recentJobs' => 1]);

    $this->getJson('/meridian/api/workload')
        ->assertOk()
        ->assertJsonFragment(['queue' => 'emails', 'pending' => 1])
        ->assertJsonFragment(['queue' => 'default', 'pending' => 1]);
});

it('shows a job with its payload and exception', function () {
    FailingJob::dispatch();
    $this->work();

    $job = MonitoredJob::sole();

    $this->get('/meridian/job/'.$job->uuid)
        ->assertOk()
        ->assertSee('Boom from FailingJob')
        ->assertSee('Retry');
});

it('searches jobs by tag', function () {
    TaggedJob::dispatch();
    SuccessfulJob::dispatch();

    $this->getJson('/meridian/api/jobs?search=customer:42')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', TaggedJob::class);
});

it('retries a failed job', function () {
    FailingJob::dispatch();
    $this->work();
    $job = MonitoredJob::sole();

    $this->post('/meridian/job/'.$job->uuid.'/retry')->assertRedirect();

    expect($job->fresh()->status)->toBe(MonitoredJob::PENDING)
        ->and(app('queue.failer')->find($job->uuid))->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('cancels a pending job', function () {
    SuccessfulJob::dispatch();
    $job = MonitoredJob::sole();

    $this->delete('/meridian/job/'.$job->uuid)->assertRedirect();

    expect(MonitoredJob::count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});
