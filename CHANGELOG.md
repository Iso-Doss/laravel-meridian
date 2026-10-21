# Changelog

All notable changes to `laravel-meridian` will be documented in this file.

## v1.10.0 - 2026-10-21

Comfort and adoption: alerts to any webhook, CSV / JSON exports, richer metrics, six languages and a documentation site.

📖 **Documentation**: https://iso-doss.github.io/laravel-meridian/

### Added

- **Webhooks**: send the alerts to Discord, Microsoft Teams, Google Chat, Slack or any URL, without any extra package: `Meridian::routeWebhookNotificationsTo($url)` or `MERIDIAN_NOTIFY_WEBHOOKS` (comma separated). The format is recognized from the URL (or forced with the second argument): Discord embeds, Teams Adaptive Cards (incoming webhooks and Workflows), Google Chat and Slack messages. Any other URL receives a JSON payload (`event`, `title`, `summary`, `url`, `app`, `environment`, `occurred_at`, `data`), signed with the `X-Meridian-Signature` header (HMAC SHA-256) when `MERIDIAN_NOTIFY_WEBHOOK_SECRET` is set. A failing webhook does not prevent the others.
- **Exports**: « Export » downloads the jobs (with the filters of the list) or the error groups as **CSV** or **JSON** (`/meridian/jobs/export`, `/meridian/errors/export`). Streamed whatever their size; the CSV opens with its accents in Excel, and the cells a spreadsheet would run as formulas are neutralized.
- **Richer metrics**:
  - **p95 and p99** runtimes per job and per queue, besides the average and the maximum;
  - the **trend** of the throughput, the failure rate and the runtime against the previous period of the same length (worse in red);
  - a **heatmap of the jobs per hour** over the last seven days, in the visitor's timezone, the hours where 10 % or more of the jobs failed outlined.
- **Four more languages**: Spanish, Portuguese, German and **Arabic, written from right to left**, in both interfaces. The six languages are offered by default (`locales` option).
- **Documentation site** built with VitePress, with search: https://iso-doss.github.io/laravel-meridian/

### Changed

- The `locales` option now lists `['en', 'fr', 'es', 'pt', 'de', 'ar']`.
- README: new sections and updated screenshots (metrics).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration. If you published `config/meridian.php`, add the new languages to `locales` to offer them, and the new `notifications.webhooks` and `notifications.webhook_secret` keys if you want them in the configuration. If you published the Blade views, republish them.

## v1.9.0 - 2026-10-21

Completes the parity with Laravel Horizon: fast termination, cancelling running jobs, `meridian:purge` and `meridian:list`.

### Added

- **Fast termination**: like Horizon's `fast_termination`, `'fast_termination' => true` makes `meridian:terminate` exit the master right away while its workers finish their current job on their own, so that a deployment starts the new master without waiting for the longest job. Disabled by default.
- **Cancel a running job**: « Cancel job » now also works on a job being processed (shown as « Cancelling… »):
  - a job using the `TracksProgress` trait stops cleanly when it checks `$this->cancelled()` (one query per second at most), and Meridian forgets it;
  - otherwise, when it runs under `php artisan meridian`, the master stops the worker running it after `cancel_timeout` seconds (10 by default), removes the job from the queue and starts a new worker.
  
  A job run by a worker Meridian does not supervise, and that does not check `$this->cancelled()`, runs to completion. Every cancellation is recorded in the audit log.
- **`php artisan meridian:purge`**: terminates the workers left behind by a master that was killed without terminating them (`--signal=SIGTERM`), and forgets the dead supervisors and workers of the machine. Only the `queue:work` processes of this application started for a configured supervisor are concerned.
- **`php artisan meridian:list`**: lists the machines running Meridian (master, host, PID, supervisors, processes, status, start).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

The new migration adds the `cancel_requested_at` column to `meridian_jobs` (the migrations you already published are skipped). Until it runs, running jobs cannot be cancelled and everything else works as before. Restart Meridian (`php artisan meridian:terminate`) after upgrading.

If you published `config/meridian.php`, you may add the new `fast_termination` and `cancel_timeout` keys (defaults apply when they are missing). If you published the Blade views, republish them.

## v1.8.0 - 2026-10-21

Retry with corrected data, scheduled tasks monitoring, and Meridian in the tools your team already uses: Prometheus / OpenTelemetry, Laravel Pulse, Filament and the terminal.

### Added

