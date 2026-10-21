<?php

use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

beforeEach(function () {
    config()->set('meridian.prometheus.enabled', true);
});

it('is disabled by default', function () {
    config()->set('meridian.prometheus.enabled', false);

    $this->get('/meridian/prometheus')->assertNotFound();
});

it('requires the token when one is configured', function () {
    config()->set('meridian.prometheus.token', 'secret-token');

    $this->get('/meridian/prometheus')->assertUnauthorized();
    $this->get('/meridian/prometheus', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    $this->get('/meridian/prometheus', ['Authorization' => 'Bearer secret-token'])->assertOk();
});

it('uses the dashboard authorization without a token', function () {
    $this->get('/meridian/prometheus')->assertOk();

    Meridian::$authUsing = fn () => false;

    $this->get('/meridian/prometheus')->assertForbidden();
});

it('exposes the metrics in the Prometheus text format', function () {
    SuccessfulJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);
    SuccessfulJob::dispatch()->onQueue('emails');
    Meridian::table('schedule_tasks')->insert(['name' => 'reports "daily"', 'expression' => '0 6 * * *', 'first_seen_at' => time(), 'last_seen_at' => time()]);
    Meridian::table('schedule_runs')->insert(['task' => 'reports "daily"', 'status' => 'success', 'runtime' => 1250, 'started_at' => $started = time() - 100, 'finished_at' => $started + 1]);

    $response = $this->get('/meridian/prometheus')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

    expect($response->getContent())
        ->toContain("# HELP meridian_queue_pending_jobs Jobs waiting on the queue.\n# TYPE meridian_queue_pending_jobs gauge\n")
        ->toContain('meridian_queue_pending_jobs{connection="database",queue="emails"} 1')
        ->toContain('meridian_queue_jobs_processed_last_hour{connection="database",queue="default"} 2')
        ->toContain('meridian_queue_jobs_failed_last_hour{connection="database",queue="default"} 1')
        ->toContain('meridian_failed_jobs 1')
        ->toContain('meridian_error_groups 1')
        ->toContain('meridian_workers{status="idle"} 1')
        ->toContain('meridian_schedule_task_last_run_timestamp_seconds{task="reports \"daily\""} '.$started)
        ->toContain('meridian_schedule_task_last_runtime_seconds{task="reports \"daily\""} 1.25')
        ->toMatch('/^meridian_up \d/m');
});
