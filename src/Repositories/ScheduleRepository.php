<?php

namespace IsoDoss\Meridian\Repositories;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Support\Migrations;
use Throwable;

/**
 * The scheduled tasks of the application (meridian_schedule_tasks) and
 * their runs (meridian_schedule_runs).
 */
class ScheduleRepository
{
    public const RUNNING = 'running';

    public const SUCCEEDED = 'success';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /**
     * The name identifying a scheduled task: its description (->name()),
     * else its command without the PHP binary, else its callback.
     */
    public static function name(Event $event): string
    {
        if (is_string($event->description) && $event->description !== '') {
            $name = $event->description;
        } elseif ($event instanceof CallbackEvent) {
            $name = $event->getSummaryForDisplay();
        } else {
            $command = (string) $event->command;
            $name = str_contains($command, "'artisan' ") ? Str::after($command, "'artisan' ") : $command;
        }

        return Str::limit(trim($name), 250, '…');
    }

    /**
     * Store the definition of the tasks scheduled in this environment, so
     * that the dashboard knows them (the schedule is only defined in console).
     */
    public function sync(?Schedule $schedule = null): int
    {
        if (! Migrations::hasSchedule()) {
            return 0;
        }

        $schedule ??= app(Schedule::class);
        $now = time();
        $environment = app()->environment();
        $rows = [];

        foreach ($schedule->events() as $event) {
            if (! $event->runsInEnvironment($environment)) {
                continue;
            }

            try {
                $next = $event->nextRunDate()->getTimestamp();
            } catch (Throwable) {
                $next = null;
            }

            $rows[static::name($event)] = [
                'name' => static::name($event),
                'expression' => $event->getExpression(),
                'timezone' => $this->timezone($event),
                'repeat_seconds' => $event->repeatSeconds,
                'background' => (bool) $event->runInBackground,
                'without_overlapping' => (bool) $event->withoutOverlapping,
                'on_one_server' => (bool) $event->onOneServer,
                'next_run_at' => $next,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ];
        }

        if ($rows !== []) {
            Meridian::table('schedule_tasks')->upsert(array_values($rows), ['name'], [
                'expression', 'timezone', 'repeat_seconds', 'background', 'without_overlapping', 'on_one_server', 'next_run_at', 'last_seen_at',
            ]);
        }

        return count($rows);
    }

    /**
     * The timezone of a task, when it differs from the application's.
     */
    protected function timezone(Event $event): ?string
    {
        $timezone = $event->timezone instanceof \DateTimeZone ? $event->timezone->getName() : $event->timezone;

        return is_string($timezone) && $timezone !== config('app.timezone') ? $timezone : null;
    }

