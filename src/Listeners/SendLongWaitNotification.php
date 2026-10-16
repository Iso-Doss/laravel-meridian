<?php

namespace Kakodjenou\Meridian\Listeners;

use Illuminate\Support\Facades\Notification;
use Kakodjenou\Meridian\Events\LongWaitDetected;
use Kakodjenou\Meridian\Notifications\LongWaitDetected as LongWaitNotification;

class SendLongWaitNotification
{
    public function handle(LongWaitDetected $event): void
    {
        if (! $mail = config('meridian.notifications.mail')) {
            return;
        }

        Notification::route('mail', $mail)->notify(
            new LongWaitNotification($event->connection, $event->queue, $event->seconds)
        );
    }
}
