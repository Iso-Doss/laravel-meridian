<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="art/logo-dark.svg">
        <img src="art/logo-light.svg" alt="Meridian" width="300">
    </picture>
</p>

<p align="center">
    <a href="https://github.com/Iso-Doss/laravel-meridian/actions/workflows/run-tests.yml?query=branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/Iso-Doss/laravel-meridian/run-tests.yml?branch=main&label=tests" alt="Tests"></a>
    <a href="https://github.com/Iso-Doss/laravel-meridian/actions/workflows/phpstan.yml?query=branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/Iso-Doss/laravel-meridian/phpstan.yml?branch=main&label=phpstan" alt="PHPStan"></a>
    <a href="https://packagist.org/packages/iso-doss/laravel-meridian"><img src="https://img.shields.io/packagist/dt/iso-doss/laravel-meridian" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/iso-doss/laravel-meridian"><img src="https://img.shields.io/packagist/v/iso-doss/laravel-meridian" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/iso-doss/laravel-meridian"><img src="https://img.shields.io/packagist/l/iso-doss/laravel-meridian" alt="License"></a>
</p>

## Introduction

Meridian gives the Laravel **database** queue driver what [Laravel Horizon](https://laravel.com/docs/horizon) gives Redis: a beautiful dashboard and code-driven configuration. Monitor the throughput, runtime, wait time and failures of your jobs, search them by tag, retry them in one click, and follow your batches and workers live.

All of your worker configuration lives in a single, simple configuration file. `php artisan meridian` starts and supervises your `queue:work` processes and balances them between your queues according to their workload, exactly like Horizon, so your whole team can scale the workers from source control.

**What sets Meridian apart**

- **Nothing to change in your jobs.** Every job is recorded from Laravel's own queue events: no trait, no interface, no base class. Batches, `Queue::bulk()` and `pushRaw()` jobs included.
- **A real supervisor, not only a dashboard.** `php artisan meridian` starts, restarts and auto-scales your `queue:work` processes (`auto`, `simple` or `log` strategies), with per-supervisor pause and resume, memory limit and graceful termination.
- **Horizon's features on the database driver**, without Redis and without depending on Horizon's internals: monitored tags, retry history, silenced jobs, metrics, long-wait notifications by e-mail, Slack or SMS.
- **Beyond Horizon**: failed jobs grouped by cause, alerts on failure rates, missing workers and long jobs, job progress, read-only access and an audit log.
- **Two interfaces**: a modern React / Inertia / shadcn/ui one, or a lightweight Blade one, in light, dark or system theme, in **English and French**.
- **Nothing to build.** The compiled assets ship with the package: your application needs neither Node nor an Inertia setup, and never conflicts with its own.
- **Production-ready**: works under a strict, nonce-based **Content-Security-Policy**, on MySQL, MariaDB, PostgreSQL and SQLite, with Laravel 12 and 13.

<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="art/dashboard-dark.png">
        <img src="art/dashboard-light.png" alt="The Meridian dashboard: jobs per minute, failures, pending jobs, throughput chart, workload per queue, supervisors and workers">
    </picture>
</p>

- [Why Meridian?](#why-meridian)
  - [Meridian and the other packages](#meridian-and-the-other-packages)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Configuration](#configuration)
  - [Environments and supervisors](#environments-and-supervisors)
  - [Balancing strategies](#balancing-strategies)
  - [Worker options](#worker-options)
  - [Silenced jobs](#silenced-jobs)
- [Running Meridian](#running-meridian)
  - [Deploying Meridian](#deploying-meridian)
- [The dashboard](#the-dashboard)
  - [Dashboard authorization](#dashboard-authorization)
  - [Read-only access](#read-only-access)
  - [Audit log](#audit-log)
  - [Interfaces, themes and languages](#interfaces-themes-and-languages)
  - [Content-Security-Policy](#content-security-policy)
  - [JSON API](#json-api)
- [Tags](#tags)
  - [Monitored tags](#monitored-tags)
- [Errors](#errors)
- [Job progress](#job-progress)
- [Notifications](#notifications)
  - [Alerts](#alerts)
- [Metrics](#metrics)
- [Retention](#retention)
- [How it works](#how-it-works)
- [Upgrading](#upgrading)
- [Contributing](#contributing)

## Why Meridian?

Horizon only works with Redis. If your application runs its queues on the `database` driver, you usually have no visibility on what runs in the background: the `jobs` table only holds pending jobs, and a job leaves no trace once it is processed. Meridian records the whole life of every job and brings the Horizon experience to the database driver:

- **Dashboard**: jobs per minute, jobs of the past hour, failures, pending and delayed jobs, max wait time, slowest and busiest queues, throughput chart, workload per queue, supervisors and workers.
- **Job history**: pending, processing, completed and failed jobs, with their tags, readable data (the properties of the job), payload, exception, attempts, wait time and runtime.
- **Errors**: the failed jobs grouped by cause (same exception thrown at the same line), with their number, first and last occurrence; retry a whole group in one click.
- **Monitored tags**: watch a customer, an order or any tag; its jobs are listed together and kept longer.
- **Job progress**: a job reports `$this->progress(42)` and the dashboard shows a progress bar and the estimated remaining time.
- **Search** by job class, tag (`App\Models\User:42`, or anything returned by a `tags()` method) or UUID.
- **Actions**: retry one failed job, or all the failed jobs matching the filters of the list, with the history of each retry; cancel a pending job; retry the failed jobs of a batch.
- **Batches**: progress of your `Bus::batch()` batches and of their jobs.
- **Metrics**: per job and per queue (processed, failure rate, average and max runtime, average wait), with a history of snapshots.
- **Workers**: every `queue:work` process reports a heartbeat (status, memory, jobs processed), even without Meridian's supervisor.
- **Supervisors**: `php artisan meridian` balances processes between queues (`auto`, `simple` or no balancing), with `meridian:pause`, `meridian:continue` and `meridian:terminate`, for all supervisors or one at a time.
- **Alerts** by e-mail, Slack or SMS when a queue waits too long, fails too often, has no worker, or when a job runs for too long.
- **Read-only access and audit log**: choose who can retry, delete or cancel jobs, and see who did what, and when.
- **A modern interface**: React, Inertia and shadcn/ui, light, dark and system themes, English and French, live refresh. Its compiled assets ship with the package: your application needs neither Node, React nor an Inertia setup of its own.

### Meridian and the other packages

Other packages cover part of this ground. Meridian is built to cover all of it in one place:

| | Meridian | Job monitors | Horizon on a database | Admin panel plugins |
| --- | --- | --- | --- | --- |
| Records every job without changing it | ✅ | Often needs a trait on each job | ✅ | Only pending and failed jobs |
| Supervises and auto-scales the workers | ✅ | ❌ | ✅ | ❌ |
| Monitored tags, retry history, metrics | ✅ | Partly | ✅ | ❌ |
| Errors grouped by cause, failure alerts, read-only access, audit log | ✅ | Partly | ❌ | ❌ |
| Independent from Horizon's internals | ✅ | ✅ | ❌ (rebinds Horizon's services) | ✅ |
| Standalone dashboard, no admin panel required | ✅ | ✅ | ✅ | ❌ (Filament, Nova…) |
| Strict CSP, English and French, Blade or React | ✅ | ❌ | ❌ | Depends on the panel |

Choose Meridian if you want the full Horizon experience on the `database` driver, from the dashboard to the supervision of your workers, without Redis and without adding anything to your jobs.

## Screenshots

<table>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/jobs-dark.png">
                <img src="art/jobs-light.png" alt="All jobs, with the progress and remaining time of the running ones, their tags, queue and wait time">
            </picture>
            <p align="center"><b>Jobs</b>: by status, with the progress of the running ones, filtered by queue or tag</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/job-dark.png">
                <img src="art/job-light.png" alt="A failed job with its exception, the link to the jobs failing with the same error, its retry history and data">
            </picture>
            <p align="center"><b>Job details</b>: exception, same errors, retry history, readable data</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/errors-dark.png">
                <img src="art/errors-light.png" alt="Failed jobs grouped by error, with their occurrences, first and last occurrence and a retry button">
            </picture>
            <p align="center"><b>Errors</b>: failed jobs grouped by cause, retried in one click</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/audit-dark.png">
                <img src="art/audit-light.png" alt="The audit log: who retried, cancelled or cleared what, and when">
            </picture>
            <p align="center"><b>Audit log</b>: who did what, and when</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/monitoring-dark.png">
                <img src="art/monitoring-light.png" alt="Monitored tags with their number of jobs, pending and failed jobs">
            </picture>
            <p align="center"><b>Monitoring</b>: watch tags, keep their jobs longer</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/batches-dark.png">
                <img src="art/batches-light.png" alt="Job batches with their progress">
            </picture>
            <p align="center"><b>Batches</b>: progress, pending and failed jobs</p>
        </td>
    </tr>
    <tr>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/metrics-dark.png">
                <img src="art/metrics-light.png" alt="Metrics per job: processed, failure rate, average and max runtime, average wait">
            </picture>
            <p align="center"><b>Metrics</b>: per job or per queue, over 1 hour to 7 days</p>
        </td>
        <td width="50%">
            <picture>
                <source media="(prefers-color-scheme: dark)" srcset="art/blade-dark.png">
                <img src="art/blade-light.png" alt="The Blade interface of the dashboard, with its theme and language switchers">
            </picture>
            <p align="center"><b>Blade interface</b>: the same pages, server-rendered (<code>MERIDIAN_UI=blade</code>)</p>
        </td>
    </tr>
</table>

The screenshots follow the theme of your GitHub account: switch it to see the other one.

## Installation

Meridian requires PHP 8.3+, Laravel 12 or 13, and `inertiajs/inertia-laravel` v3 (installed with the package).

```bash
composer require iso-doss/laravel-meridian
php artisan meridian:install
```

The install command publishes `config/meridian.php`, the migration and `App\Providers\MeridianServiceProvider` (registered in `bootstrap/providers.php`), then offers to run the migrations.

If your application does not have them yet, create the tables of the database queue driver:

```bash
php artisan make:queue-table
php artisan make:queue-failed-table
php artisan make:queue-batches-table
php artisan migrate
```

Meridian registers `meridian:snapshot` (every five minutes) and `meridian:prune` (hourly) in the scheduler: make sure it runs (`php artisan schedule:work` locally, a cron entry in production), or disable this with `meridian.schedule = false` and schedule them yourself.

## Configuration

After installing Meridian, its configuration lives in `config/meridian.php`. Each option is documented in the file itself.

### Environments and supervisors

The `environments` option lists, for each environment of your application, the supervisors to run. A supervisor manages a group of worker processes processing one or more queues. The supervisors of the current environment (`APP_ENV`) are started; a `'*'` environment is used when none matches.

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'database',
            'queue' => ['default', 'emails', 'images'],
            'balance' => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
        ],

        'reports' => [
            'connection' => 'database',
            'queue' => ['reports'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'timeout' => 300,
        ],
    ],

    'local' => [
        'supervisor-1' => [
            'maxProcesses' => 3,
        ],
    ],
],
```

The `defaults` option holds the values shared by every supervisor of the same name, merged with the values of the environment.

### Balancing strategies

| `balance` | Behaviour |
| --- | --- |
| `auto` (default) | Each queue gets its own pool of processes; Meridian moves processes towards the queues with the most work, between `minProcesses` per queue and `maxProcesses` in total. With `autoScalingStrategy: time` (default) the work of a queue is its pending jobs × their average runtime, with `size` only its number of pending jobs, and with `log` the logarithm of that number, so that one huge queue does not take every process. `balanceMaxShift` processes are added or removed every `balanceCooldown` seconds. |
| `simple` | `maxProcesses` is split evenly between the queues. |
| `false` | `maxProcesses` workers process the queues in the order they are listed, like `queue:work --queue=high,default`. |

Like with Horizon, the order of the queues does not set priorities with `auto` balancing: give a resource-heavy queue its own supervisor with a low `maxProcesses`.

### Worker options

| Option | Default | `queue:work` option |
| --- | --- | --- |
| `tries` | `1` | `--tries` |
| `timeout` | `60` | `--timeout` (keep it a few seconds below `retry_after`) |
| `backoff` | `0` | `--backoff` |
| `memory` | `128` | `--memory` |
| `sleep` | `3` | `--sleep` |
| `maxJobs` | `0` | `--max-jobs` |
| `maxTime` | `0` | `--max-time` |
| `rest` | `0` | `--rest` |
| `nice` | `0` | runs the workers with `nice -n` |
| `force` | `false` | `--force` (process jobs in maintenance mode) |

### Silenced jobs

Completed jobs listed in the `silenced` option, implementing `IsoDoss\Meridian\Contracts\Silenced`, or carrying a tag of the `silenced_tags` option leave the « Completed » list for the « Silenced » one, like with Horizon. They still count in the metrics and are pruned like the completed jobs. Their failures are always listed with the other failures.

```php
'silenced' => [
    App\Jobs\PingHealthCheck::class,
],

'silenced_tags' => [
    'notifications',
],
```

## Running Meridian

You have two options:

1. **Keep your workers.** Run `php artisan queue:work database ...` with Supervisor or systemd as usual: jobs, metrics and workers show up in the dashboard.
2. **Let Meridian supervise them**, like Horizon: run one long-lived process and Meridian starts, restarts and balances the `queue:work` processes of the current environment.

```bash
php artisan meridian
```

| Command | Description |
| --- | --- |
| `meridian` | Start the master supervisor |
| `meridian:status` | Show the status, the supervisors and the workload |
| `meridian:pause` / `meridian:continue` | Pause or resume the workers (`--all` for every host) |
| `meridian:pause-supervisor {name}` / `meridian:continue-supervisor {name}` | Pause or resume the workers of one supervisor (`--all` for every host) |
| `meridian:supervisors` | List the running supervisors |
| `meridian:supervisor-status {name}` | Show the status of a supervisor |
| `meridian:terminate` | Finish the running jobs, then stop the master supervisor (`--all` for every host) |
| `meridian:snapshot` | Store a metrics snapshot, import the jobs pushed without event, check the wait times |
| `meridian:prune` | Delete old completed and failed jobs, metrics and stale heartbeats |
| `meridian:forget {uuid}` / `meridian:forget --all` | Delete a failed job, or all of them, from Meridian and from the failed jobs table |
| `meridian:clear {connection?} --queue=` | Delete the jobs waiting on a queue (asks for confirmation in production, `--force` to skip it) |
| `meridian:clear-metrics` | Delete every metrics snapshot |
| `meridian:listen` | Local development: run Meridian and restart it whenever a PHP file of the application changes |

`php artisan meridian` requires the `pcntl` and `posix` extensions. When its memory exceeds `memory_limit` (64 MB by default, `0` to disable), it finishes the running jobs and exits with the code 12, so that your process monitor starts it again with fresh memory.

In local development, `php artisan meridian:listen` runs Meridian and restarts it whenever a PHP file of the directories listed in the `watch` option changes (`app`, `config`, `routes`…). Unlike `horizon:listen`, it needs neither Node nor any npm package: it polls the files every second (`--poll=` to change it).

### Deploying Meridian

Keep `php artisan meridian` running with a process monitor such as Supervisor, and run `php artisan meridian:terminate` during each deployment: the running jobs finish, Meridian exits, and the process monitor restarts it with your new code.

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

`stopwaitsecs` must be longer than your longest job.

## The dashboard

The dashboard is available at `/meridian`. Its pages refresh themselves every `meridian.poll` seconds (switch « Live » in the header).

### Dashboard authorization

In the `local` environment everybody can open the dashboard. In the other environments, access is granted by the `viewMeridian` gate of `app/Providers/MeridianServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewMeridian', function ($user = null) {
        return in_array(optional($user)->email, [
            'taylor@example.com',
        ]);
    });
}
```

You may also call `Meridian::auth(fn (Request $request) => ...)` directly. The prefix, domain and middleware of the dashboard and of its API are configured in the `route` option (`MERIDIAN_PREFIX`, `MERIDIAN_API_PREFIX`, `MERIDIAN_DOMAIN`); the authorization middleware is always added on top of them.

### Read-only access

By default, everybody who can open the dashboard can also retry, delete and cancel jobs, and monitor tags. To give some people a read-only access, define the `manageMeridian` gate in `app/Providers/MeridianServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewMeridian', fn ($user = null) => in_array(optional($user)->email, [
        'taylor@example.com',
        'support@example.com',
    ]));

    // Only Taylor can act on the jobs, the support team can only look.
    Gate::define('manageMeridian', fn ($user = null) => in_array(optional($user)->email, [
        'taylor@example.com',
    ]));
}
```

or call `Meridian::manage(fn (Request $request) => ...)`. The read-only users browse every page, but the buttons that act on the jobs are hidden and their requests are refused (403). The page header shows « Read only ».

### Audit log

The « Audit log » page lists who retried, deleted or cancelled a job, retried a batch or a group of failed jobs, monitored a tag, and who paused, resumed, terminated or cleared Meridian from the command line, with the date, the IP address and the details of each action. Users are named by their `email`, else their `name`, else their identifier; Artisan commands appear as « Console ». The entries are kept `trim.audit` minutes (30 days by default) and are also available as JSON (`GET /meridian/api/audit`).

### Interfaces, themes and languages

Two interfaces ship with Meridian:

- **Inertia** (default): React, Inertia and shadcn/ui. It has its own root view, Inertia middleware and asset version: your application's Inertia setup (shared props, SSR, root view) is never involved, even when it uses Inertia too.
- **Blade**: a lightweight server-rendered interface, with the same pages.

Both offer a light, dark or system theme (kept in the browser) and are translated in English and French: the language follows the application locale and can be changed from the interface (kept in a cookie). Restrict the offered languages with the `locales` option.

To use the Blade interface, set in your `.env`:

```dotenv
MERIDIAN_UI=blade
```

or `'ui' => 'blade'` in a published `config/meridian.php` (republish it with `php artisan vendor:publish --tag=meridian-config --force` to get the keys added since your version), then `php artisan config:clear` if the configuration is cached. Both interfaces share the same URLs, route names, actions and JSON API.

### Content-Security-Policy

Meridian works under a strict, nonce-based CSP such as `script-src 'nonce-…' 'strict-dynamic' 'self'`: when your application sets a nonce with `Vite::useCspNonce()`, Meridian puts it on its scripts and styles and passes it to Inertia. There is nothing to configure, and no need to exempt Meridian's routes from your policy.

### JSON API

The dashboard data is also available as JSON, behind the same authorization: `GET /meridian/api/stats`, `/meridian/api/workload`, `/meridian/api/jobs/{status?}?search=&queue=` and `/meridian/api/jobs/{uuid}`.

## Tags

Meridian tags jobs, mailables, broadcast events, notifications and queued event listeners like Horizon does. The Eloquent models held by a job are tagged automatically: a `RenderVideo` job holding the `App\Models\Video` with the id `1` gets the `App\Models\Video:1` tag. To tag a job yourself, give it a `tags` method:

```php
class RenderVideo implements ShouldQueue
{
    public function tags(): array
    {
        return ['render', 'video:'.$this->video->id];
    }
}
```

Click a tag anywhere in the dashboard to list every job carrying it. The page of a retried job lists every retry with its outcome, runtime and exception. On the failed jobs page, « Retry all » pushes back every failed job matching the current filters (queue, search): retry all the failed jobs of a queue, or of a customer by searching its tag.

### Monitored tags

Like Horizon, the « Monitoring » page lets you watch tags: add `App\Models\User:42` or `customer:42` and its jobs are listed together, with their number of pending and failed jobs. The jobs carrying a monitored tag are kept `trim.monitored` minutes (7 days by default) instead of `trim.completed`.

Monitored tags are stored in the `meridian_monitored_tags` table. When upgrading from a version older than 1.4.0 (monitored tags), 1.5.0 (retry history) or 1.7.0 (errors, job progress, audit log), publish and run the new migrations:

```bash
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

## Errors

The « Errors » page groups the failed jobs by cause: the same exception class thrown at the same line, whatever its message (`User 12 not found` and `User 57 not found` are one error). Each group shows the exception, its message and location, the jobs and queues it affects, its number of occurrences and when it was first and last seen. Open a group to list its jobs, or « Retry all » to push them all back onto their queue once the bug is fixed. The page of a failed job links to the other jobs failing with the same error. The groups are also available as JSON (`GET /meridian/api/errors`).

## Job progress

Long jobs can report their progress with the `IsoDoss\Meridian\Concerns\TracksProgress` trait: the dashboard shows a progress bar, the estimated remaining time and an optional message while they run.

```php
use IsoDoss\Meridian\Concerns\TracksProgress;

class ImportCustomers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, TracksProgress;

    public function handle(): void
    {
        foreach ($this->rows as $index => $row) {
            // ...

            $this->progressOf($index + 1, count($this->rows), ($index + 1).' customers imported');
        }
    }
}
```

`$this->progress(42)` takes a percentage, `$this->progressOf($done, $total)` computes it. Meridian only writes when the percentage or the message changes, so you may call them in a tight loop.

## Notifications

When the oldest job of a queue waits longer than its threshold, Meridian fires an `IsoDoss\Meridian\Events\LongWaitDetected` event and notifies the configured routes, at most once every `notifications.cooldown` minutes per queue. Like with Horizon, set the routes in the `boot` method of `App\Providers\MeridianServiceProvider`:

```php
use IsoDoss\Meridian\Meridian;

public function boot(): void
{
    parent::boot();

    Meridian::routeMailNotificationsTo('ops@example.com');
    Meridian::routeSlackNotificationsTo('https://hooks.slack.com/services/…', '#queues');
    Meridian::routeSmsNotificationsTo('+15555550123');
}
```

or in your `.env`: `MERIDIAN_NOTIFY_MAIL`, `MERIDIAN_NOTIFY_SLACK_WEBHOOK` (and `MERIDIAN_NOTIFY_SLACK_CHANNEL`), `MERIDIAN_NOTIFY_SMS`. Slack requires `laravel/slack-notification-channel` and SMS `laravel/vonage-notification-channel`.

The thresholds, in seconds, are set per `connection:queue` in the `waits` option. Like with Horizon, a queue not listed uses `'*'`, or 60 seconds when `'*'` is not set; `0` disables the notifications of a queue:

```php
'waits' => [
    'database:default' => 60,
    'database:reports' => 600,
    '*' => 120,
],
```

### Alerts

Besides long waits, `meridian:snapshot` (every five minutes) checks the health of your queues and notifies the same routes, at most once per `notifications.cooldown` minutes for the same problem:

| Alert | Event | When |
| --- | --- | --- |
| High failure rate | `FailureRateExceeded` | `failure_rate` % of the jobs of a queue failed during the last `window` minutes, once at least `failure_rate_minimum` jobs ran; or `failures` jobs failed |
| No worker | `NoWorkersRunning` | jobs are waiting but no worker has reported itself |
| Long running job | `LongRunningJobDetected` | a job has been running for more than `long_running` seconds |

```php
'alerts' => [
    'window' => 5,                 // minutes
    'failure_rate' => 25,          // %, 0 to disable
    'failure_rate_minimum' => 10,  // jobs
    'failures' => 0,               // failed jobs, 0 to disable
    'no_workers' => true,
    'long_running' => 0,           // seconds, 0 to disable
],
```

The events live in `IsoDoss\Meridian\Events`: listen to them to send the alerts anywhere else.

## Metrics

The dashboard shows the slowest queue (highest average runtime) and the busiest one (most jobs processed) of the last hour. The metrics page shows, per job class and per queue, the jobs processed and failed, the failure rate, the average and max runtime and the average wait, over the last hour to the last seven days. Each job and queue also has its history of snapshots, stored every five minutes by `meridian:snapshot`.

## Retention

`meridian:prune` deletes the completed jobs after `trim.completed` minutes (1 day by default), the failed ones after `trim.failed` minutes (7 days), the metric snapshots after `trim.metrics` minutes (7 days) and the stale heartbeats of workers and supervisors after `trim.workers` minutes, and the audit log entries after `trim.audit` minutes (30 days). Pending and processing jobs are never pruned. Set `store_payload` to `false` to keep the table small on very busy queues.

## How it works

| Horizon (Redis) | Meridian (database) |
| --- | --- |
| Jobs stored in Redis by Horizon's own queue | Laravel's queue events (`JobQueued`, `JobProcessing`, `JobProcessed`, `JobFailed`, `JobReleasedAfterException`, `JobRetryRequested`) are recorded in a `meridian_jobs` table, keyed by the job UUID |
| Workload read from Redis lists | Workload read from the `jobs` table: pending, delayed and reserved jobs, oldest available job |
| Supervisors and workers in Redis | `meridian_supervisors` and `meridian_workers` tables (heartbeats) |
| Commands through a Redis command queue | Commands written in `meridian_supervisors`, read by the master every second |
| Metrics snapshots in Redis | `meridian:snapshot` aggregates them into `meridian_metrics` |

Jobs inserted without event (`Queue::bulk()`, used by job batches, `pushRaw()`, jobs pushed by another application or before Meridian was installed) are imported from the `jobs` table when a batch is dispatched, when the dashboard is opened and at every snapshot. Recording never breaks your application: every write is guarded and its errors are only reported.

A few notes:

- Keep Meridian's tables on a dedicated connection with `MERIDIAN_DB_CONNECTION` to move the monitoring writes away from your main database. Each job costs two or three small writes.
- With **SQLite** and several workers, Laravel's own `DatabaseQueue::pop()` may hit `database is locked` errors: enable WAL and a busy timeout (`'journal_mode' => 'wal'`, `'busy_timeout' => 5000`), and prefer MySQL or PostgreSQL in production.

## Upgrading

Every release and its upgrade notes are listed in the [changelog](CHANGELOG.md). Since v1.1.0 the React / Inertia interface is the default one; set `MERIDIAN_UI=blade` to keep the previous one.

## Contributing

```bash
composer test
composer analyse
```

The interface lives in `resources/js` (React, TypeScript, Tailwind CSS v4, shadcn/ui) and is compiled into `dist/`, which is committed. The CI checks that `dist/` matches the sources.

```bash
npm ci
npm run build      # or npm run dev to rebuild on change
npm run typecheck
npm test
```

Texts go through `t('English text')` in React and `$t('English text')` in Blade; add their French translation to `lang/fr.json` (a test fails when one is missing).

## Security Vulnerabilities

Please report security vulnerabilities through the [security advisories](https://github.com/Iso-Doss/laravel-meridian/security/advisories/new) of the repository rather than in a public issue.

## Credits

- Inspired by [Laravel Horizon](https://github.com/laravel/horizon), whose tag extraction is adapted.
- Built on [spatie/package-skeleton-laravel](https://github.com/spatie/package-skeleton-laravel).

## License

Meridian is open-sourced software licensed under the [MIT license](LICENSE.md).
