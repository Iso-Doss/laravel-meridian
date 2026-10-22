<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Tests\Fixtures\Operator;
use IsoDoss\Meridian\Tests\Fixtures\ParentJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

beforeEach(function () {
    (fn () => $this->events = [])->call(app(Schedule::class));
});

it('records the HTTP request that dispatched a job, without its query string', function () {
    Route::post('orders', function () {
        SuccessfulJob::dispatch();

        return 'ok';
    })->name('orders.store');
    $this->actingAs(Operator::create(['email' => 'ada@example.com']));

    $this->post('/orders?token=s3cret')->assertOk();

    expect(MonitoredJob::sole()->only(['origin_type', 'origin', 'origin_user', 'parent_uuid']))->toBe([
        'origin_type' => 'http',
        'origin' => 'POST /orders (orders.store)',
        'origin_user' => 'ada@example.com',
        'parent_uuid' => null,
    ]);
});

it('records the artisan command, without its options', function () {
    Artisan::command('reports:send {--password=}', function () {
        SuccessfulJob::dispatch();
    });

    // As "php artisan" does: the console kernel fires CommandStarting.
    app(Kernel::class)->rerouteSymfonyCommandEvents();
    $this->artisan('reports:send', ['--password' => 'hunter2'])->assertSuccessful();

    expect(MonitoredJob::sole()->only(['origin_type', 'origin']))->toBe(['origin_type' => 'console', 'origin' => 'artisan reports:send']);
});

it('records the scheduled task', function () {
    app(Schedule::class)->call(function () {
        SuccessfulJob::dispatch();
    })->name('nightly-sync')->everyMinute();

    $this->artisan('schedule:run');

    expect(MonitoredJob::sole()->only(['origin_type', 'origin']))->toBe(['origin_type' => 'schedule', 'origin' => 'nightly-sync']);
});

it('links the jobs to the job that dispatched them', function () {
    ParentJob::dispatch();
    $this->work();

    $parent = MonitoredJob::where('name', ParentJob::class)->sole();
    $children = MonitoredJob::where('name', SuccessfulJob::class)->get();

    expect($children)->toHaveCount(2)
        ->and($children->pluck('parent_uuid')->unique()->all())->toBe([$parent->uuid])
        ->and($children->first()->origin_type)->toBe('job')
        ->and($children->first()->origin)->toBe(ParentJob::class);

    $this->get('/meridian/jobs/'.$parent->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.children.count', 2)
        ->where('job.children.jobs.0.name', SuccessfulJob::class));

    $this->get('/meridian/jobs/'.$children->first()->uuid)->assertInertia(fn (Assert $page) => $page
        ->where('job.origin.type', 'job')
        ->where('job.origin.parent.uuid', $parent->uuid)
        ->where('job.origin.parent.status', 'completed'));

    // The job run afterwards by the same worker has no parent.
    $this->work();
    expect(MonitoredJob::where('name', SuccessfulJob::class)->whereNull('parent_uuid')->count())->toBe(0);
});

it('shows the origin in the Blade interface', function () {
    config()->set('meridian.ui', 'blade');
    ParentJob::dispatch();
    $this->work();

    $parent = MonitoredJob::where('name', ParentJob::class)->sole();
    $child = MonitoredJob::where('name', SuccessfulJob::class)->first();

    $this->get('/meridian/jobs/'.$parent->uuid)->assertOk()->assertSee('Dispatched jobs')->assertSee('2 job(s) dispatched by this job');
    $this->get('/meridian/jobs/'.$child->uuid)->assertOk()->assertSee('Dispatched by')->assertSee(route('meridian.jobs.show', $parent->uuid), false);
});

it('keeps working before the migration of v1.12.0', function () {
    Schema::table('meridian_jobs', function ($table) {
        $table->dropIndex(['parent_uuid']);
        $table->dropColumn(['origin_type', 'origin', 'origin_user', 'parent_uuid']);
    });
    Migrations::flush();

    ParentJob::dispatch();
    $this->work();

    $this->get('/meridian/jobs/'.MonitoredJob::where('name', ParentJob::class)->value('uuid'))->assertInertia(fn (Assert $page) => $page
        ->where('job.origin', null)
        ->where('job.children.count', 0));
});
