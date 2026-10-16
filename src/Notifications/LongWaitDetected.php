<?php

namespace Kakodjenou\Meridian\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LongWaitDetected extends Notification
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(config('app.name').': long queue wait detected')
            ->greeting('Oh no! Something needs your attention.')
            ->line("The \"{$this->queue}\" queue on the \"{$this->connection}\" connection has a wait time of {$this->seconds} seconds.")
            ->action('Open Meridian', url(config('meridian.path', 'meridian')));
    }
}
