<?php

namespace IsoDoss\Meridian;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Meridian
{
    /**
     * The callback that should be used to authenticate dashboard users.
     */
    public static ?Closure $authUsing = null;

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
