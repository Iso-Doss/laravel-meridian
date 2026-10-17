<?php

namespace IsoDoss\Meridian\Listeners;

use Illuminate\Notifications\AnonymousNotifiable;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Notifications\LongWaitDetected as LongWaitNotification;

class SendLongWaitNotification
{
    public function handle(LongWaitDetected $event): void
    {
        $routes = Meridian::notificationRoutes();
        $notifiable = new AnonymousNotifiable;

        foreach (['mail' => 'mail', 'slack' => 'slack', 'sms' => 'vonage'] as $key => $channel) {
            if ($routes[$key]) {
                $notifiable->route($channel, $routes[$key]);
            }
        }

        if ($notifiable->routes === []) {
            return;
        }

        $notifiable->notify(new LongWaitNotification($event->connection, $event->queue, $event->seconds));
    }
}
