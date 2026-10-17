<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Support\Collection;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;

/**
 * Throughput / runtime statistics computed from "meridian_jobs" (live)
 * and "meridian_metrics" (history snapshots).
 */
class MetricsRepository
{
    /**
     * Jobs processed (completed or failed) during the last $minutes.
     */
    public function processedSince(int $minutes): int
    {
        $since = time() - $minutes * 60;

        return MonitoredJob::query()
            ->where(fn ($q) => $q->where('completed_at', '>=', $since)->orWhere('failed_at', '>=', $since))
            ->count();
    }

    public function failedSince(int $minutes): int
    {
        return MonitoredJob::query()
            ->where('status', MonitoredJob::FAILED)
            ->where('failed_at', '>=', time() - $minutes * 60)
            ->count();
    }

    public function jobsPerMinute(int $minutes = 60): float
    {
        return round($this->processedSince($minutes) / max(1, $minutes), 2);
    }

    /**
     * Average runtime (ms) of the jobs completed recently on a queue.
     */
    public function runtimeForQueue(string $queue, int $minutes = 60): float
    {
        return (float) MonitoredJob::query()
            ->where('queue', $queue)
            ->where('status', MonitoredJob::COMPLETED)
            ->where('completed_at', '>=', time() - $minutes * 60)
            ->avg('runtime');
    }

    /**
     * The queue whose completed jobs ran the longest on average during the last $minutes.
     *
     * @return array{queue: string, runtime: int}|null
     */
    public function queueWithMaximumRuntime(int $minutes = 60): ?array
    {
        $row = Meridian::table('jobs')
            ->select(['connection', 'queue'])
            ->selectRaw('AVG(runtime) AS average')
            ->where('status', MonitoredJob::COMPLETED)
            ->where('completed_at', '>=', time() - $minutes * 60)
            ->groupBy('connection', 'queue')
            ->orderByDesc('average')
            ->first();

        return $row ? ['queue' => $row->connection.':'.$row->queue, 'runtime' => (int) round((float) $row->average)] : null;
    }

    /**
     * The queue that processed the most jobs (completed or failed) during the last $minutes.
     *
     * @return array{queue: string, count: int}|null
     */
    public function queueWithMaximumThroughput(int $minutes = 60): ?array
    {
        $since = time() - $minutes * 60;

        $row = Meridian::table('jobs')
            ->select(['connection', 'queue'])
            ->selectRaw('COUNT(*) AS processed')
            ->where(fn ($q) => $q->where('completed_at', '>=', $since)->orWhere('failed_at', '>=', $since))
            ->groupBy('connection', 'queue')
            ->orderByDesc('processed')
            ->first();

        return $row ? ['queue' => $row->connection.':'.$row->queue, 'count' => (int) $row->processed] : null;
    }

    /**
     * Number of jobs processed per minute over the last $minutes (for charts).
     *
     * @return array<int, int> minute timestamp => count
     */
    public function throughputPerMinute(int $minutes = 60): array
    {
        $start = (intdiv(time(), 60) - $minutes + 1) * 60;
        $series = array_fill_keys(range($start, $start + ($minutes - 1) * 60, 60), 0);

        Meridian::table('jobs')
            ->selectRaw('COALESCE(completed_at, failed_at) AS done_at')
            ->where(fn ($q) => $q->where('completed_at', '>=', $start)->orWhere('failed_at', '>=', $start))
            ->orderBy('id')
            ->lazy(1000)
            ->each(function ($row) use (&$series) {
                $minute = intdiv((int) $row->done_at, 60) * 60;

                if (isset($series[$minute])) {
                    $series[$minute]++;
                }
            });

        return $series;
    }

    /**
     * Aggregated statistics per job class or per queue.
     *
     * @param  'name'|'queue'  $groupBy
     * @return Collection<int, \stdClass> rows with key, processed, failed, runtime, max_runtime and wait
     */
    public function breakdown(string $groupBy, int $minutes = 1440): Collection
    {
        $since = time() - $minutes * 60;

        return Meridian::table('jobs')
            ->select($groupBy.' as key')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS processed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->selectRaw("AVG(CASE WHEN status = 'completed' THEN runtime END) AS runtime")
            ->selectRaw('MAX(runtime) AS max_runtime')
            ->selectRaw('AVG(wait) AS wait')
            ->where(fn ($q) => $q->where('completed_at', '>=', $since)->orWhere('failed_at', '>=', $since))
            ->groupBy($groupBy)
            ->orderByDesc('processed')
            ->get();
    }

    /**
     * History snapshots for a job class or a queue.
     *
     * @param  'job'|'queue'  $type
     * @return Collection<int, \stdClass>
     */
    public function snapshots(string $type, string $name, int $limit = 288): Collection
    {
        return Meridian::table('metrics')
            ->where('type', $type)
            ->where('name', $name)
            ->orderByDesc('bucket')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Aggregate the jobs finished in [$from, $to[ into "meridian_metrics".
     */
    public function snapshot(int $from, int $to): int
    {
        $rows = [];

        foreach (['job' => 'name', 'queue' => 'queue'] as $type => $column) {
            Meridian::table('jobs')
                ->select($column.' as key')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS processed")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
                ->selectRaw("AVG(CASE WHEN status = 'completed' THEN runtime END) AS runtime")
                ->selectRaw('AVG(wait) AS wait')
                ->where(function ($q) use ($from, $to) {
                    $q->whereBetween('completed_at', [$from, $to - 1])
                        ->orWhereBetween('failed_at', [$from, $to - 1]);
                })
                ->groupBy($column)
                ->get()
                ->each(function ($row) use (&$rows, $type, $from, $to) {
                    $rows[] = [
                        'type' => $type,
                        'name' => $row->key,
                        'bucket' => $from,
                        'period' => $to - $from,
                        'processed' => (int) $row->processed,
                        'failed' => (int) $row->failed,
                        'runtime' => (int) round((float) $row->runtime),
                        'wait' => (int) round((float) $row->wait),
                    ];
                });
        }

        if ($rows !== []) {
            Meridian::table('metrics')->upsert(
                $rows,
                ['type', 'name', 'bucket'],
                ['period', 'processed', 'failed', 'runtime', 'wait']
            );
        }

        return count($rows);
    }
}
