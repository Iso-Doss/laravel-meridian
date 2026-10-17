<?php

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Support\Facades\Notification;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Notifications\LongWaitDetected as LongWaitNotification;

it('sends nothing when no route is configured', function () {
    Notification::fake();

    event(new LongWaitDetected('database', 'default', 120));

    Notification::assertNothingSent();
});

it('routes the notifications like Horizon', function () {
    Notification::fake();

    Meridian::routeMailNotificationsTo('ops@example.com')
        ->routeSlackNotificationsTo('https://hooks.slack.com/services/T/B/X', '#queues')
        ->routeSmsNotificationsTo('+22990000000');

    event(new LongWaitDetected('database', 'emails', 300));

    Notification::assertSentOnDemand(LongWaitNotification::class, function (LongWaitNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
        return $channels === ['mail', 'slack', 'vonage']
            && $notifiable->routes['mail'] === 'ops@example.com'
            && $notifiable->routes['slack'] === 'https://hooks.slack.com/services/T/B/X'
            && $notifiable->routes['vonage'] === '+22990000000'
            && $notification->queue === 'emails'
            && $notification->seconds === 300;
    });
});

it('reads the routes from the configuration', function () {
    Notification::fake();
    config()->set('meridian.notifications.mail', 'ops@example.com');
    config()->set('meridian.notifications.slack', 'https://hooks.slack.com/services/T/B/X');

    event(new LongWaitDetected('database', 'default', 90));

    Notification::assertSentOnDemand(LongWaitNotification::class, fn ($notification, array $channels) => $channels === ['mail', 'slack']);
});

it('builds the Slack and SMS messages', function () {
    Meridian::routeSlackNotificationsTo('https://hooks.slack.com/services/T/B/X', '#queues');
    $notification = new LongWaitNotification('database', 'emails', 300);

    $slack = $notification->toSlack(new AnonymousNotifiable);
    $sms = $notification->toVonage(new AnonymousNotifiable);

    expect($slack)->toBeInstanceOf(SlackMessage::class)
        ->and($slack->channel)->toBe('#queues')
        ->and($slack->level)->toBe('error')
        ->and($slack->attachments[0]->content)->toContain('"emails" queue')
        ->and($sms)->toBeInstanceOf(VonageMessage::class)
        ->and($sms->content)->toContain('wait time of 300 seconds');
});
