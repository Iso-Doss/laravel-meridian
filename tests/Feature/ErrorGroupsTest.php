<?php

use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\ExceptionSummary;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\FlakyJob;

beforeEach(function () {
    FlakyJob::$fails = true;
});

function failSomeJobs(): string
{
    FailingJob::dispatch();
    FailingJob::dispatch()->onQueue('emails');
    FlakyJob::dispatch();
    test()->work(2);
    test()->work(1, ['--queue' => 'emails']);

    return MonitoredJob::where('name', FailingJob::class)->value('fingerprint');
}

it('groups the failed jobs by cause', function () {
    $fingerprint = failSomeJobs();

    expect($fingerprint)->toHaveLength(40)
        ->and(MonitoredJob::where('name', FlakyJob::class)->value('fingerprint'))->not->toBe($fingerprint);

    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page
        ->component('Errors/Index')
        ->where('ready', true)
        ->has('groups', 2)
        ->where('groups.0.fingerprint', $fingerprint)
        ->where('groups.0.count', 2)
        ->where('groups.0.class', 'RuntimeException')
        ->where('groups.0.message', 'Boom from FailingJob')
        ->where('groups.0.jobs', [FailingJob::class])
        ->where('groups.0.queues', ['database:default', 'database:emails'])
        ->where('groups.0.url', route('meridian.jobs.index', ['status' => 'failed', 'error' => $fingerprint]))
        ->where('groups.0.retry_url', route('meridian.jobs.retry-all'))
        ->where('groups.1.message', 'Flaky failure')
    );

    $this->get('/meridian/errors?queue=emails')->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)
        ->where('groups.0.count', 1)
    );

    $this->getJson('/meridian/api/errors')->assertOk()->assertJsonPath('0.count', 2);
});

it('lists and retries the jobs of an error group', function () {
    $fingerprint = failSomeJobs();

    $this->get('/meridian/jobs/failed?error='.$fingerprint)->assertInertia(fn (Assert $page) => $page
        ->has('jobs.data', 2)
        ->where('filters.error', $fingerprint)
        ->where('error.class', 'RuntimeException')
        ->where('error.count', 2)
    );

    $this->post('/meridian/jobs/retry', ['error' => $fingerprint])
        ->assertSessionHas('meridian.success', '2 failed job(s) pushed back onto the queue.');

    expect(MonitoredJob::where('status', 'pending')->count())->toBe(2)
        ->and(MonitoredJob::where('name', FlakyJob::class)->value('status'))->toBe('failed');
});

it('links a failed job to the jobs failing with the same error', function () {
    failSomeJobs();
    $job = MonitoredJob::where('name', FailingJob::class)->first();

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.error_group.count', 2)
        ->where('job.error_group.url', route('meridian.jobs.index', ['status' => 'failed', 'error' => $job->fingerprint]))
    );

    config()->set('meridian.ui', 'blade');

    $this->get('/meridian/jobs/'.$job->uuid)->assertSee('2 failed jobs with the same error');
    $this->get('/meridian/errors')->assertOk()->assertSee('Boom from FailingJob')->assertSee('Flaky failure');
});

it('reads the class, the message and the location of an exception', function () {
    try {
        throw new RuntimeException('Outer: failed in step 2', 0, new LogicException('Inner cause'));
    } catch (Throwable $e) {
        $summary = ExceptionSummary::parse((string) $e);
    }

    expect($summary)->toMatchArray([
        'class' => 'RuntimeException',
        'message' => 'Outer: failed in step 2',
        'file' => __FILE__,
    ])
        ->and(ExceptionSummary::parse('Illuminate\Queue\MaxAttemptsExceededException: App\Jobs\Import has been attempted too many times.'))
        ->toMatchArray(['class' => 'Illuminate\Queue\MaxAttemptsExceededException', 'file' => null, 'line' => null])
        ->and(ExceptionSummary::parse(null))->toBeNull()
        ->and(ExceptionSummary::parse('  '))->toBeNull();

    // Same class and line, different messages: same group.
    expect(ExceptionSummary::fingerprint("RuntimeException: User 1 not found in /app/A.php:10\nStack trace:\n#0"))
        ->toBe(ExceptionSummary::fingerprint("RuntimeException: User 2 not found in /app/A.php:10\nStack trace:\n#0"))
        ->not->toBe(ExceptionSummary::fingerprint("RuntimeException: User 2 not found in /app/A.php:11\nStack trace:\n#0"));
});

it('groups the failed jobs recorded before the upgrade and works until the migration runs', function () {
    $migration = include __DIR__.'/../../database/migrations/add_fingerprint_and_progress_to_meridian_jobs_table.php.stub';
    $migration->down();
    Migrations::flush();

    FailingJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);

    expect(MonitoredJob::where('status', 'failed')->count())->toBe(2);

    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page->where('ready', false)->has('groups', 0));
    $this->get('/meridian/jobs/failed')->assertInertia(fn (Assert $page) => $page->has('jobs.data', 2));

    $migration->up();
    Migrations::flush();

    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page
        ->where('ready', true)
        ->where('groups.0.count', 2)
    );
});
