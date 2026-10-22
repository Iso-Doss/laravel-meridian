<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tells whether the migrations adding a feature have run, so that Meridian
 * keeps working (without the feature) until they do.
 */
class Migrations
{
    /**
     * @var array<string, bool>
     */
    protected static array $checked = [];

    public static function has(string $table, ?string $column = null): bool
    {
        $key = $table.'.'.$column;

        if (isset(static::$checked[$key])) {
            return static::$checked[$key];
        }

        try {
            $schema = Schema::connection(config('meridian.database'));
            $exists = $column === null ? $schema->hasTable($table) : $schema->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }

        return static::$checked[$key] = $exists;
    }

    /**
     * The error groups and the progress of the jobs (v1.7.0).
     */
    public static function hasJobInsights(): bool
    {
        return static::has('meridian_jobs', 'fingerprint');
    }

    /**
     * The audit log (v1.7.0).
     */
    public static function hasAuditLog(): bool
    {
        return static::has('meridian_audit_logs');
    }

    /**
     * The scheduled tasks monitoring (v1.8.0).
     */
    public static function hasSchedule(): bool
    {
        return static::has('meridian_schedule_runs');
    }

    /**
     * The cancellation of running jobs (v1.9.0).
     */
    public static function hasCancellation(): bool
    {
        return static::has('meridian_jobs', 'cancel_requested_at');
    }

    /**
     * The origin of the jobs (v1.12.0).
     */
    public static function hasOrigin(): bool
    {
        return static::has('meridian_jobs', 'parent_uuid');
    }

    public static function flush(): void
    {
        static::$checked = [];
        PrimaryKey::flush();
    }
}
