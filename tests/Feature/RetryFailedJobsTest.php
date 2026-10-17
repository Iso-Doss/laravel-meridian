<?php

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

beforeEach(function () {
    FailingJob::dispatch();
    FailingJob::dispatch()->onQueue('emails');
    SuccessfulJob::dispatch();
    $this->work(3);
    $this->work(1, ['--queue' => 'emails']);
});

it('retries every failed job', function () {
    expect(MonitoredJob::where('status', 'failed')->count())->toBe(2);

    $this->from('/meridian/jobs/failed')
        ->post('/meridian/jobs/retry')
        ->assertRedirect('/meridian/jobs/failed')
        ->assertSessionHas('meridian.success', '2 failed job(s) pushed back onto the queue.');

    expect(MonitoredJob::where('status', 'failed')->count())->toBe(0)
        ->and(MonitoredJob::where('status', 'pending')->count())->toBe(2)
        ->and(DB::table('jobs')->count())->toBe(2)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('only retries the failed jobs matching the filters', function () {
    $this->post('/meridian/jobs/retry', ['queue' => 'emails'])
        ->assertSessionHas('meridian.success', '1 failed job(s) pushed back onto the queue.');

    expect(MonitoredJob::where('status', 'failed')->sole()->queue)->toBe('default')
        ->and(DB::table('jobs')->value('queue'))->toBe('emails');
});

it('retries from the stored payload when the failed job was forgotten', function () {
    DB::table('failed_jobs')->delete();

    $this->post('/meridian/jobs/retry')->assertSessionHas('meridian.success');

    expect(MonitoredJob::where('status', 'pending')->count())->toBe(2)
        ->and(MonitoredJob::where('retries', 1)->count())->toBe(2)
        ->and(DB::table('jobs')->count())->toBe(2);
});

it('reports the jobs that cannot be retried', function () {
    DB::table('failed_jobs')->delete();
    MonitoredJob::where('queue', 'emails')->update(['payload' => null]);

    $this->post('/meridian/jobs/retry')
        ->assertSessionHas('meridian.success', '1 failed job(s) pushed back onto the queue.')
        ->assertSessionHas('meridian.error', '1 job(s) could not be retried: their payload was not stored.');

    expect(MonitoredJob::where('status', 'failed')->sole()->queue)->toBe('emails');
});

it('says when there is nothing to retry', function () {
    $this->post('/meridian/jobs/retry', ['queue' => 'unknown'])
        ->assertSessionHas('meridian.error', 'There is no failed job to retry.');
});

it('offers the retry actions in the Inertia interface', function () {
    $this->get('/meridian/jobs/failed')->assertInertia(fn (Assert $page) => $page
        ->where('retry_all_url', route('meridian.jobs.retry-all'))
        ->where('jobs.data.0.retry_url', fn ($url) => str_contains($url, '/retry'))
    );

    $this->get('/meridian/jobs/completed')->assertInertia(fn (Assert $page) => $page
        ->where('jobs.data.0.retry_url', null)
    );
});

it('offers the retry actions in the Blade interface', function () {
    config()->set('meridian.ui', 'blade');

    $this->get('/meridian/jobs/failed?queue=emails')
        ->assertOk()
        ->assertSee('action="'.route('meridian.jobs.retry-all').'"', false)
        ->assertSee('<input type="hidden" name="queue" value="emails">', false)
        ->assertSee('Retry 1 failed job(s)?');

    $this->get('/meridian/jobs/completed')->assertDontSee(route('meridian.jobs.retry-all'), false);
});
