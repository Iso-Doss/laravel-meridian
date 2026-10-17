<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Listeners\RecordJobLifecycle;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Tests\Fixtures\FlakyJob;

beforeEach(function () {
    FlakyJob::$fails = true;
    FlakyJob::dispatch();
    $this->work();
    $this->uuid = MonitoredJob::sole()->uuid;
});

function history(string $uuid): array
{
    return app(JobRetryRepository::class)->history($uuid);
}

it('records every retry with its outcome', function () {
    $this->post('/meridian/jobs/'.$this->uuid.'/retry');

    expect(history($this->uuid))->toHaveCount(1)
        ->and(history($this->uuid)[0]['status'])->toBe('pending');

    $this->work();

    expect(history($this->uuid)[0])
        ->status->toBe('failed')
        ->exception->toContain('Flaky failure')
        ->finished_at->not->toBeNull();

    FlakyJob::$fails = false;
    $this->post('/meridian/jobs/'.$this->uuid.'/retry');
    $this->work();

    expect(collect(history($this->uuid))->pluck('status')->all())->toBe(['failed', 'completed'])
        ->and(history($this->uuid)[1]['runtime'])->toBeInt()
        ->and(MonitoredJob::sole()->retries)->toBe(2);
});

it('records the retries made from the stored payload', function () {
    DB::table('failed_jobs')->delete();

    $this->post('/meridian/jobs/retry');

    expect(history($this->uuid))->toHaveCount(1);
});

it('shows the retries on the job page', function () {
    $this->post('/meridian/jobs/'.$this->uuid.'/retry');
    $this->work();

    $this->get('/meridian/jobs/'.$this->uuid)->assertInertia(fn (Assert $page) => $page
        ->has('job.retry_history', 1)
        ->where('job.retry_history.0.status', 'failed')
    );

    config()->set('meridian.ui', 'blade');
    $this->get('/meridian/jobs/'.$this->uuid)->assertSee('Retries')->assertSee('Flaky failure');
});

it('forgets the history with the job', function () {
    $this->post('/meridian/jobs/'.$this->uuid.'/retry');
    $this->work();

    $this->delete('/meridian/jobs/'.$this->uuid);

    expect(history($this->uuid))->toBe([]);
});

it('does not record anything before the migration', function () {
    Schema::drop('meridian_job_retries');
    app()->forgetInstance(JobRetryRepository::class);
    app()->singleton(JobRetryRepository::class);
    app()->forgetInstance(RecordJobLifecycle::class);

    $this->post('/meridian/jobs/'.$this->uuid.'/retry')->assertSessionHas('meridian.success');
    $this->work();

    expect(MonitoredJob::sole()->status)->toBe('failed')
        ->and(app(JobRetryRepository::class)->history($this->uuid))->toBe([]);
});
