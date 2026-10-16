<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | The URI path, optional domain and middleware of the Meridian dashboard.
    | Access is additionally protected by the "viewMeridian" gate (see the
    | published App\Providers\MeridianServiceProvider).
    |
    */

    'domain' => env('MERIDIAN_DOMAIN'),

    'path' => env('MERIDIAN_PATH', 'meridian'),

    'middleware' => ['web'],

    // Number of seconds between two automatic refreshes of the dashboard (0 = off).
    'poll' => 5,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The database connection used to store Meridian's own tables. "null"
    | means the application's default connection.
    |
    */

    'database' => env('MERIDIAN_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Watched queue connections
    |--------------------------------------------------------------------------
    |
    | The queue connections Meridian records jobs for. "null" means every
    | connection (from config/queue.php) using the "database" driver.
    |
    */

    'connections' => null,

    // Store the raw job payload (useful for debugging, but takes space).
    'store_payload' => true,

    /*
    |--------------------------------------------------------------------------
    | Silenced jobs
    |--------------------------------------------------------------------------
    |
    | Completed jobs matching these classes or tags are not kept in the
    | dashboard. Failures are always recorded.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (in minutes)
    |--------------------------------------------------------------------------
    |
    | "meridian:prune" deletes recorded jobs older than these values.
    | Pending and processing jobs are never pruned.
    |
    */

    'trim' => [
        'completed' => 1440,  // 1 day
        'failed' => 10080,    // 7 days
        'metrics' => 10080,   // 7 days of metric snapshots
        'workers' => 60,      // stale worker / supervisor heartbeats
    ],

    /*
    |--------------------------------------------------------------------------
    | Long wait thresholds (in seconds)
    |--------------------------------------------------------------------------
    |
    | When the oldest job waiting on a "connection:queue" exceeds the given
    | threshold, a LongWaitDetected event is fired (and a notification is
    | sent if configured below). Use "*" for a default threshold.
    |
    */

    'waits' => [
        'database:default' => 60,
    ],

    'notifications' => [
        'mail' => env('MERIDIAN_NOTIFY_MAIL'),
        // Minimum number of minutes between two notifications for the same queue.
        'cooldown' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | When enabled, Meridian registers "meridian:snapshot" (every 5 minutes)
    | and "meridian:prune" (hourly) in the application scheduler.
    |
    */

    'schedule' => true,

    /*
    |--------------------------------------------------------------------------
    | Worker heartbeat
    |--------------------------------------------------------------------------
    |
    | Workers (plain queue:work or managed by meridian) report themselves at
    | most every "heartbeat" seconds. A worker that has not been seen for
    | "worker_timeout" seconds is considered gone.
    |
    */

    'heartbeat' => 5,

    'worker_timeout' => 90,

    /*
    |--------------------------------------------------------------------------
    | Supervisors (php artisan meridian)
    |--------------------------------------------------------------------------
    |
    | Like Horizon, "php artisan meridian" starts a master process that spawns
    | and supervises "queue:work" processes per environment, balancing them
    | between queues according to the number of pending jobs.
    |
    | balance: "auto" (by workload), "simple" (even split) or false (a single
    | pool processing the queues in priority order).
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'database',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time', // "time" or "size"
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'balanceCooldown' => 3,
            'balanceMaxShift' => 1,
            'tries' => 1,
            'timeout' => 60,
            'memory' => 128,
            'sleep' => 3,
            'backoff' => 0,
            'maxTime' => 0,
            'maxJobs' => 0,
            'rest' => 0,
            'nice' => 0,
            'force' => false,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
        ],
    ],

];
