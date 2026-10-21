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
