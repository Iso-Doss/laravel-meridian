<?php

namespace IsoDoss\Meridian\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use IsoDoss\Meridian\Meridian;

class LongWaitDetected extends Notification
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}

    /**
     * The channels of the routes the notifiable defines (mail, slack, vonage).
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $routes = method_exists($notifiable, 'routeNotificationFor') ? $notifiable : null;

        return array_values(array_filter([
            $routes?->routeNotificationFor('mail', $this) ? 'mail' : null,
            $routes?->routeNotificationFor('slack', $this) && class_exists(SlackMessage::class) ? 'slack' : null,
            $routes?->routeNotificationFor('vonage', $this) && class_exists(VonageMessage::class) ? 'vonage' : null,
        ]));
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(config('app.name').': long queue wait detected')
            ->greeting('Oh no! Something needs your attention.')
            ->line($this->summary())
            ->action('Open Meridian', route('meridian.dashboard'));
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->from('Meridian')
            ->to(Meridian::notificationRoutes()['slack_channel'])
            ->error()
            ->content('Oh no! Something needs your attention.')
            ->attachment(function ($attachment) {
                $attachment->title('Long wait detected', route('meridian.dashboard'))
                    ->content($this->summary());
            });

        return $message;
    }

    public function toVonage(mixed $notifiable): VonageMessage
    {
        return (new VonageMessage)->content(config('app.name').': '.$this->summary());
    }

    protected function summary(): string
    {
        return "The \"{$this->queue}\" queue on the \"{$this->connection}\" connection has a wait time of {$this->seconds} seconds.";
    }
}
