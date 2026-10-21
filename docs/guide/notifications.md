# Notifications and alerts

When the oldest job of a queue waits longer than its threshold, Meridian fires an `IsoDoss\Meridian\Events\LongWaitDetected` event and notifies the configured routes, at most once every `notifications.cooldown` minutes per queue. Like with Horizon, set the routes in the `boot` method of `App\Providers\MeridianServiceProvider`:

```php
use IsoDoss\Meridian\Meridian;

public function boot(): void
{
    parent::boot();

    Meridian::routeMailNotificationsTo('ops@example.com');
    Meridian::routeSlackNotificationsTo('https://hooks.slack.com/services/…', '#queues');
    Meridian::routeSmsNotificationsTo('+15555550123');
}
```

or in your `.env`: `MERIDIAN_NOTIFY_MAIL`, `MERIDIAN_NOTIFY_SLACK_WEBHOOK` (and `MERIDIAN_NOTIFY_SLACK_CHANNEL`), `MERIDIAN_NOTIFY_SMS`. Slack requires `laravel/slack-notification-channel` and SMS `laravel/vonage-notification-channel`.

**Webhooks**: send the alerts to Discord, Microsoft Teams, Google Chat, Slack or any URL, without any extra package:

```php
Meridian::routeWebhookNotificationsTo('https://discord.com/api/webhooks/…');
Meridian::routeWebhookNotificationsTo('https://acme.webhook.office.com/webhookb2/…');
Meridian::routeWebhookNotificationsTo('https://ops.example.com/hooks/meridian'); // JSON
```

or `MERIDIAN_NOTIFY_WEBHOOKS` with comma separated URLs. Discord, Teams (incoming webhooks and Workflows), Google Chat and Slack are recognized from their URL and receive a message in their format; force it with the second argument (`json`, `discord`, `teams`, `google_chat` or `slack`). Any other URL receives JSON:

```json
{
    "event": "failure_rate_exceeded",
    "title": "High failure rate detected",
    "summary": "6 of the 10 jobs processed on the \"emails\" queue …",
    "url": "https://app.example.com/meridian/errors",
    "app": "Acme Store",
    "environment": "production",
    "occurred_at": "2026-10-21T18:05:00+00:00",
    "data": {"connection": "database", "queue": "emails", "failed": 6, "processed": 10, "minutes": 5}
}
```

Set `MERIDIAN_NOTIFY_WEBHOOK_SECRET` to sign it: the `X-Meridian-Signature` header holds `sha256=` followed by the HMAC SHA-256 of the body with your secret. A webhook that fails does not prevent the others.

The thresholds, in seconds, are set per `connection:queue` in the `waits` option. Like with Horizon, a queue not listed uses `'*'`, or 60 seconds when `'*'` is not set; `0` disables the notifications of a queue:

```php
'waits' => [
    'database:default' => 60,
    'database:reports' => 600,
    '*' => 120,
],
```

## Alerts

Besides long waits, `meridian:snapshot` (every five minutes) checks the health of your queues and notifies the same routes, at most once per `notifications.cooldown` minutes for the same problem:

| Alert | Event | When |
| --- | --- | --- |
| High failure rate | `FailureRateExceeded` | `failure_rate` % of the jobs of a queue failed during the last `window` minutes, once at least `failure_rate_minimum` jobs ran; or `failures` jobs failed |
| No worker | `NoWorkersRunning` | jobs are waiting but no worker has reported itself |
| Long running job | `LongRunningJobDetected` | a job has been running for more than `long_running` seconds |
| Scheduled task failed | `ScheduleTaskFailed` | a scheduled task threw an exception or exited with an error (`schedule_failures`), notified right away |
| Scheduled task missed | `ScheduleTaskMissed` | a scheduled task did not run when it was due (`schedule_missed`) |

```php
'alerts' => [
    'window' => 5,                 // minutes
    'failure_rate' => 25,          // %, 0 to disable
    'failure_rate_minimum' => 10,  // jobs
    'failures' => 0,               // failed jobs, 0 to disable
    'no_workers' => true,
    'long_running' => 0,           // seconds, 0 to disable
    'schedule_failures' => true,
    'schedule_missed' => true,
],
```

The events live in `IsoDoss\Meridian\Events`: listen to them to send the alerts anywhere else.
