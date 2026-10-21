<?php

namespace IsoDoss\Meridian\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use IsoDoss\Meridian\Meridian;

/**
 * A Meridian alert, sent by e-mail, Slack and / or SMS to the configured routes.
 */
abstract class Alert extends Notification
{
    /**
     * Short title, e.g. "Long wait detected".
     */
    abstract public function title(): string;

    /**
     * What happened, in one sentence.
     */
    abstract public function summary(): string;

    /**
     * The page of the dashboard to open.
     */
    public function url(): string
    {
        return route('meridian.dashboard');
    }

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
            ->subject(config('app.name').': '.$this->title())
            ->greeting('Oh no! Something needs your attention.')
            ->line($this->summary())
            ->action('Open Meridian', $this->url());
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->from('Meridian')
            ->to(Meridian::notificationRoutes()['slack_channel'])
            ->error()
            ->content('Oh no! Something needs your attention.')
            ->attachment(function ($attachment) {
                $attachment->title($this->title(), $this->url())
                    ->content($this->summary());
            });
    }

    public function toVonage(mixed $notifiable): VonageMessage
    {
        return (new VonageMessage)->content(config('app.name').': '.$this->summary());
    }
}
