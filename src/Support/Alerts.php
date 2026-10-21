<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Support\Facades\Cache;
use IsoDoss\Meridian\Events\FailureRateExceeded;
use IsoDoss\Meridian\Events\LongRunningJobDetected;
use IsoDoss\Meridian\Events\LongWaitDetected;
use IsoDoss\Meridian\Events\NoWorkersRunning;
use IsoDoss\Meridian\Events\ScheduleTaskMissed;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Repositories\ScheduleRepository;

/**
 * The health checks run by meridian:snapshot. Each problem fires an event
 * (notified to the configured routes), at most once per cooldown.
 */
class Alerts
{
    public function __construct(protected QueueRepository $queues, protected ScheduleRepository $schedule) {}

    /**
     * Run every check.
     *
     * @return array<int, string> A description of each alert fired.
     */
    public function check(): array
    {
        return array_merge(
            $this->checkWaitTimes(),
            $this->checkFailureRates(),
            $this->checkWorkers(),
            $this->checkLongRunningJobs(),
            $this->checkSchedule(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function checkWaitTimes(): array
    {
        $thresholds = config('meridian.waits', []);
        $fired = [];

        foreach ($this->queues->waitTimes() as $key => $seconds) {
            // Like Horizon, 60 seconds for the queues not listed; 0 disables a queue.
            $threshold = (int) ($thresholds[$key] ?? $thresholds['*'] ?? 60);

            if ($threshold <= 0 || $seconds < $threshold || ! $this->cooldown('long-wait:'.$key)) {
                continue;
            }

            [$connection, $queue] = explode(':', $key, 2);

            event(new LongWaitDetected($connection, $queue, $seconds));

            $fired[] = "Long wait detected on [{$key}]: {$seconds}s.";
        }

        return $fired;
    }

    /**
     * @return array<int, string>
     */
    public function checkFailureRates(): array
    {
        $rate = (float) config('meridian.alerts.failure_rate', 25);
        $count = (int) config('meridian.alerts.failures', 0);

        if ($rate <= 0 && $count <= 0) {
            return [];
        }

        $minutes = max(1, (int) config('meridian.alerts.window', 5));
        $minimum = max(1, (int) config('meridian.alerts.failure_rate_minimum', 10));
        $since = time() - $minutes * 60;
        $fired = [];

        $rows = MonitoredJob::query()->toBase()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereIn('status', MonitoredJob::SUCCEEDED)->where('completed_at', '>=', $since))
                ->orWhere(fn ($query) => $query->where('status', MonitoredJob::FAILED)->where('failed_at', '>=', $since)))
            ->groupBy('connection', 'queue')
            ->selectRaw('connection, queue, COUNT(*) AS processed_jobs, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed_jobs', [MonitoredJob::FAILED])
            ->get();

        foreach ($rows as $row) {
            $failed = (int) $row->failed_jobs;
            $processed = (int) $row->processed_jobs;

            $exceeded = ($rate > 0 && $processed >= $minimum && $failed / $processed * 100 >= $rate)
                || ($count > 0 && $failed >= $count);

            if (! $exceeded || ! $this->cooldown("failure-rate:{$row->connection}:{$row->queue}")) {
                continue;
            }

            event(new FailureRateExceeded($row->connection, $row->queue, $failed, $processed, $minutes));

            $fired[] = "High failure rate on [{$row->connection}:{$row->queue}]: {$failed} of {$processed} jobs failed in {$minutes} minutes.";
        }

        return $fired;
    }

    /**
     * @return array<int, string>
     */
    public function checkWorkers(): array
    {
        if (! config('meridian.alerts.no_workers', true) || Worker::query()->alive()->exists()) {
            return [];
        }

        $pending = (int) $this->queues->workload()->sum('pending');

        if ($pending === 0 || ! $this->cooldown('no-workers')) {
            return [];
        }

        event(new NoWorkersRunning($pending));

        return ["No worker running while {$pending} job(s) are waiting."];
    }

    /**
     * @return array<int, string>
     */
    public function checkLongRunningJobs(): array
    {
        $threshold = (int) config('meridian.alerts.long_running', 0);

        if ($threshold <= 0) {
            return [];
        }

        $now = time();
        $fired = [];

        $jobs = MonitoredJob::query()
            ->where('status', MonitoredJob::PROCESSING)
            ->where('reserved_at', '<=', $now - $threshold)
            ->orderBy('reserved_at')
            ->limit(50)
            ->get(['uuid', 'name', 'connection', 'queue', 'reserved_at']);

        foreach ($jobs as $job) {
            if (! $this->cooldown('long-running:'.$job->uuid)) {
                continue;
            }

            $seconds = $now - (int) $job->reserved_at?->getTimestamp();

            event(new LongRunningJobDetected($job->uuid, $job->name, $job->connection, $job->queue, $seconds));

            $fired[] = "Long running job [{$job->name}] ({$job->uuid}): {$seconds}s.";
        }

        return $fired;
    }

    /**
     * @return array<int, string>
     */
    public function checkSchedule(): array
    {
        if (! config('meridian.alerts.schedule_missed', true)) {
            return [];
        }

        $fired = [];

        foreach ($this->schedule->missed() as $task) {
            // Once per missed run.
            if (! Cache::add('meridian:alert:schedule-missed:'.sha1($task['name']).':'.$task['due_at'], true, 86400)) {
                continue;
            }

            event(new ScheduleTaskMissed($task['name'], $task['due_at']));

            $fired[] = "Scheduled task [{$task['name']}] did not run at ".date('Y-m-d H:i', $task['due_at']).'.';
        }

        return $fired;
    }

    /**
     * False when the same alert was fired less than "notifications.cooldown" minutes ago.
     */
    protected function cooldown(string $key): bool
    {
        return Cache::add('meridian:alert:'.$key, true, max(1, (int) config('meridian.notifications.cooldown', 5)) * 60);
    }
}
