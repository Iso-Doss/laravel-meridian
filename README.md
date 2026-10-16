# Meridian — a Horizon-like dashboard for Laravel **database** queues

[Laravel Horizon](https://laravel.com/docs/horizon) only works with Redis. Meridian brings the same
experience to applications using the `database` queue driver:

- **Dashboard**: throughput, jobs per minute, failures, pending / delayed / reserved jobs per queue, max wait time.
- **Job history**: pending, processing, completed and failed jobs, with tags, payload, exception, attempts, wait time and runtime.
- **Search** by job class, tag (`App\Models\User:42`, or anything returned by a `tags()` method) or UUID.
- **Actions**: retry a failed job, cancel a pending job, retry the failed jobs of a batch.
- **Batches**: progress of `Bus::batch()` batches and their jobs.
- **Metrics**: per job and per queue (count, failure rate, average / max runtime, average wait), with history snapshots.
- **Workers**: every `queue:work` process reports a heartbeat (memory, jobs processed, status) — even without Meridian's supervisor.
- **Supervisor** (optional): `php artisan meridian` starts and supervises `queue:work` processes, auto-balancing them between queues according to the number of pending jobs, exactly like Horizon. `meridian:pause`, `meridian:continue`, `meridian:terminate` included.
- **Long wait notifications** per `connection:queue`.

The UI is plain Blade + a few lines of vanilla JS: no front-end build, no CDN.

## How it works

| Horizon (Redis) | Meridian (database) |
| --- | --- |
| Jobs stored in Redis hashes by Horizon's own `RedisQueue` | Laravel queue events (`JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed`, `JobReleasedAfterException`, `JobRetryRequested`) are recorded in a `meridian_jobs` table, keyed by the job UUID |
| Workload read from Redis lists | Workload read from the `jobs` table (`pending`, `delayed`, `reserved`, oldest available job) |
| Supervisors state in Redis | `meridian_supervisors` and `meridian_workers` tables (heartbeats) |
| Commands sent through a Redis command queue | Commands written in `meridian_supervisors.command`, polled by the master every second |
| Metrics snapshots in Redis | `meridian:snapshot` aggregates into `meridian_metrics` |
| `horizon:snapshot` / trimming in the master loop | `meridian:snapshot` (every 5 min) and `meridian:prune` (hourly), auto-registered in the scheduler |

Jobs inserted without event — `Queue::bulk()` (used by job batches), `pushRaw()`, jobs pushed by another application or
before installing Meridian — are imported from the `jobs` table when a batch is dispatched, when the dashboard is opened and on every snapshot.

Monitoring never breaks your application: every write is wrapped and errors are only reported.

## Installation

```bash
composer require kakodjenou/laravel-meridian
php artisan meridian:install
```

The install command publishes `config/meridian.php`, the migration and `App\Providers\MeridianServiceProvider`
(registered in `bootstrap/providers.php`), then offers to run the migrations.

If not done yet, create the queue tables used by the database driver:

```bash
php artisan make:queue-table
php artisan make:queue-failed-table
php artisan make:queue-batches-table
php artisan migrate
```

Make sure the scheduler runs (`php artisan schedule:work` locally, a cron entry in production): Meridian registers
`meridian:snapshot` and `meridian:prune` automatically (disable with `meridian.schedule = false`).

## Dashboard authorization

The dashboard is available at `/meridian`. In the `local` environment everybody can access it; in other environments
access is granted by the `viewMeridian` gate defined in `app/Providers/MeridianServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewMeridian', function ($user = null) {
        return in_array(optional($user)->email, [
            'admin@example.com',
        ]);
    });
}
```

You can also use `Meridian::auth(fn (Request $request) => ...)` directly.

## Running the workers

You have two options.

**1. Keep your current workers.** Run `php artisan queue:work database ...` with Supervisor/systemd as usual: jobs, metrics
and workers will show up in the dashboard.

**2. Let Meridian supervise them**, like Horizon. Configure the supervisors per environment in `config/meridian.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'database',
            'queue' => ['high', 'default', 'emails'],
            'balance' => 'auto',      // "auto", "simple" or false
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

then run a single long-lived process (managed by Supervisor/systemd):

```bash
php artisan meridian
```

- `balance: auto` gives each queue its own pool and moves processes towards the queues with the most work
  (`pending jobs × average runtime`, or only pending jobs with `autoScalingStrategy: size`), between `minProcesses` per queue and `maxProcesses` in total.
- `balance: simple` splits `maxProcesses` evenly between the queues.
- `balance: false` runs `maxProcesses` workers processing the queues in priority order.

Supervisor configuration example:

```ini
[program:meridian]
process_name=%(program_name)s
command=php /var/www/app/artisan meridian
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/meridian.log
stopwaitsecs=3600
```

On deploy, run `php artisan meridian:terminate`: the master finishes the running jobs, exits, and your process monitor
restarts it with the new code.

## Commands

| Command | Description |
| --- | --- |
| `meridian` | Start the master supervisor |
| `meridian:status` | Status, supervisors, workload |
| `meridian:pause` / `meridian:continue` | Pause / resume the workers (`--all` for every host) |
| `meridian:terminate` | Gracefully stop the master supervisor (`--all` for every host) |
| `meridian:snapshot` | Store metrics, import unseen jobs, check long wait times |
| `meridian:prune` | Delete old completed / failed jobs, metrics and stale heartbeats |

## Tags and silenced jobs

Like Horizon, Eloquent models held by a job are tagged automatically (`App\Models\Order:15`). Define your own tags with a `tags()` method:

```php
public function tags(): array
{
    return ['billing', 'customer:'.$this->customer->id];
}
```

Completed jobs listed in `meridian.silenced`, implementing `Kakodjenou\Meridian\Contracts\Silenced`, or having a tag
listed in `meridian.silenced_tags` are not kept (failures always are).

## Notifications

Configure thresholds in `meridian.waits` (`'database:default' => 60`, or `'*'` for all queues). When the oldest job
waits longer, a `Kakodjenou\Meridian\Events\LongWaitDetected` event is fired and, if `MERIDIAN_NOTIFY_MAIL` is set, an email is sent.

## JSON API

`GET /meridian/api/stats`, `/meridian/api/workload`, `/meridian/api/jobs/{status?}?search=&queue=`, `/meridian/api/job/{uuid}`.

## Notes

- Use a dedicated connection for Meridian's tables with `MERIDIAN_DB_CONNECTION` if you want to keep the monitoring writes away from your main database.
- With **SQLite** and several workers, Laravel's own `DatabaseQueue::pop()` may hit `database is locked` errors. Enable WAL and a busy timeout
  (`'journal_mode' => 'wal'`, `'busy_timeout' => 5000` in `config/database.php`) and prefer MySQL / PostgreSQL in production.
- Each job costs 2–3 small writes in `meridian_jobs`. Tune `trim` and `store_payload` for very high volumes.
- `pcntl` / `posix` are required for `php artisan meridian` (pause, continue, graceful termination).

## Testing

```bash
composer test
composer analyse
```

## Credits

- Inspired by [laravel/horizon](https://github.com/laravel/horizon) (tag extraction is adapted from it).
- Built on [spatie/package-skeleton-laravel](https://github.com/spatie/package-skeleton-laravel).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
