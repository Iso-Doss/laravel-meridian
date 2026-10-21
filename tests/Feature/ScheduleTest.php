<?php

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use IsoDoss\Meridian\Events\ScheduleTaskFailed;
use IsoDoss\Meridian\Events\ScheduleTaskMissed;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Repositories\ScheduleRepository;

beforeEach(function () {
    // Leave out the tasks Meridian schedules itself.
    (fn () => $this->events = [])->call(app(Schedule::class));
});

function scheduledRuns(): array
{
    return Meridian::table('schedule_runs')->orderBy('id')->get()
        ->map(fn ($run) => [$run->task, $run->status])
        ->all();
}

function commandEvent(string $command, bool $background = false): ScheduledEvent
{
    $event = new ScheduledEvent(app(CacheEventMutex::class), "'/usr/bin/php' 'artisan' {$command}");
    $event->runInBackground = $background;

    return $event;
}

it('records the runs of the scheduled tasks', function () {
    Event::fake([ScheduleTaskFailed::class]);

    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->name('refresh-rates')->everyMinute();
    $schedule->call(fn () => throw new RuntimeException('Rates API down'))->name('sync-rates')->everyMinute();
    $schedule->call(fn () => null)->name('weekend-report')->everyMinute()->when(fn () => false);

    $this->artisan('schedule:run');

    expect(scheduledRuns())->toBe([
        ['refresh-rates', 'success'],
        ['sync-rates', 'failed'],
        ['weekend-report', 'skipped'],
    ])
        ->and(Meridian::table('schedule_runs')->where('task', 'sync-rates')->value('exception'))->toContain('Rates API down')
        ->and(Meridian::table('schedule_tasks')->pluck('name')->sort()->values()->all())->toBe(['refresh-rates', 'sync-rates', 'weekend-report']);

    Event::assertDispatched(ScheduleTaskFailed::class, fn (ScheduleTaskFailed $event) => $event->task === 'sync-rates'
        && str_starts_with($event->error, 'RuntimeException: Rates API down'));
});

it('names the tasks like the developers read them', function () {
    expect(ScheduleRepository::name(commandEvent('meridian:snapshot')))->toBe('meridian:snapshot')
        ->and(ScheduleRepository::name(commandEvent('reports:send --daily')->name('Daily reports')))->toBe('Daily reports')
        ->and(ScheduleRepository::name(new ScheduledEvent(app(CacheEventMutex::class), 'rm -rf /tmp/cache')))->toBe('rm -rf /tmp/cache');
});

it('records the runtime and the exit code of a command', function () {
    $task = commandEvent('reports:send');

    event(new ScheduledTaskStarting($task));
    $task->exitCode = 0;
    event(new ScheduledTaskFinished($task, 1.53));

    $run = Meridian::table('schedule_runs')->sole();
    expect([$run->status, (int) $run->runtime, (int) $run->exit_code])->toBe(['success', 1530, 0]);
});

it('finishes the tasks run in background', function () {
    Event::fake([ScheduleTaskFailed::class]);
    $task = commandEvent('exports:build', background: true);

    event(new ScheduledTaskStarting($task));
    event(new ScheduledTaskFinished($task, 0.01));
    expect(scheduledRuns())->toBe([['exports:build', 'running']]);

    $task->exitCode = 1;
    event(new ScheduledBackgroundTaskFinished($task));

    expect(scheduledRuns())->toBe([['exports:build', 'failed']]);
    Event::assertDispatched(ScheduleTaskFailed::class, fn (ScheduleTaskFailed $event) => $event->error === 'Exit code 1');
});

it('detects the missed runs', function () {
    Event::fake([ScheduleTaskMissed::class]);

    Meridian::table('schedule_tasks')->insert([
        ['name' => 'every-minute', 'expression' => '* * * * *', 'first_seen_at' => time() - 3600, 'last_seen_at' => time()],
        ['name' => 'just-added', 'expression' => '* * * * *', 'first_seen_at' => time(), 'last_seen_at' => time()],
        ['name' => 'healthy', 'expression' => '* * * * *', 'first_seen_at' => time() - 3600, 'last_seen_at' => time()],
    ]);
    Meridian::table('schedule_runs')->insert(['task' => 'healthy', 'status' => 'success', 'started_at' => time() - 30, 'finished_at' => time() - 29]);
    config()->set('meridian.schedule_grace', 1);

    $tasks = collect(app(ScheduleRepository::class)->tasks())->keyBy('name');

    expect($tasks['every-minute']['health'])->toBe('missed')
        ->and($tasks['just-added']['health'])->toBe('never')
        ->and($tasks['healthy']['health'])->toBe('success');

    $this->artisan('meridian:snapshot')->expectsOutputToContain('Scheduled task [every-minute] did not run')->assertSuccessful();
    $this->artisan('meridian:snapshot')->assertSuccessful();

    Event::assertDispatchedTimes(ScheduleTaskMissed::class, 1);
});

it('keeps the definitions of the environment from meridian:snapshot', function () {
    $schedule = app(Schedule::class);
    $schedule->command('meridian:prune')->hourly()->withoutOverlapping()->onOneServer();
    $schedule->call(fn () => null)->name('production-only')->daily()->environments('production');

    $this->artisan('meridian:snapshot')->assertSuccessful();

    $task = Meridian::table('schedule_tasks')->where('name', 'like', '%meridian:prune%')->sole();

    expect($task->expression)->toBe('0 * * * *')
        ->and((bool) $task->without_overlapping)->toBeTrue()
        ->and((bool) $task->on_one_server)->toBeTrue()
        ->and(Meridian::table('schedule_tasks')->where('name', 'production-only')->exists())->toBeFalse();
});

it('shows the scheduled tasks and their runs', function () {
    $schedule = app(Schedule::class);
    $schedule->call(fn () => null)->name('refresh-rates')->everyFiveMinutes();
    $this->artisan('meridian:snapshot');
    $id = Meridian::table('schedule_tasks')->value('id');

    $this->get('/meridian/schedule')->assertInertia(fn (Assert $page) => $page
        ->component('Schedule/Index')
        ->where('tasks.0.name', 'refresh-rates')
        ->where('tasks.0.expression', '*/5 * * * *')
        ->where('tasks.0.url', route('meridian.schedule.show', $id))
    );

    $this->get('/meridian/schedule/'.$id)->assertInertia(fn (Assert $page) => $page
        ->component('Schedule/Show')
        ->where('task.name', 'refresh-rates')
        ->has('runs.data')
    );

    $this->get('/meridian/schedule/999')->assertNotFound();
    $this->getJson('/meridian/api/schedule')->assertOk()->assertJsonPath('0.name', 'refresh-rates');

    config()->set('meridian.ui', 'blade');

    $this->get('/meridian/schedule')->assertOk()->assertSee('refresh-rates')->assertSee('*/5 * * * *');
    $this->get('/meridian/schedule/'.$id)->assertOk()->assertSee('refresh-rates');
});

it('prunes the old runs', function () {
    Meridian::table('schedule_runs')->insert([
        ['task' => 'old', 'status' => 'success', 'started_at' => time() - 8 * 86400],
        ['task' => 'recent', 'status' => 'success', 'started_at' => time() - 60],
    ]);

    $this->artisan('meridian:prune')->expectsOutputToContain('1 scheduled task runs')->assertSuccessful();

    expect(scheduledRuns())->toBe([['recent', 'success']]);
});
