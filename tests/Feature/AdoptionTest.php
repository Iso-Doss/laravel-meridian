<?php

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Events\FailureRateExceeded;
use IsoDoss\Meridian\Events\NoWorkersRunning;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Notifications\LongWaitDetected;
use IsoDoss\Meridian\Notifications\WebhookChannel;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;
use IsoDoss\Meridian\Tests\Fixtures\TaggedJob;

it('sends the alerts to webhooks in their format', function () {
    Http::fake();
    config()->set('meridian.notifications.webhook_secret', 'webhook-secret');
    Meridian::routeWebhookNotificationsTo('https://discord.com/api/webhooks/1/abc')
        ->routeWebhookNotificationsTo('https://acme.webhook.office.com/webhookb2/xyz')
        ->routeWebhookNotificationsTo('https://chat.googleapis.com/v1/spaces/AAA/messages?key=k')
        ->routeWebhookNotificationsTo('https://ops.example.com/hooks/meridian');

    event(new FailureRateExceeded('database', 'emails', 6, 10, 5));

    Http::assertSentCount(4);

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'discord.com')
        && $request['embeds'][0]['title'] === config('app.name').': High failure rate detected'
        && $request['embeds'][0]['url'] === route('meridian.errors.index'));

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'office.com')
        && $request['attachments'][0]['contentType'] === 'application/vnd.microsoft.card.adaptive'
        && $request['attachments'][0]['content']['actions'][0]['url'] === route('meridian.errors.index'));

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'chat.googleapis.com')
        && str_contains($request['text'], '6 of the 10 jobs'));

    Http::assertSent(function (HttpRequest $request) {
        if (! str_contains($request->url(), 'ops.example.com')) {
            return false;
        }

        return $request['event'] === 'failure_rate_exceeded'
            && $request['data'] === ['connection' => 'database', 'queue' => 'emails', 'failed' => 6, 'processed' => 10, 'minutes' => 5]
            && $request->header('X-Meridian-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), 'webhook-secret');
    });
});

it('reads the webhooks from the configuration and forces their format', function () {
    Http::fake();
    config()->set('meridian.notifications.webhooks', 'https://hooks.slack.com/services/T/B/X, https://ops.example.com/a');

    expect(Meridian::notificationRoutes()['webhooks'])->toBe([
        ['url' => 'https://hooks.slack.com/services/T/B/X', 'format' => null],
        ['url' => 'https://ops.example.com/a', 'format' => null],
    ]);

    event(new NoWorkersRunning(3));

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'slack.com') && str_contains($request['text'], '3 job(s) are waiting'));

    Meridian::$notificationRoutes = [];
    Meridian::routeWebhookNotificationsTo('https://ops.example.com/discord-proxy', 'discord');

    expect(WebhookChannel::payload(new LongWaitDetected('database', 'default', 90), 'discord'))->toHaveKey('embeds');
    expect(WebhookChannel::detectFormat('https://ops.example.com/x'))->toBe('json');
});

it('keeps sending to the other webhooks when one fails', function () {
    Http::fake([
        'down.example.com/*' => Http::response('', 500),
        '*' => Http::response('ok'),
    ]);
    Meridian::routeWebhookNotificationsTo('https://down.example.com/hook')
        ->routeWebhookNotificationsTo('https://up.example.com/hook');

    event(new FailureRateExceeded('database', 'default', 3, 4, 5));

    Http::assertSentCount(2);
});

it('exports the jobs as CSV and JSON with the filters of the list', function () {
    FailingJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(2);
    TaggedJob::dispatch();

    $csv = $this->get('/meridian/jobs/export?status=failed')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
        ->streamedContent();

    $lines = array_map('str_getcsv', explode("\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))));

    expect($lines[0])->toBe(['uuid', 'job', 'connection', 'queue', 'status', 'attempts', 'retries', 'tags', 'batch_id', 'worker', 'queued_at', 'started_at', 'completed_at', 'failed_at', 'wait_seconds', 'runtime_ms', 'exception'])
        ->and($lines)->toHaveCount(2)
        ->and($lines[1][1])->toBe(FailingJob::class)
        ->and($lines[1][16])->toStartWith('RuntimeException: Boom from FailingJob');

    $json = json_decode($this->get('/meridian/jobs/export?format=json&search=customer:42')->assertOk()->streamedContent(), true);

    expect($json)->toHaveCount(1)
        ->and($json[0]['tags'])->toBe(['invoices', 'customer:42']);
});

it('neutralizes spreadsheet formulas in the CSV export', function () {
    SuccessfulJob::dispatch();
    MonitoredJob::query()->update(['queue' => '=HYPERLINK("http://evil")']);

    $csv = $this->get('/meridian/jobs/export')->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});

it('exports the error groups', function () {
    FailingJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);

    $json = json_decode($this->get('/meridian/errors/export?format=json')->assertOk()->streamedContent(), true);

    expect($json[0])->toMatchArray(['exception' => 'RuntimeException', 'message' => 'Boom from FailingJob', 'failed_jobs' => 2, 'jobs' => [FailingJob::class]]);

    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page->where('export_url', route('meridian.errors.export')));
});

it('shows the percentiles, the trends and the jobs per hour', function () {
    foreach (range(1, 20) as $i) {
        SuccessfulJob::dispatch();
    }
    $this->work(20);

    MonitoredJob::query()->orderBy('id')->get()->each(fn (MonitoredJob $job, int $index) => $job->update(['runtime' => ($index + 1) * 100]));

    // The previous period: half as many jobs, twice as fast.
    foreach (range(1, 10) as $i) {
        MonitoredJob::query()->create(array_merge(MonitoredJob::query()->first()->only(['connection', 'queue', 'name', 'tags']), [
            'uuid' => (string) Str::uuid(), 'status' => 'completed', 'attempts' => 1, 'runtime' => 50,
            'queued_at' => now()->subHours(30), 'available_at' => now()->subHours(30), 'completed_at' => now()->subHours(30),
        ]));
    }

    $this->get('/meridian/metrics?period=1440')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.processed', 20)
        ->where('rows.0.p95', 1900)
        ->where('rows.0.p99', 2000)
        ->where('rows.0.previous.processed', 10)
        ->where('rows.0.previous.runtime', 50)
        ->has('hourly', 168)
        ->where('hourly.167.processed', 20)
    );

    config()->set('meridian.ui', 'blade');

    $this->get('/meridian/metrics?period=1440')->assertOk()->assertSee('p95')->assertSee('▲ 100 %');
});
