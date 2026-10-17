# Changelog

All notable changes to `laravel-meridian` will be documented in this file.

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
