<?php

use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\ProgressJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

it('shows the queues, the running jobs and the errors in the terminal', function () {
    FailingJob::dispatch();
    $this->work();
    SuccessfulJob::dispatch()->onQueue('emails');
    ProgressJob::dispatch();
    MonitoredJob::where('name', ProgressJob::class)->update(['status' => 'processing', 'reserved_at' => time() - 30, 'progress' => 40]);

    $this->artisan('meridian:top', ['--once' => true])
        ->expectsOutputToContain('Meridian')
        ->expectsOutputToContain('Queues')
        ->expectsOutputToContain('database:emails')
        ->expectsOutputToContain('Running jobs')
        ->expectsOutputToContain('████░░░░░░ 40%')
        ->expectsOutputToContain('RuntimeException: Boom from FailingJob')
        ->assertSuccessful();
});

it('shows empty sections', function () {
    $this->artisan('meridian:top', ['--once' => true])
        ->expectsOutputToContain('none')
        ->doesntExpectOutputToContain('Refreshing every')
        ->assertSuccessful();
});
