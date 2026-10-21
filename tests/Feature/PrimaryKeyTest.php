<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\MonitoredTagRepository;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Support\PrimaryKey;
use IsoDoss\Meridian\Tests\Fixtures\FailingJob;
use IsoDoss\Meridian\Tests\Fixtures\SuccessfulJob;

const MERIDIAN_TABLES = [
    'meridian_jobs', 'meridian_workers', 'meridian_supervisors', 'meridian_metrics', 'meridian_monitored_tags',
    'meridian_job_retries', 'meridian_audit_logs', 'meridian_schedule_tasks', 'meridian_schedule_runs',
];

const MERIDIAN_STUBS = [
    'create_meridian_tables', 'create_meridian_monitored_tags_table', 'create_meridian_job_retries_table',
    'add_fingerprint_and_progress_to_meridian_jobs_table', 'create_meridian_audit_logs_table',
    'create_meridian_schedule_tables', 'add_cancel_requested_at_to_meridian_jobs_table',
];

/**
 * Drop Meridian's tables and run the migrations again with this key type.
 */
function remigrate(string $type): void
{
    foreach (MERIDIAN_TABLES as $table) {
        Schema::dropIfExists($table);
    }

    config()->set('meridian.primary_key', $type);
    Migrations::flush();
    runMigrations();
}

function runMigrations(): void
{
    foreach (MERIDIAN_STUBS as $stub) {
        (include __DIR__."/../../database/migrations/{$stub}.php.stub")->up();
    }
}

/**
 * Record a job, a failure, a metric, an audit entry and a monitored tag.
 */
function recordEverything(): void
{
    SuccessfulJob::dispatch();
    FailingJob::dispatch();
    test()->work(2);
    app(MetricsRepository::class)->snapshot(time() - 300, time() + 1);
    app(AuditLogRepository::class)->record('queue.cleared', 'database:default');
    app(MonitoredTagRepository::class)->monitor('billing');
}

it('uses UUIDs by default', function () {
    expect(config('meridian.primary_key'))->toBe('uuid')
        ->and(PrimaryKey::of('meridian_jobs'))->toBe('uuid');

    recordEverything();

    foreach (['meridian_jobs', 'meridian_metrics', 'meridian_audit_logs', 'meridian_monitored_tags'] as $table) {
        $id = Meridian::db()->table($table)->value('id');

        expect(Str::isUuid($id))->toBeTrue("{$table}: {$id}");
    }

    // UUIDv7 grow with time: the latest jobs come first.
    $this->get('/meridian/jobs/failed')->assertOk();
    expect(MonitoredJob::orderByDesc('id')->value('name'))->toBe(FailingJob::class)
        ->and(MonitoredJob::where('name', FailingJob::class)->first()->getKey())->toBeString();
});

it('can use ULIDs', function () {
    remigrate('ulid');
    recordEverything();

    expect(PrimaryKey::of('meridian_jobs'))->toBe('ulid')
        ->and(Str::isUlid(MonitoredJob::value('id')))->toBeTrue()
        ->and(MonitoredJob::count())->toBe(2);
});

it('can use auto-incrementing integers', function () {
    remigrate('id');
    recordEverything();

    expect(PrimaryKey::of('meridian_jobs'))->toBeNull()
        ->and(MonitoredJob::orderBy('id')->pluck('id')->all())->toBe([1, 2])
        ->and((int) Meridian::db()->table('meridian_metrics')->min('id'))->toBe(1);
});

it('keeps writing integer keys to the tables created before the option', function () {
    remigrate('id');
    config()->set('meridian.primary_key', 'uuid');
    Migrations::flush();

    recordEverything();

    expect(PrimaryKey::of('meridian_jobs'))->toBeNull()
        ->and(MonitoredJob::orderBy('id')->pluck('id')->all())->toBe([1, 2]);
});

it('records the scheduled task runs and opens their page with any key', function (string $type) {
    remigrate($type);
    $this->artisan('meridian:snapshot')->assertSuccessful();

    $repository = app(ScheduleRepository::class);
    $event = app(Schedule::class)->command('inspire')->daily();
    $repository->sync();
    $run = $repository->started($event);
    $repository->update($run, ['status' => 'success', 'finished_at' => time()]);

    $task = collect($repository->tasks())->firstWhere('name', 'inspire');

    expect($task['last_run']['status'])->toBe('success')
        ->and($type === 'id' ? is_int($task['id']) : is_string($task['id']))->toBeTrue();

    $this->get("/meridian/schedule/{$task['id']}")->assertInertia(fn (Assert $page) => $page
        ->component('Schedule/Show')
        ->where('task.name', 'inspire'));

    $this->get('/meridian/schedule/123abc')->assertNotFound();
})->with(['uuid', 'ulid', 'id']);

it('groups the failures and retries them with UUID keys', function () {
    FailingJob::dispatch();
    FailingJob::dispatch();
    $this->work(2);

    $this->get('/meridian/errors')->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.count', 2)
        ->where('groups.0.sample', MonitoredJob::orderByDesc('id')->value('uuid')));

    $this->post('/meridian/jobs/retry')->assertRedirect();

    expect(MonitoredJob::where('status', MonitoredJob::FAILED)->count())->toBe(0);
});

it('can run the migrations again without error', function () {
    runMigrations();
    runMigrations();

    expect(Schema::hasColumns('meridian_jobs', ['fingerprint', 'progress', 'progress_message', 'cancel_requested_at']))->toBeTrue();
});

it('adds only the missing columns', function () {
    Schema::table('meridian_jobs', function (Blueprint $table) {
        $table->dropColumn('progress_message');
    });

    (include __DIR__.'/../../database/migrations/add_fingerprint_and_progress_to_meridian_jobs_table.php.stub')->up();

    expect(Schema::hasColumn('meridian_jobs', 'progress_message'))->toBeTrue();
});

it('rolls back the migrations', function () {
    foreach (array_reverse(MERIDIAN_STUBS) as $stub) {
        (include __DIR__."/../../database/migrations/{$stub}.php.stub")->down();
    }

    foreach (MERIDIAN_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }
});
