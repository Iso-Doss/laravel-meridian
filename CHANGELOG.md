# Changelog

All notable changes to `laravel-meridian` will be documented in this file.

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
