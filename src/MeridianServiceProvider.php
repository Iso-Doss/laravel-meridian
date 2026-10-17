<?php

namespace IsoDoss\Meridian;

use Illuminate\Bus\Events\BatchDispatched;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Http\Middleware\Authorize;
use IsoDoss\Meridian\Listeners\RecordJobLifecycle;
use IsoDoss\Meridian\Listeners\SendLongWaitNotification;
use IsoDoss\Meridian\Listeners\WorkerHeartbeat;
use IsoDoss\Meridian\Support\PendingJobsImporter;
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
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'domain' => config('meridian.domain'),
            'prefix' => config('meridian.path', 'meridian'),
            'middleware' => array_merge((array) config('meridian.middleware', ['web']), [Authorize::class]),
            'as' => 'meridian.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
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