- **Retry with corrected data**: « Edit and retry » on a failed job changes its plain values (strings, numbers, booleans, arrays) and pushes it back onto its queue, when it failed because of an invalid value. Models, enums, dates, objects and the queue settings of the job (`tries`, `timeout`...) are kept as they are; encrypted jobs stay encrypted; invalid values are refused field by field. Only the people allowed by the new `editMeridianJobs` gate (or `Meridian::editJobs()`), among those who can act on the jobs, can do it; without the gate, only in the `local` environment. Every change is recorded in the audit log.
- **Scheduled tasks** (« Schedule » page): Meridian records every run of `schedule:run` (succeeded, failed with its exception or exit code, skipped, running in background) and lists the tasks with their cron expression, last run, duration, recent runs and next run. The tasks that **did not run when due** (`schedule_grace`, 5 minutes) are flagged. Two new alerts: `ScheduleTaskFailed` (sent right away) and `ScheduleTaskMissed`. Also `GET /meridian/api/schedule`.
- **Prometheus / OpenTelemetry**: `GET /meridian/prometheus` exposes the queues, throughput, failures, runtimes, workers, error groups and scheduled tasks in the Prometheus text format, for Prometheus, the OpenTelemetry Collector, Grafana or Datadog. Disabled by default: `MERIDIAN_PROMETHEUS=true` and a `MERIDIAN_PROMETHEUS_TOKEN` bearer token for the scrapers.
- **Laravel Pulse card**: when Pulse is installed, `<livewire:meridian cols="4" />` shows the status, throughput, queues and most frequent errors.
- **Filament plugin** (v4 and v5): `MeridianPlugin::make()` adds Meridian's figures and errors to the panel dashboard and a link to Meridian in the navigation (`navigationGroup()`, `navigationLabel()`, `navigationSort()`, `withoutWidgets()`, `withoutNavigation()`).
- **`php artisan meridian:top`**: a live view of the queues, running jobs with their progress, supervisors, errors and failing scheduled tasks in the terminal (`--interval=2`, `--once`), for the servers without access to the dashboard.

Pulse and Filament are optional: nothing is installed in your application, they are only suggested.

### Changed

- README: new sections and updated screenshots (schedule, terminal).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

The new migration creates the `meridian_schedule_tasks` and `meridian_schedule_runs` tables (the migrations you already published are skipped). Until it runs, Meridian works as before, without the scheduled tasks monitoring.

If you published `config/meridian.php`, you may add the new `prometheus`, `schedule_grace`, `trim.schedule`, `alerts.schedule_failures` and `alerts.schedule_missed` keys (defaults apply when they are missing). If you published the Blade views, republish them. The two scheduled tasks alerts are enabled by default and are only sent when notification routes are configured.

## v1.7.0 - 2026-10-21

Production features beyond Horizon: errors grouped by cause, health alerts, job progress, audit log and read-only access.

### Added

- **Errors page**: the failed jobs grouped by cause, the same exception class thrown at the same line whatever its message (`User 12 not found` and `User 57 not found` are one error). Each group shows the exception, its message and location, the jobs and queues it affects, its occurrences and when it was first and last seen. Open a group to list its jobs, or « Retry all » to push them back once the bug is fixed. The page of a failed job links to the jobs failing with the same error. Also `GET /meridian/api/errors`.
- **Alerts**, checked by `meridian:snapshot` and notified by e-mail, Slack or SMS like the long waits (at most once per `notifications.cooldown`):
  - **high failure rate**: `failure_rate` % of the jobs of a queue failed during the last `window` minutes (25 % over 5 minutes, from 10 jobs, by default), or `failures` failed jobs;
  - **no worker**: jobs are waiting but no worker is running;
  - **long running job**: a job runs for more than `long_running` seconds (disabled by default).

  Each alert fires an event (`FailureRateExceeded`, `NoWorkersRunning`, `LongRunningJobDetected`) you may listen to.
- **Job progress**: with the `IsoDoss\Meridian\Concerns\TracksProgress` trait, a job reports `$this->progress(42)` or `$this->progressOf($done, $total, 'message')`, and the dashboard shows a progress bar, the estimated remaining time and the message while it runs.
- **Audit log**: who retried, deleted or cancelled a job, retried a batch or an error group, monitored a tag, and who paused, resumed, terminated or cleared Meridian from the command line (« Console »), with the date and IP address. Filter by action, kept `trim.audit` minutes (30 days). Also `GET /meridian/api/audit`.
- **Read-only access**: define a `manageMeridian` gate (or call `Meridian::manage()`) to choose who can act on the jobs. The others browse every page, the action buttons are hidden and their requests refused (403). Without the gate, nothing changes.

