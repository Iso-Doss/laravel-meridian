<?php

namespace IsoDoss\Meridian\Notifications;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the alerts to webhooks: Discord, Microsoft Teams, Google Chat,
 * Slack, or any URL receiving a JSON payload (signed when a secret is set).
 */
class WebhookChannel
{
    public const FORMATS = ['json', 'discord', 'teams', 'google_chat', 'slack'];

    public function send(mixed $notifiable, Alert $notification): void
    {
        $routes = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor(static::class, $notification) : null;

        foreach ((array) $routes as $route) {
            $url = is_array($route) ? (string) ($route['url'] ?? '') : (string) $route;
            $format = is_array($route) && isset($route['format']) ? (string) $route['format'] : static::detectFormat($url);

            if ($url === '') {
                continue;
            }

            try {
                $body = (string) json_encode(static::payload($notification, $format), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $secret = (string) config('meridian.notifications.webhook_secret', '');

                Http::timeout(10)
                    ->withHeaders($format === 'json' && $secret !== '' ? ['X-Meridian-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret)] : [])
                    ->withBody($body, 'application/json')
                    ->post($url)
                    ->throw();
            } catch (Throwable $e) {
                // An unreachable webhook must not prevent the other routes.
                report($e);
            }
        }
    }

    /**
     * The format of a webhook, from its URL.
     */
    public static function detectFormat(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return match (true) {
            Str::endsWith($host, ['discord.com', 'discordapp.com']) => 'discord',
            Str::endsWith($host, 'hooks.slack.com') => 'slack',
            Str::endsWith($host, 'chat.googleapis.com') => 'google_chat',
            Str::endsWith($host, ['webhook.office.com', 'logic.azure.com', 'powerplatform.com']) => 'teams',
            default => 'json',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Alert $notification, string $format): array
    {
        $title = config('app.name').': '.$notification->title();
        $summary = $notification->summary();
        $url = $notification->url();

        return match ($format) {
            'discord' => [
                'username' => 'Meridian',
                'embeds' => [[
                    'title' => $title,
                    'description' => $summary,
                    'url' => $url,
                    'color' => 0xE11D48,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ]],
            ],
            'teams' => [
                'type' => 'message',
                'attachments' => [[
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => [
                            ['type' => 'TextBlock', 'text' => $title, 'weight' => 'Bolder', 'size' => 'Medium', 'color' => 'Attention', 'wrap' => true],
                            ['type' => 'TextBlock', 'text' => $summary, 'wrap' => true],
                        ],
                        'actions' => [['type' => 'Action.OpenUrl', 'title' => 'Open Meridian', 'url' => $url]],
                    ],
                ]],
            ],
            'google_chat' => ['text' => "*{$title}*\n{$summary}\n<{$url}|Open Meridian>"],
            'slack' => ['text' => "*{$title}*\n{$summary} <{$url}|Open Meridian>"],
            default => [
                'event' => $notification->event(),
                'title' => $notification->title(),
                'summary' => $summary,
                'url' => $url,
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'occurred_at' => Carbon::now()->toIso8601String(),
                'data' => $notification->data(),
            ],
        };
    }
}
