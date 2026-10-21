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
            ->whereIn('status', MonitoredJob::SUCCEEDED)
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
            ->whereIn('status', MonitoredJob::SUCCEEDED)
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
     * @param  int|null  $until  End of the window (now by default), to compare with a previous period.
     * @return Collection<int, \stdClass> rows with key, processed, failed, runtime, max_runtime and wait
     */
    public function breakdown(string $groupBy, int $minutes = 1440, ?int $until = null): Collection
    {
        $until ??= time() + 1;
        $since = $until - $minutes * 60;

        return Meridian::table('jobs')
            ->select($groupBy.' as key')
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'silenced') THEN 1 ELSE 0 END) AS processed")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
            ->selectRaw("AVG(CASE WHEN status IN ('completed', 'silenced') THEN runtime END) AS runtime")
            ->selectRaw('MAX(runtime) AS max_runtime')
            ->selectRaw('AVG(wait) AS wait')
            ->where(fn ($q) => $q->whereBetween('completed_at', [$since, $until - 1])->orWhereBetween('failed_at', [$since, $until - 1]))
            ->groupBy($groupBy)
            ->orderByDesc('processed')
            ->get();
    }

    /**
     * Runtime percentiles (in milliseconds) of the jobs of a class or a queue
     * completed during the last $minutes, e.g. [95 => 1200, 99 => 3400].
     *
     * @param  'name'|'queue'  $groupBy
     * @param  array<int, int>  $percentiles
     * @return array<int, int|null>
     */
    public function percentiles(string $groupBy, string $key, int $minutes, array $percentiles = [95, 99]): array
    {
        $query = fn () => Meridian::table('jobs')
            ->where($groupBy, $key)
            ->whereIn('status', MonitoredJob::SUCCEEDED)
            ->where('completed_at', '>=', time() - $minutes * 60)
            ->whereNotNull('runtime');

        $count = $query()->count();
        $values = [];

        foreach ($percentiles as $percentile) {
            // Nearest-rank method: the smallest value greater or equal to p% of the runtimes.
            $values[$percentile] = $count === 0 ? null : (int) $query()
                ->orderBy('runtime')
                ->offset(max(0, (int) ceil($percentile / 100 * $count) - 1))
                ->limit(1)
                ->value('runtime');
        }

        return $values;
    }

    /**
     * Jobs processed and failed per hour during the last $days days.
     *
     * @return array<int, array{time: int, processed: int, failed: int}>
     */
    public function hourly(int $days = 7): array
    {
        $start = (intdiv(time(), 3600) - $days * 24 + 1) * 3600;
        $series = [];

        for ($hour = $start; $hour <= time(); $hour += 3600) {
            $series[$hour] = ['time' => $hour, 'processed' => 0, 'failed' => 0];
        }

        Meridian::table('jobs')
            ->selectRaw('COALESCE(completed_at, failed_at) - (COALESCE(completed_at, failed_at) % 3600) AS hour_bucket')
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'silenced') THEN 1 ELSE 0 END) AS processed_jobs")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_jobs")
            ->where(fn ($q) => $q->where('completed_at', '>=', $start)->orWhere('failed_at', '>=', $start))
            ->groupBy('hour_bucket')
            ->get()
            ->each(function (object $row) use (&$series) {
                if (isset($series[(int) $row->hour_bucket])) {
                    $series[(int) $row->hour_bucket]['processed'] = (int) $row->processed_jobs;
                    $series[(int) $row->hour_bucket]['failed'] = (int) $row->failed_jobs;
                }
            });

        return array_values($series);
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
                ->selectRaw("SUM(CASE WHEN status IN ('completed', 'silenced') THEN 1 ELSE 0 END) AS processed")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")
                ->selectRaw("AVG(CASE WHEN status IN ('completed', 'silenced') THEN runtime END) AS runtime")
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
