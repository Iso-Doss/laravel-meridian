<?php

namespace IsoDoss\Meridian\Listeners;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use IsoDoss\Meridian\Events\ScheduleTaskFailed;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Support\PrimaryKey;
use Throwable;

/**
 * Records the runs of the scheduled tasks (schedule:run).
 */
class RecordScheduledTasks
{
    /**
     * The run id of the tasks started by this process.
     *
     * @var array<int, int|string>
     */
    protected array $runs = [];

    protected bool $synced = false;

    public function __construct(protected ScheduleRepository $schedule) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ScheduledTaskStarting::class => 'starting',
            ScheduledTaskFinished::class => 'finished',
            ScheduledTaskFailed::class => 'failed',
            ScheduledTaskSkipped::class => 'skipped',
            ScheduledBackgroundTaskFinished::class => 'backgroundFinished',
        ];
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $this->safely(function () use ($event) {
            // Keep the definitions known by the dashboard up to date, once per run.
            if (! $this->synced) {
                $this->synced = true;
                $this->schedule->sync();
            }

            $this->runs[spl_object_id($event->task)] = $this->schedule->started($event->task);
        });
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $run = $this->runs[spl_object_id($event->task)] ?? null;

        // A task run in background is finished by schedule:finish.
        if ($run === null || $event->task->runInBackground) {
            return;
        }

        $this->safely(function () use ($event, $run) {
            $failed = (int) $event->task->exitCode !== 0;

            $this->schedule->update($run, [
                'status' => $failed ? ScheduleRepository::FAILED : ScheduleRepository::SUCCEEDED,
                'exit_code' => $event->task->exitCode === null ? null : (int) $event->task->exitCode,
                'runtime' => (int) round($event->runtime * 1000),
                'finished_at' => time(),
            ]);

            if (! $failed) {
                unset($this->runs[spl_object_id($event->task)]);
            }
        });
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->safely(function () use ($event) {
            $run = $this->runs[spl_object_id($event->task)] ?? $this->schedule->started($event->task);
            unset($this->runs[spl_object_id($event->task)]);

            $this->schedule->update($run, [
                'status' => ScheduleRepository::FAILED,
                'exit_code' => $event->task->exitCode === null ? null : (int) $event->task->exitCode,
                // ScheduledTaskFailed only carries the runtime since Laravel 12.x.
                'runtime' => ($runtime = get_object_vars($event)['runtime'] ?? null) === null ? null : (int) round($runtime * 1000),
                'exception' => (string) $event->exception,
                'finished_at' => time(),
            ]);

            $this->alert($event->task, (string) $event->exception);
        });
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $this->safely(fn () => $this->schedule->skipped($event->task));
    }

    public function backgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $this->safely(function () use ($event) {
            $run = $this->schedule->lastRunning($event->task);

            if (! $run) {
                return;
            }

            $failed = (int) $event->task->exitCode !== 0;

            $this->schedule->update(PrimaryKey::cast($run->id), [
                'status' => $failed ? ScheduleRepository::FAILED : ScheduleRepository::SUCCEEDED,
                'exit_code' => $event->task->exitCode === null ? null : (int) $event->task->exitCode,
                'runtime' => max(0, time() - (int) $run->started_at) * 1000,
                'finished_at' => time(),
            ]);

            if ($failed) {
                $this->alert($event->task, "Exit code {$event->task->exitCode}");
            }
        });
    }

    /**
     * Fire the failure alert, at most once per cooldown for the same task.
     */
    protected function alert(Event $task, string $error): void
    {
        if (! config('meridian.alerts.schedule_failures', true)) {
            return;
        }

        $name = ScheduleRepository::name($task);

        if (Cache::add('meridian:alert:schedule-failed:'.sha1($name), true, max(1, (int) config('meridian.notifications.cooldown', 5)) * 60)) {
            event(new ScheduleTaskFailed($name, strtok($error, "\n") ?: $error));
        }
    }

    /**
     * Monitoring must never break the scheduler.
     */
    protected function safely(callable $callback): void
    {
        if (! Migrations::hasSchedule()) {
            return;
        }

        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
