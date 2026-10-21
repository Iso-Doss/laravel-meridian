# Upgrading

Every release and its upgrade notes are listed in the [changelog](https://github.com/Iso-Doss/laravel-meridian/blob/main/CHANGELOG.md). To upgrade:

```bash
composer update iso-doss/laravel-meridian
php artisan migrate
```

`php artisan migrate` is only needed by the releases that ship a migration (see below). Every migration checks that its tables and columns do not exist yet, so running it again is harmless. Until a migration has run, the feature it brings stays hidden and the rest of Meridian keeps working.

## Notes by version

| Version | Migration | Notes |
| --- | --- | --- |
| v1.11.0 | — | New installations use UUID primary keys (`MERIDIAN_PRIMARY_KEY`). Existing tables keep their integer keys, nothing to change. |
| v1.10.0 | — | Add the new languages to `locales` if you published the configuration. |
| v1.9.0 | Yes | Cancellation of running jobs (`cancel_requested_at` column). |
| v1.8.0 | Yes | Scheduled tasks monitoring (`meridian_schedule_tasks`, `meridian_schedule_runs`). |
| v1.7.0 | Yes | Error groups and job progress (new `meridian_jobs` columns), audit log (`meridian_audit_logs`). |

If you published the migrations of an earlier release, publish the new ones with `php artisan vendor:publish --tag=meridian-migrations`, then run `php artisan migrate`. The migrations you already published keep their file and are left as they are; only the new ones are added.

## The interface

Since v1.1.0 the React / Inertia interface is the default one; set `MERIDIAN_UI=blade` to keep the previous one. If you published the Blade views, republish them after upgrading.
