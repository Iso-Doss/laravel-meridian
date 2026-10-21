<?php

use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Tests\Fixtures\ProgressJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

beforeEach(function () {
    ProgressJob::$seen = [];
});

it('records the progress reported by a running job', function () {
    ProgressJob::dispatch();
    $this->work();

    expect(ProgressJob::$seen)->toBe([[25, 'Importing'], [75, '3 / 4 rows']])
        ->and(MonitoredJob::sole()->status)->toBe('completed');
});

it('resets the progress when the job runs again', function () {
    SuccessfulJob::dispatch();
    MonitoredJob::query()->update(['progress' => 90, 'progress_message' => 'Previous attempt']);

    $this->work();

    expect(MonitoredJob::sole()->only('progress', 'progress_message'))->toBe(['progress' => null, 'progress_message' => null]);
});

it('shows the progress and the remaining time of the running jobs', function () {
    $this->freezeSecond();
    ProgressJob::dispatch();
    $job = MonitoredJob::sole();
    $job->update(['status' => 'processing', 'reserved_at' => now()->subSeconds(30), 'progress' => 25, 'progress_message' => '250 / 1000']);

    expect(Present::remaining($job->fresh()))->toBe(90);

    $this->get('/meridian/jobs/processing')->assertInertia(fn (Assert $page) => $page
        ->where('jobs.data.0.progress', 25)
        ->where('jobs.data.0.progress_message', '250 / 1000')
        ->where('jobs.data.0.remaining', 90)
    );

    $this->get('/meridian/jobs/'.$job->uuid)->assertInertia(fn (Assert $page) => $page->where('job.progress', 25));

    config()->set('meridian.ui', 'blade');

    $this->get('/meridian/jobs/processing')->assertSee('25%')->assertSee('about 1 min 30 s left');
    $this->get('/meridian/jobs/'.$job->uuid)->assertSee('250 / 1000');
});

it('hides the progress once the job is no longer running', function () {
    ProgressJob::dispatch();
    MonitoredJob::query()->update(['status' => 'completed', 'progress' => 100]);

    $this->get('/meridian/jobs')->assertInertia(fn (Assert $page) => $page
        ->where('jobs.data.0.progress', null)
        ->where('jobs.data.0.remaining', null)
    );
});

it('ignores the progress of a job run outside a worker', function () {
    (new ProgressJob)->progress(50);

    expect(MonitoredJob::count())->toBe(0);
});
