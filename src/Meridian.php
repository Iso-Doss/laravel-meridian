<?php

namespace IsoDoss\Meridian;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Meridian
{
    /**
     * The callback that should be used to authenticate dashboard users.
     */
    public static ?Closure $authUsing = null;

    /**
     * The callback that determines who can act on the jobs and queues.
     */
    public static ?Closure $manageUsing = null;

    /**
     * The callback that determines who can change the data of a failed job before retrying it.
     */
    public static ?Closure $editUsing = null;

    /**
     * Routes of the long wait notifications, set in a service provider.
     *
     * @var array{mail?: string|array<int, string>, slack?: string, slack_channel?: string|null, sms?: string}
     */
    public static array $notificationRoutes = [];

    /**
     * Send the long wait notifications by e-mail.
     *
     * @param  string|array<int, string>  $email
     */
    public static function routeMailNotificationsTo(string|array $email): self
    {
        static::$notificationRoutes['mail'] = $email;

        return new self;
    }

    /**
     * Send the long wait notifications to a Slack webhook
     * (requires laravel/slack-notification-channel).
     */
    public static function routeSlackNotificationsTo(string $webhookUrl, ?string $channel = null): self
    {
        static::$notificationRoutes['slack'] = $webhookUrl;
        static::$notificationRoutes['slack_channel'] = $channel;

        return new self;
    }

    /**
     * Send the long wait notifications by SMS
     * (requires laravel/vonage-notification-channel).
     */
    public static function routeSmsNotificationsTo(string $number): self
    {
        static::$notificationRoutes['sms'] = $number;

        return new self;
    }

    /**
     * The notification routes: those set in code, else those of the configuration.
     *
     * @return array{mail: string|array<int, string>|null, slack: string|null, slack_channel: string|null, sms: string|null}
     */
    public static function notificationRoutes(): array
    {
        $config = (array) config('meridian.notifications', []);

        return [
            'mail' => static::$notificationRoutes['mail'] ?? ($config['mail'] ?? null) ?: null,
            'slack' => static::$notificationRoutes['slack'] ?? ($config['slack'] ?? null) ?: null,
            'slack_channel' => array_key_exists('slack_channel', static::$notificationRoutes)
                ? static::$notificationRoutes['slack_channel']
                : (($config['slack_channel'] ?? null) ?: null),
            'sms' => static::$notificationRoutes['sms'] ?? ($config['sms'] ?? null) ?: null,
        ];
    }

    /**
     * Register the dashboard authentication callback.
     */
    public static function auth(Closure $callback): self
    {
        static::$authUsing = $callback;

        return new self;
    }

    /**
     * Determine if the given request can access the dashboard.
     */
    public static function check(Request $request): bool
    {
        return (bool) (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Register the callback that determines who can act on the jobs and
     * queues (retry, delete, cancel, monitor tags). The others only read.
     */
    public static function manage(Closure $callback): self
    {
        static::$manageUsing = $callback;

        return new self;
    }

    /**
     * Determine if the given request can act on the jobs and queues: the
     * Meridian::manage() callback, else the "manageMeridian" gate when it is
     * defined, else everybody who can open the dashboard.
     */
    public static function canManage(Request $request): bool
    {
        if (static::$manageUsing) {
            return (bool) (static::$manageUsing)($request);
        }

        if (Gate::has('manageMeridian')) {
            return Gate::check('manageMeridian', [$request->user()]);
        }

        return true;
    }

    /**
     * Register the callback that determines who can change the data of a
     * failed job before retrying it.
     */
    public static function editJobs(Closure $callback): self
    {
        static::$editUsing = $callback;

        return new self;
    }

    /**
     * Determine if the given request can change the data of a failed job
     * before retrying it: only people who can act on the jobs, and then the
     * Meridian::editJobs() callback, else the "editMeridianJobs" gate when it
     * is defined, else nobody outside the local environment.
     */
    public static function canEditJobs(Request $request): bool
    {
        if (! static::canManage($request)) {
            return false;
        }

        if (static::$editUsing) {
            return (bool) (static::$editUsing)($request);
        }

        if (Gate::has('editMeridianJobs')) {
            return Gate::check('editMeridianJobs', [$request->user()]);
        }

        return app()->environment('local');
    }

    /**
     * The dashboard interface: "inertia" (default) or "blade".
     */
    public static function ui(): string
    {
        return config('meridian.ui') === 'blade' ? 'blade' : 'inertia';
    }

    /**
     * Get the database connection holding the Meridian tables.
     */
    public static function db(): ConnectionInterface
    {
        return DB::connection(config('meridian.database'));
    }

    /**
     * Get a query builder for one of the Meridian tables.
     */
    public static function table(string $table): Builder
    {
        return static::db()->table('meridian_'.$table);
    }

    /**
     * Determine if jobs pushed on the given queue connection are recorded.
     */
    public static function watches(?string $connection): bool
    {
        if ($connection === null) {
            return false;
        }

        $watched = config('meridian.connections');

        if (is_array($watched)) {
            return in_array($connection, $watched, true);
        }

        return config("queue.connections.{$connection}.driver") === 'database';
    }

    /**
     * Get the list of watched queue connections.
     *
     * @return array<int, string>
     */
    public static function watchedConnections(): array
    {
        $watched = config('meridian.connections');

        if (is_array($watched)) {
            return array_values($watched);
        }

        return collect(config('queue.connections', []))
            ->filter(fn ($config) => ($config['driver'] ?? null) === 'database')
            ->keys()
            ->all();
    }

    /**
     * Determine if a completed job should be hidden from the dashboard.
     *
     * @param  array<int, string>  $tags
     */
    public static function isSilenced(string $name, array $tags = []): bool
    {
        if (in_array($name, config('meridian.silenced', []), true)) {
            return true;
        }

        if (is_subclass_of($name, Contracts\Silenced::class)) {
            return true;
        }

        return array_intersect($tags, config('meridian.silenced_tags', [])) !== [];
    }

    /**
     * Get the name identifying the current process.
     */
    public static function processName(): string
    {
        return static::hostname().':'.getmypid();
    }

    /**
     * Get the hostname of the current machine.
     */
    public static function hostname(): string
    {
        return gethostname() ?: 'localhost';
    }
}
