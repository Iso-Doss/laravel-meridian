<?php

namespace IsoDoss\Meridian;

use Illuminate\Bus\Events\BatchDispatched;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Middleware as InertiaMiddleware;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Http\Middleware\Authorize;
use IsoDoss\Meridian\Http\Middleware\HandleInertiaRequests;
use IsoDoss\Meridian\Listeners\RecordJobLifecycle;
use IsoDoss\Meridian\Listeners\SendLongWaitNotification;
use IsoDoss\Meridian\Listeners\WorkerHeartbeat;
use IsoDoss\Meridian\Support\PendingJobsImporter;
use IsoDoss\Meridian\Support\Translator;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MeridianServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('meridian')
            ->hasConfigFile()
            ->hasViews('meridian')
            ->hasMigration('create_meridian_tables')
            ->publishesServiceProvider('MeridianServiceProvider')
            ->hasCommands([
                Console\MeridianCommand::class,
                Console\PauseCommand::class,
                Console\ContinueCommand::class,
                Console\TerminateCommand::class,
                Console\StatusCommand::class,
                Console\SnapshotCommand::class,
                Console\PruneCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->publish('provider')
                    ->endWith(fn () => $this->registerApplicationProvider())
                    ->askToRunMigrations();
            });
    }

    /**
     * Add App\Providers\MeridianServiceProvider to bootstrap/providers.php.
     */
    protected function registerApplicationProvider(): void
    {
        $namespace = rtrim($this->app->getNamespace(), '\\');
        $path = app_path('Providers/MeridianServiceProvider.php');

        if (file_exists($path)) {
            file_put_contents($path, str_replace('namespace App\\Providers;', "namespace {$namespace}\\Providers;", (string) file_get_contents($path)));
        }

        ServiceProvider::addProviderToBootstrapFile($namespace.'\\Providers\\MeridianServiceProvider');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(WorkerHeartbeat::class);
        $this->app->singleton(RecordJobLifecycle::class);
    }

    public function packageBooted(): void
    {
        Event::subscribe(WorkerHeartbeat::class);
        Event::subscribe(RecordJobLifecycle::class);
        Event::listen(LongWaitDetected::class, SendLongWaitNotification::class);

        // Batched jobs are inserted with Queue::bulk(), which fires no JobQueued event.
        Event::listen(BatchDispatched::class, function () {
            app(PendingJobsImporter::class)->import();
        });

        $this->registerRoutes();
        $this->registerSchedule();
        $this->shareTranslationsWithViews();
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group($this->routeGroup('api', 'meridian.api.'), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });

        Route::group($this->routeGroup('web', 'meridian.', [HandleInertiaRequests::class]), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        $web = (array) config('meridian.route.web', []);

        Route::group(array_filter([
            'prefix' => trim((string) ($web['prefix'] ?? ''), '/'),
            'domain' => $web['domain'] ?? null,
            'as' => 'meridian.',
        ], fn ($value) => $value !== null && $value !== ''), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/assets.php');
        });
    }

    /**
     * Build the route group attributes from the "meridian.route.{api|web}" config.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, class-string>  $extraMiddleware
     * @return array<string, mixed>
     */
    protected function routeGroup(string $type, string $name, array $extraMiddleware = []): array
    {
        $config = (array) config("meridian.route.{$type}", []);
        $middleware = (array) ($config['middleware'] ?? []);

        return array_filter([
            'namespace' => $config['namespace'] ?? null,
            'prefix' => trim((string) ($config['prefix'] ?? ''), '/'),
            'domain' => $config['domain'] ?? null,
            'middleware' => array_merge($middleware, [Authorize::class], $extraMiddleware),
            'excluded_middleware' => $this->applicationInertiaMiddleware($middleware),
            'as' => $name,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * The application's own Inertia middleware (usually appended to the "web"
     * group) must not run on Meridian's routes: Meridian has its own.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, class-string>
     */
    protected function applicationInertiaMiddleware(array $middleware): array
    {
        if (! class_exists(InertiaMiddleware::class)) {
            return [];
        }

        $groups = $this->app['router']->getMiddlewareGroups();

        return collect($middleware)
            ->flatMap(fn ($entry) => is_string($entry) && isset($groups[$entry]) ? $groups[$entry] : [$entry])
            ->filter(fn ($entry) => is_string($entry)
                && class_exists($entry)
                && is_subclass_of($entry, InertiaMiddleware::class)
                && $entry !== HandleInertiaRequests::class)
            ->values()
            ->all();
    }

    /**
     * The Blade interface translates its texts with $t('English text').
     */
    protected function shareTranslationsWithViews(): void
    {
        View::composer('meridian::*', function ($view) {
            $locale = Translator::locale(request());

            $view->with('locale', $locale)
                ->with('languages', Translator::languages())
                ->with('t', fn (string $key, array $replace = []) => Translator::get($key, $replace, $locale));
        });
    }

    protected function registerSchedule(): void
    {
        if (! config('meridian.schedule', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('meridian:snapshot')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('meridian:prune')->hourly()->withoutOverlapping();
        });
    }
}
