<?php

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use IsoDoss\Meridian\Events\FailureRateExceeded;
use IsoDoss\Meridian\Events\LongRunningJobDetected;
use IsoDoss\Meridian\Events\NoWorkersRunning;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Notifications\FailureRateExceeded as FailureRateNotification;
use IsoDoss\Meridian\Notifications\LongRunningJobDetected as LongRunningNotification;
use IsoDoss\Meridian\Notifications\NoWorkersRunning as NoWorkersNotification;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

beforeEach(function () {
    config()->set('meridian.alerts.failure_rate_minimum', 2);
});

it('alerts when the failure rate of a queue is too high', function () {
    Event::fake([FailureRateExceeded::class]);
    config()->set('meridian.alerts.failure_rate', 50);

    FailingJob::dispatch();
    FailingJob::dispatch();
    SuccessfulJob::dispatch();
    $this->work(3);

    $this->artisan('meridian:snapshot')
        ->expectsOutputToContain('High failure rate on [database:default]: 2 of 3 jobs failed in 5 minutes.')
        ->assertSuccessful();

    Event::assertDispatched(FailureRateExceeded::class, fn (FailureRateExceeded $event) => $event->queue === 'default'
        && $event->failed === 2
        && $event->processed === 3
        && $event->rate() === 66.7);

    // Once per cooldown.
    $this->artisan('meridian:snapshot')->assertSuccessful();
    Event::assertDispatchedTimes(FailureRateExceeded::class, 1);
});

it('ignores the failure rate below the minimum number of jobs, or when disabled', function () {
    Event::fake([FailureRateExceeded::class]);

    FailingJob::dispatch();
    $this->work();

    config()->set('meridian.alerts.failure_rate', 25);
    $this->artisan('meridian:snapshot')->assertSuccessful();

    config()->set('meridian.alerts.failure_rate_minimum', 1);
    config()->set('meridian.alerts.failure_rate', 0);
    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertNotDispatched(FailureRateExceeded::class);
});

it('alerts on a number of failed jobs', function () {
    Event::fake([FailureRateExceeded::class]);
    config()->set('meridian.alerts.failure_rate', 0);
    config()->set('meridian.alerts.failures', 2);

    FailingJob::dispatch();
    FailingJob::dispatch();
    foreach (range(1, 8) as $i) {
        SuccessfulJob::dispatch();
    }
    $this->work(10);

    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertDispatched(FailureRateExceeded::class, fn (FailureRateExceeded $event) => $event->failed === 2 && $event->processed === 10);
});

it('alerts when jobs are waiting and no worker runs', function () {
    Event::fake([NoWorkersRunning::class]);

    SuccessfulJob::dispatch();
    SuccessfulJob::dispatch();

    $this->artisan('meridian:snapshot')
        ->expectsOutputToContain('No worker running while 2 job(s) are waiting.')
        ->assertSuccessful();

    Event::assertDispatched(NoWorkersRunning::class, fn (NoWorkersRunning $event) => $event->pending === 2);
});

it('does not alert about workers when one runs or when disabled', function () {
    Event::fake([NoWorkersRunning::class]);

    SuccessfulJob::dispatch();
    config()->set('meridian.alerts.no_workers', false);
    $this->artisan('meridian:snapshot')->assertSuccessful();

    config()->set('meridian.alerts.no_workers', true);
    SuccessfulJob::dispatch();
    $this->work(); // Reports a worker heartbeat.
    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertNotDispatched(NoWorkersRunning::class);
});

it('alerts on jobs running for too long', function () {
    Event::fake([LongRunningJobDetected::class]);

    SuccessfulJob::dispatch();
    $job = MonitoredJob::sole();
    $job->update(['status' => 'processing', 'reserved_at' => now()->subMinutes(20)]);

    $this->artisan('meridian:snapshot')->assertSuccessful();
    Event::assertNotDispatched(LongRunningJobDetected::class);

    config()->set('meridian.alerts.long_running', 600);
    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertDispatched(LongRunningJobDetected::class, fn (LongRunningJobDetected $event) => $event->uuid === $job->uuid
        && $event->name === SuccessfulJob::class
        && $event->seconds >= 1200);
});

it('notifies the alerts to the configured routes', function () {
    Notification::fake();
    Meridian::routeMailNotificationsTo('ops@example.com');

    event(new FailureRateExceeded('database', 'emails', 6, 10, 5));
    event(new NoWorkersRunning(42));
    event(new LongRunningJobDetected('9f0a8a9e-6b1a-4d4c-9b52-4c8d2a1f0e11', 'App\Jobs\Import', 'database', 'default', 1200));

    Notification::assertSentOnDemand(FailureRateNotification::class, function (FailureRateNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
        return $channels === ['mail']
            && $notifiable->routes['mail'] === 'ops@example.com'
            && $notification->summary() === '6 of the 10 jobs processed on the "emails" queue of the "database" connection failed in the last 5 minutes (60%).'
            && $notification->url() === route('meridian.errors.index');
    });

    Notification::assertSentOnDemand(NoWorkersNotification::class, fn (NoWorkersNotification $notification) => $notification->summary() === '42 job(s) are waiting but no worker is running.');

    Notification::assertSentOnDemand(LongRunningNotification::class, function (LongRunningNotification $notification) {
        $mail = $notification->toMail(new AnonymousNotifiable);

        return $mail->subject === config('app.name').': Long running job detected'
            && $mail->actionUrl === route('meridian.jobs.show', '9f0a8a9e-6b1a-4d4c-9b52-4c8d2a1f0e11');
    });
});