    /**
     * @return int The id of the run.
     */
    public function started(Event $event): int
    {
        return (int) Meridian::table('schedule_runs')->insertGetId([
            'task' => static::name($event),
            'status' => self::RUNNING,
            'host' => Meridian::hostname(),
            'started_at' => time(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(int $run, array $values): void
    {
        Meridian::table('schedule_runs')->where('id', $run)->update($values);
    }

    /**
     * The last run of a task still running, for the tasks run in background
     * (finished by another process).
     */
    public function lastRunning(Event $event): ?object
    {
        return Meridian::table('schedule_runs')
            ->where('task', static::name($event))
            ->where('status', self::RUNNING)
            ->orderByDesc('id')
            ->first();
    }

    public function skipped(Event $event): void
    {
        Meridian::table('schedule_runs')->insert([
            'task' => static::name($event),
            'status' => self::SKIPPED,
            'host' => Meridian::hostname(),
            'started_at' => time(),
            'finished_at' => time(),
        ]);
    }

    /**
     * The known tasks with their last runs and whether their last due run was missed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tasks(): array
    {
        if (! Migrations::hasSchedule()) {
            return [];
        }

        $tasks = Meridian::table('schedule_tasks')
            ->where('last_seen_at', '>=', time() - 2 * 86400)
            ->orderBy('name')
            ->get();

        if ($tasks->isEmpty()) {
            return [];
        }

        $runs = Meridian::table('schedule_runs')
            ->whereIn('task', $tasks->pluck('name'))
            ->where('started_at', '>=', time() - 7 * 86400)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get(['id', 'task', 'status', 'exit_code', 'runtime', 'started_at', 'finished_at'])
            ->groupBy('task');

        return $tasks->map(function (object $task) use ($runs) {
            $history = collect($runs[$task->name] ?? [])->values();
            $last = $history->firstWhere('status', '!=', self::SKIPPED) ?? $history->first();
            $missed = $this->missedRun($task, $history->first());

            return [
                'id' => (int) $task->id,
                'name' => (string) $task->name,
                'expression' => (string) $task->expression,
                'timezone' => $task->timezone,
                'repeat_seconds' => $task->repeat_seconds === null ? null : (int) $task->repeat_seconds,
                'background' => (bool) $task->background,
                'without_overlapping' => (bool) $task->without_overlapping,
                'on_one_server' => (bool) $task->on_one_server,
                'next_run_at' => $this->nextRun($task),
                'last_run' => $last ? $this->present($last) : null,
                'recent' => $history->take(10)->map(fn (object $run) => $this->present($run))->all(),
                'missed_at' => $missed,
                'health' => match (true) {
                    $missed !== null => 'missed',
                    $last === null => 'never',
                    default => $last->status,
                },
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function task(int $id): ?array
    {
        $task = collect($this->tasks())->firstWhere('id', $id);

        if ($task) {
            return $task;
        }

        $row = Migrations::hasSchedule() ? Meridian::table('schedule_tasks')->where('id', $id)->first() : null;

        return $row ? ['id' => (int) $row->id, 'name' => (string) $row->name, 'expression' => (string) $row->expression, 'timezone' => $row->timezone, 'repeat_seconds' => null, 'background' => (bool) $row->background, 'without_overlapping' => (bool) $row->without_overlapping, 'on_one_server' => (bool) $row->on_one_server, 'next_run_at' => null, 'last_run' => null, 'recent' => [], 'missed_at' => null, 'health' => 'never'] : null;
    }

    public function runs(string $task, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = Meridian::table('schedule_runs')->where('task', $task)->orderByDesc('started_at')->orderByDesc('id')->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map(fn ($run) => $this->present($run, details: true)));

        return $paginator;
    }

    /**
     * The tasks whose last due run was missed.
     *
     * @return array<int, array{name: string, due_at: int}>
     */
    public function missed(): array
    {
        return collect($this->tasks())
            ->filter(fn (array $task) => $task['missed_at'] !== null)
            ->map(fn (array $task) => ['name' => $task['name'], 'due_at' => $task['missed_at']])
            ->values()
            ->all();
    }

    public function prune(int $before): int
    {
        if (! Migrations::hasSchedule()) {
            return 0;
        }

        Meridian::table('schedule_tasks')->where('last_seen_at', '<', time() - 7 * 86400)->delete();

        return Meridian::table('schedule_runs')->where('started_at', '<', $before)->delete();
    }

    /**
     * When the last run due before the grace period did not start, the
     * time it was due.
     */
    protected function missedRun(object $task, ?object $lastRun): ?int
    {
        $grace = max(1, (int) config('meridian.schedule_grace', 5)) * 60;

        try {
            $due = (new CronExpression((string) $task->expression))
                ->getPreviousRunDate(Carbon::now()->subSeconds($grace), 0, true, $task->timezone ?: config('app.timezone'))
                ->getTimestamp();
        } catch (Throwable) {
            return null;
        }

        // Due before Meridian first saw the task: unknown.
        if ($due < (int) $task->first_seen_at) {
            return null;
        }

        return $lastRun && (int) $lastRun->started_at >= $due - 60 ? null : $due;
    }

    protected function nextRun(object $task): ?int
    {
        try {
            return (new CronExpression((string) $task->expression))
                ->getNextRunDate(Carbon::now(), 0, false, $task->timezone ?: config('app.timezone'))
                ->getTimestamp();
        } catch (Throwable) {
            return $task->next_run_at === null ? null : (int) $task->next_run_at;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(object $run, bool $details = false): array
    {
        return array_merge([
            'status' => (string) $run->status,
            'exit_code' => $run->exit_code === null ? null : (int) $run->exit_code,
            'runtime' => $run->runtime === null ? null : (int) $run->runtime,
            'started_at' => (int) $run->started_at,
            'finished_at' => $run->finished_at === null ? null : (int) $run->finished_at,
        ], $details ? ['exception' => $run->exception ?? null, 'host' => $run->host ?? null] : []);
    }
}
