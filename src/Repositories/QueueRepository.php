<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;

/**
 * Reads the live state of the database queues straight from the "jobs" table
 * (the equivalent of Horizon's WorkloadRepository).
 */
class QueueRepository
{
    /**
     * Get a query builder on the jobs table of a database queue connection.
     */
    public function jobsTable(string $connection): Builder
    {
        $config = config("queue.connections.{$connection}", []);

        return DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'jobs');
    }

    /**
     * Pending / delayed / reserved counts and wait time per queue.
     *
     * @return Collection<int, array{connection: string, queue: string, pending: int, delayed: int, reserved: int, wait: int, processes: int}>
     */
    public function workload(): Collection
    {
        $now = time();
        $processes = $this->processesByQueue();

        return collect(Meridian::watchedConnections())
            ->flatMap(function (string $connection) use ($now, $processes) {
                $rows = $this->jobsTable($connection)
                    ->selectRaw('queue')
                    // Aliases suffixed: « delayed » is a reserved word of MySQL / MariaDB.
                    ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS pending_jobs', [$now])
                    ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_jobs', [$now])
                    ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved_jobs')
                    ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_available', [$now])
                    ->groupBy('queue')
                    ->get()
                    ->keyBy('queue');

                $queues = $rows->keys()
                    ->merge($this->configuredQueues($connection))
                    ->unique()
                    ->sort()
                    ->values();

                return $queues->map(function (string $queue) use ($rows, $connection, $now, $processes) {
                    $row = $rows->get($queue);

                    return [
                        'connection' => $connection,
                        'queue' => $queue,
                        'pending' => (int) ($row->pending_jobs ?? 0),
                        'delayed' => (int) ($row->delayed_jobs ?? 0),
                        'reserved' => (int) ($row->reserved_jobs ?? 0),
                        'wait' => isset($row->oldest_available) ? max(0, $now - (int) $row->oldest_available) : 0,
                        'processes' => (int) ($processes[$connection.':'.$queue] ?? 0),
                    ];
                });
            })
            ->values();
    }

    /**
     * The wait time (in seconds) of the oldest pending job, per "connection:queue".
     *
     * @return Collection<string, int>
     */
    public function waitTimes(): Collection
    {
        return $this->workload()
            ->mapWithKeys(fn (array $queue) => [$queue['connection'].':'.$queue['queue'] => $queue['wait']])
            ->sortDesc();
    }

    /**
     * Number of jobs ready to be processed now on a queue.
     */
    public function readyNow(string $connection, string $queue): int
    {
        return $this->jobsTable($connection)
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', time())
            ->count();
    }

    /**
     * Number of live worker processes per "connection:queue".
     *
     * @return array<string, int>
     */
    public function processesByQueue(): array
    {
        $counts = [];

        foreach (Worker::query()->alive()->get(['connection', 'queue']) as $worker) {
            foreach (explode(',', $worker->queue) as $queue) {
                $key = $worker->connection.':'.$queue;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Queues mentioned in the supervisors configuration or in recent history.
     *
     * @return array<int, string>
     */
    protected function configuredQueues(string $connection): array
    {
        $fromConfig = collect(config('meridian.defaults', []))
            ->merge(config('meridian.environments.'.app()->environment(), []))
            ->filter(fn ($supervisor) => ($supervisor['connection'] ?? 'database') === $connection)
            ->flatMap(fn ($supervisor) => (array) ($supervisor['queue'] ?? []));

        $fromHistory = MonitoredJob::query()
            ->where('connection', $connection)
            ->where('queued_at', '>=', time() - 3600)
            ->distinct()
            ->pluck('queue');

        return $fromConfig->merge($fromHistory)->unique()->values()->all();
    }

    /**
     * Global status: running, paused or inactive.
     */
    public function status(): string
    {
        $supervisors = Supervisor::query()->alive()->get(['status']);

        if ($supervisors->isNotEmpty()) {
            return $supervisors->every(fn ($s) => $s->status === 'paused') ? 'paused' : 'running';
        }

        return Worker::query()->alive()->exists() ? 'running' : 'inactive';
    }
}
