# Installation

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

### Primary keys

Meridian's tables use UUID primary keys by default (UUIDv7, which grow with time, so sorting by `id` keeps the insertion order). To use ULIDs or auto-incrementing integers instead, set the type **before running the migrations**:

```ini
MERIDIAN_PRIMARY_KEY=uuid   # default; or "ulid", or "id" for auto-incrementing integers
```

The migrations check `Schema::hasTable()` and `Schema::hasColumn()` before creating anything, so running them again is harmless. Tables that already exist keep their key type: Meridian reads the type of each table's `id` column and writes to it accordingly, so an application installed before this option keeps its integer keys without any change.
