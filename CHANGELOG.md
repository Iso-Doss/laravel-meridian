# Changelog

All notable changes to `laravel-meridian` will be documented in this file.

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