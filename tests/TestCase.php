<?php

namespace IsoDoss\Meridian\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\MeridianServiceProvider;
use IsoDoss\Meridian\Support\Migrations;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Meridian::$authUsing = fn () => true;
        Meridian::$manageUsing = null;
        Meridian::$editUsing = null;
        Meridian::$notificationRoutes = [];
        Migrations::flush();

        $this->createQueueTables();

        (include __DIR__.'/../database/migrations/create_meridian_tables.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_meridian_monitored_tags_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_meridian_job_retries_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/add_fingerprint_and_progress_to_meridian_jobs_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_meridian_audit_logs_table.php.stub')->up();
        (include __DIR__.'/../database/migrations/create_meridian_schedule_tables.php.stub')->up();
        (include __DIR__.'/../database/migrations/add_cancel_requested_at_to_meridian_jobs_table.php.stub')->up();
    }

    protected function getPackageProviders($app)
    {
        return [InertiaServiceProvider::class, MeridianServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('database.default', 'testing');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
        config()->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'testing',
            'table' => 'failed_jobs',
        ]);
        config()->set('queue.batching', ['database' => 'testing', 'table' => 'job_batches']);

        // assertInertia() checks that every rendered page component exists.
        config()->set('inertia.pages.paths', [__DIR__.'/../resources/js/pages']);
        config()->set('inertia.pages.extensions', ['tsx']);
    }

    protected function createQueueTables(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });
    }

    /**
     * Process the next job like a worker would.
     */
    protected function work(int $times = 1, array $options = []): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->artisan('queue:work', array_merge(['connection' => 'database', '--once' => true, '--sleep' => 0], $options));
        }
    }
}
