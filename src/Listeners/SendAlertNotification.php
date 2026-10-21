<?php

namespace IsoDoss\Meridian\Listeners;

use Illuminate\Notifications\AnonymousNotifiable;
use IsoDoss\Meridian\Events;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Notifications;

/**
 * Sends the alerts to the configured routes (nothing when none is configured).
 */
class SendAlertNotification
{
    public function handle(object $event): void
    {
        $notification = match (true) {
            $event instanceof Events\LongWaitDetected => new Notifications\LongWaitDetected($event->connection, $event->queue, $event->seconds),
            $event instanceof Events\FailureRateExceeded => new Notifications\FailureRateExceeded($event->connection, $event->queue, $event->failed, $event->processed, $event->minutes),
            $event instanceof Events\NoWorkersRunning => new Notifications\NoWorkersRunning($event->pending),
            $event instanceof Events\LongRunningJobDetected => new Notifications\LongRunningJobDetected($event->uuid, $event->name, $event->connection, $event->queue, $event->seconds),
            $event instanceof Events\ScheduleTaskFailed => new Notifications\ScheduleTaskFailed($event->task, $event->error),
            $event instanceof Events\ScheduleTaskMissed => new Notifications\ScheduleTaskMissed($event->task, $event->dueAt),
            default => null,
        };

        $routes = Meridian::notificationRoutes();
        $notifiable = new AnonymousNotifiable;

        foreach (['mail' => 'mail', 'slack' => 'slack', 'sms' => 'vonage'] as $key => $channel) {
            if ($routes[$key]) {
                $notifiable->route($channel, $routes[$key]);
            }
        }

        if ($notification === null || $notifiable->routes === []) {
            return;
        }

        $notifiable->notify($notification);
    }
}