Everything is available in both interfaces, in English and French.

### Changed

- The long wait notification is sent by the new `SendAlertNotification` listener (the internal `SendLongWaitNotification` listener is removed); its mail subject is now « Long wait detected ».
- README: new sections and updated screenshots.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

The new migrations add the `fingerprint`, `progress` and `progress_message` columns to `meridian_jobs` (and group the failed jobs already recorded), and create the `meridian_audit_logs` table. Until they run, Meridian works as before, without these features. Restart your workers (`php artisan meridian:terminate`) after migrating.

If you published `config/meridian.php`, you may add the new `alerts` and `trim.audit` keys (defaults apply when they are missing). If you published the Blade views, republish them. The « no worker » alert is enabled by default: applications with notification routes receive it when jobs wait without any running worker; set `alerts.no_workers` to `false` to disable it.

## v1.6.2 - 2026-10-20

Maintenance release. No change to the package code nor to the compiled assets.

### Changed

- **Build**: the dashboard assets are now built with **Node 22** (`.nvmrc`), Node 20 having reached its end of life in April 2026. The compiled `dist/` is identical, and your application still needs no Node at all.
- CI: `actions/setup-node` bumped from v5 to v7 (#1).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change.

## v1.6.1 - 2026-10-19

Documentation and package metadata release. No code change.

### Changed

- **README**: a « What sets Meridian apart » summary under the introduction (no change to your jobs, a real supervisor with auto-scaling, Horizon's features without Redis nor Horizon's internals, React or Blade interfaces in English and French, nothing to build, strict CSP), and a new « Meridian and the other packages » section comparing Meridian with job monitors, Horizon running on a database and admin panel plugins.
- README: clearer « Actions » and « Supervisors » descriptions.
- **Package description** (`composer.json`, shown on Packagist) aligned with the GitHub repository.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change.

## v1.6.0 - 2026-10-17

Completes the parity with Laravel Horizon.

### Added

- **`log` auto-scaling strategy**: with `'autoScalingStrategy' => 'log'`, the processes are shared by the logarithm of the pending jobs of each queue, so that one huge queue does not take every process.
- **`php artisan meridian:listen`** for local development: runs Meridian and restarts it whenever a PHP file of the directories listed in the new `watch` option changes. Unlike `horizon:listen`, it needs neither Node nor any npm package (it polls the files, `--poll=` seconds). Cache, `vendor` and `storage` directories are skipped.
- **« Silenced » tab**: like Horizon, silenced jobs (`silenced`, `silenced_tags`, `Silenced` contract) now leave the « Completed » list for their own tab, in both interfaces.
- **Master memory limit**: when `php artisan meridian` exceeds `memory_limit` (64 MB by default, `0` to disable), it finishes the running jobs and exits with the code 12 so that your process monitor restarts it; a `MasterSupervisorOutOfMemory` event is fired.

### Changed

- **Silenced jobs are kept** (status `silenced`) until `meridian:prune` removes them like the completed jobs, instead of being deleted as soon as they complete. They now count in the metrics.
- **Long waits**: a queue not listed in `waits` now uses `'*'`, or **60 seconds** when `'*'` is not set (it was ignored before), like Horizon. `0` disables a queue. Notifications are only sent when routes are configured.
- README: updated screenshots.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration. If you published `config/meridian.php`, you may add the new `memory_limit` and `watch` keys (defaults apply when they are missing). If you published the Blade views, republish them.

## v1.5.0 - 2026-10-17

### Added

- **Control one supervisor at a time**, like Horizon: `meridian:pause-supervisor {name}` and `meridian:continue-supervisor {name}` pause or resume the workers of one supervisor while the others keep working (`--all` for every host). Each supervisor now has its own status in the dashboard.
- `meridian:supervisors` lists the running supervisors, `meridian:supervisor-status {name}` shows the status of one.
- **Cleanup commands**: `meridian:forget {uuid}` / `meridian:forget --all` delete a failed job, or all of them, from Meridian and from the failed jobs table; `meridian:clear {connection?} --queue=` deletes the jobs waiting on a queue (jobs being processed finish normally; asks for confirmation in production, `--force` to skip it); `meridian:clear-metrics` deletes every metrics snapshot.
- **Retry history**: the page of a retried job lists every retry (single, « Retry all » or `queue:retry`) with its date, status, end, runtime and exception. The history is deleted with the job.

### Changed

- Blade interface: the dashboard cards are laid out on four columns on wide screens.
- README: new screenshots (dashboard, monitoring, job data and retries, Blade interface).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

The new migration creates the `meridian_job_retries` table (the migrations you already published are skipped). Until it runs, Meridian works as before, without recording the retry history. If you published the Blade views, republish them.

## v1.4.0 - 2026-10-17

Horizon parity release.

### Added

- **Monitored tags**: a « Monitoring » page (both interfaces, and `GET /meridian/api/monitoring`) to watch tags such as `App\Models\User:42` or `customer:42`, with their number of jobs, pending and failed jobs. The jobs carrying a monitored tag are kept `trim.monitored` minutes (7 days by default) instead of `trim.completed`.
- **Clickable tags**: a tag anywhere in the dashboard opens the jobs carrying it (exact `tag` filter). « Retry all » honours it: retry all the failed jobs of a customer in one click.
- **Slack and SMS notifications** for long waits, besides e-mail, with Horizon's API in a service provider: `Meridian::routeMailNotificationsTo()`, `routeSlackNotificationsTo($webhook, $channel)` and `routeSmsNotificationsTo($number)`, or the `MERIDIAN_NOTIFY_MAIL`, `MERIDIAN_NOTIFY_SLACK_WEBHOOK`, `MERIDIAN_NOTIFY_SLACK_CHANNEL` and `MERIDIAN_NOTIFY_SMS` variables. Slack requires `laravel/slack-notification-channel`, SMS `laravel/vonage-notification-channel`.
- **Readable job data**: the job page lists the properties of the job (models as `App\Models\User:42`, collections, dates, enums, arrays…), encrypted jobs included, instead of only the serialized payload.
- **Dashboard**: the slowest queue (highest average runtime) and the busiest one (most jobs processed) of the last hour.

### Fixed

- Tag searches did not find model tags (`App\Models\User:42`) on MySQL and PostgreSQL: their LIKE patterns now use an explicit escape character, which behaves the same on every database.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
php artisan vendor:publish --tag=meridian-migrations
php artisan migrate
```

The new migration creates the `meridian_monitored_tags` table (the migrations you already published are skipped). If you published `config/meridian.php`, add the new keys from the package's config (`trim.monitored`, `notifications.slack`, `notifications.slack_channel`, `notifications.sms`), or republish it. If you published the Blade views, republish them.

## v1.3.0 - 2026-10-17

### Added

- **Retry all failed jobs**: a « Retry all » button on the failed jobs page pushes back every failed job matching the current filters (queue, search), with a confirmation giving their number. Retry all the failed jobs of a queue, or of a customer by searching its tag. Large lists are processed by chunks of 500.
- **Retry from the list**: every failed job of the job lists (failed jobs, batch details) has its own « Retry » button, no need to open its details. The actions column stays visible when the table scrolls horizontally on small screens.
- After a bulk retry, the jobs that could not be retried (payload not stored and no longer in `failed_jobs`) are reported.

Both the Inertia and the Blade interfaces get these actions, in English and French.

### Fixed

- Blade interface: the buttons placed in page titles (retry, delete) no longer take the title's font size.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change. If you published the Blade views of Meridian, republish them to get the new buttons.

## v1.2.0 - 2026-10-17

### Added

- **Blade interface in French**: every text of the Blade interface (`MERIDIAN_UI=blade`) is now translated, with the same translations as the Inertia interface; dates, numbers and relative times follow the visitor's language. A language switcher sits in the sidebar.
- **Light / dark / system theme for the Blade interface**, with a switcher in the sidebar, applied before the first paint.
- Both interfaces share the same settings: the theme kept in the browser and the language kept in a cookie, so switching interface keeps your choices.

### Fixed

- **Content-Security-Policy (Blade interface)**: the queue filter and the delete / retry confirmations used inline event handlers (`onchange`, `onsubmit`), which a strict CSP refuses even with a nonce. They are now handled by the nonce'd script, so the Blade interface fully works under a policy such as `script-src 'nonce-…' 'strict-dynamic' 'self'`.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change. If you published the Blade views of Meridian, republish them to get the translations and the theme switcher.

## v1.1.1 - 2026-10-17

### Fixed

- **Content-Security-Policy**: the dashboard now works under a strict, nonce-based CSP (for instance `script-src 'nonce-…' 'strict-dynamic' 'self'`). When the application sets a nonce with `Vite::useCspNonce()`, Meridian puts it on its scripts and styles and passes it to Inertia for the styles it injects, in both the Inertia and the Blade interfaces. Previously the scripts were blocked and the page stayed blank, unless Meridian's routes were exempted from the policy.

If you exempted Meridian's routes from your CSP to use v1.1.0, you can remove that exception.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change.

## v1.1.0 - 2026-10-17

### Added

- **New dashboard interface** built with React, Inertia and shadcn/ui (Tailwind CSS v4):
  - light, dark and system themes, kept in the browser;
  - English and French: the language follows the application locale and can be changed from the header (kept in a cookie); dates and numbers are formatted in the chosen language;
  - auto-refresh every `meridian.poll` seconds (« Live » switch in the header), confirmation dialogs for retry / cancel / delete, responsive layout.
- Its compiled assets ship with the package and are served by Meridian itself: nothing to install, build or publish in your application, which needs neither Node, React nor an Inertia setup of its own.
- Meridian uses its own root view, Inertia middleware and asset version: your application's Inertia middleware is excluded from Meridian's routes and its SSR server never renders Meridian's pages.
- `meridian.ui` (`MERIDIAN_UI`) and `meridian.locales` configuration keys.
- New dependency: `inertiajs/inertia-laravel` ^3.0.

### Changed

- The new interface is the default one. The URLs, route names, actions and JSON API are unchanged.

### Switching back to the previous interface

The Blade interface is still available. To keep it, set in your `.env`:

```dotenv
MERIDIAN_UI=blade
```

or `'ui' => 'blade'` in a published `config/meridian.php` (republish it with `php artisan vendor:publish --tag=meridian-config --force` to get the new keys), then `php artisan config:clear` if the configuration is cached.

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration. Applications still on Inertia v2 cannot install this version.

## v1.0.1 - 2026-10-17

### Fixed

- **MySQL / MariaDB**: the dashboard and the workload API (`/meridian/api/workload`) failed with a SQL syntax error, the workload query aliasing a column `delayed`, a reserved word there. The aliases are now suffixed (`pending_jobs`, `delayed_jobs`, `reserved_jobs`, `oldest_available`); the keys returned by `QueueRepository::workload()` and the API are unchanged. A test guards against reserved words used as aliases (the test suite runs on SQLite, which accepts them).

### Upgrade

```bash
composer update iso-doss/laravel-meridian
```

No migration nor configuration change.

## v1.0.0 - 2026-10-17

First release of Meridian: a Horizon-like dashboard and supervisor for Laravel **database** queues.

### Features

- **Job history**: every job pushed on a `database` queue is recorded from Laravel's queue events (queued, processing, completed, failed, released, retried) with tags, payload, exception, attempts, wait time and runtime.
- **Bulk / batch support**: jobs inserted without event (`Bus::batch()`, `Queue::bulk()`, `pushRaw()`, other applications) are imported from the `jobs` table.
- **Dashboard** (Blade, no front-end build): stats, throughput chart, workload per queue, supervisors and workers, jobs by status with search by class / tag / UUID, job detail with retry and cancel, batches, metrics per job and per queue.
- **JSON API**: stats, workload, jobs and job detail.
- **Worker heartbeats**: any `queue:work` process shows up in the dashboard, with or without Meridian's supervisor.
- **Supervisor**: `php artisan meridian` spawns and supervises `queue:work` processes with `auto`, `simple` or no balancing, plus `meridian:pause`, `meridian:continue`, `meridian:terminate` and `meridian:status`.
- **Metrics & maintenance**: `meridian:snapshot` and `meridian:prune`, registered automatically in the scheduler.
- **Long wait detection**: `LongWaitDetected` event and optional mail notification per `connection:queue`.
- **Configurable routes**: namespace, prefix, domain and middleware for the web pages and the API (`meridian.route.web` / `meridian.route.api`), protected by the `viewMeridian` gate.

### Requirements

- PHP 8.3+
- Laravel 12 or 13
- `pcntl` / `posix` for `php artisan meridian`

### Installation

```bash
composer require iso-doss/laravel-meridian
php artisan meridian:install
```
