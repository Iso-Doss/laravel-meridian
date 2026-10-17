<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use IsoDoss\Meridian\Tests\Fixtures\TaggedJob;

// These tests cover the Blade interface; the Inertia one is covered by InertiaTest.
beforeEach(fn () => config()->set('meridian.ui', 'blade'));

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

it('reads the workload without a reserved word of MySQL as alias', function () {
    SuccessfulJob::dispatch();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $workload = app(QueueRepository::class)->workload();

    expect($workload->firstWhere('queue', 'default'))->toMatchArray(['pending' => 1, 'delayed' => 0, 'reserved' => 0]);

    // « delayed » (and every column alias of MySQL's reserved words) breaks the query there.
    foreach ($queries as $sql) {
        expect($sql)->not->toMatch('/\bas\s+[`"]?(delayed|status|key|order|range|rank)[`"]?\b/i');
    }
});

it('shows a job with its payload and exception', function () {
    FailingJob::dispatch();
    $this->work();

    $job = MonitoredJob::sole();

    $this->get('/meridian/jobs/'.$job->uuid)
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

    $this->post('/meridian/jobs/'.$job->uuid.'/retry')->assertRedirect();

    expect($job->fresh()->status)->toBe(MonitoredJob::PENDING)
        ->and(app('queue.failer')->find($job->uuid))->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(1);
});

it('cancels a pending job', function () {
    SuccessfulJob::dispatch();
    $job = MonitoredJob::sole();

    $this->delete('/meridian/jobs/'.$job->uuid)->assertRedirect();

    expect(MonitoredJob::count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('returns a job through the api', function () {
    SuccessfulJob::dispatch();
    $job = MonitoredJob::sole();

    $this->getJson('/meridian/api/jobs/'.$job->uuid)
        ->assertOk()
        ->assertJsonPath('uuid', $job->uuid);

    $this->getJson('/meridian/api/jobs/pending')->assertOk()->assertJsonCount(1, 'data');
});

it('puts the CSP nonce of the application on the Blade interface', function () {
    Vite::useCspNonce('meridian-test-nonce');

    $html = $this->get('/meridian')->assertOk()->getContent();

    expect($html)->toContain('<style nonce="meridian-test-nonce">')
        ->and($html)->toContain('<script nonce="meridian-test-nonce">');
});
