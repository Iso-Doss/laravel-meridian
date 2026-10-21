<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Database\Query\Builder;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\ExceptionSummary;
use IsoDoss\Meridian\Support\Migrations;

/**
 * The failed jobs grouped by cause: same exception class thrown at the same line.
 */
class ErrorGroupRepository
{
    /**
     * The groups of failed jobs, the largest first.
     *
     * @return array<int, array{fingerprint: string, class: string, message: string, file: string|null, line: int|null, count: int, jobs: array<int, string>, queues: array<int, string>, first_seen: int, last_seen: int, sample: string}>
     */
    public function groups(?string $queue = null, int $limit = 100): array
    {
        if (! Migrations::hasJobInsights()) {
            return [];
        }

        $rows = $this->failed($queue)
            ->groupBy('fingerprint')
            ->selectRaw('fingerprint, COUNT(*) AS jobs_count, MIN(failed_at) AS first_seen, MAX(failed_at) AS last_seen')
            ->orderByDesc('jobs_count')
            ->orderByDesc('last_seen')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // The last failure of each group (MAX(id) does not exist for the UUIDs of PostgreSQL).
        $samples = $this->failed($queue)
            ->where(function ($query) use ($rows) {
                foreach ($rows as $row) {
                    $query->orWhere(fn ($query) => $query->where('fingerprint', $row->fingerprint)->where('failed_at', $row->last_seen));
                }
            })
            ->orderByDesc('id')
            ->get(['fingerprint', 'uuid', 'exception'])
            ->unique('fingerprint')
            ->keyBy('fingerprint');

        $names = $this->failed($queue)
            ->whereIn('fingerprint', $rows->pluck('fingerprint'))
            ->distinct()
            ->get(['fingerprint', 'name', 'connection', 'queue'])
            ->groupBy('fingerprint');

        return $rows->map(function (object $row) use ($samples, $names) {
            $sample = $samples[$row->fingerprint] ?? null;
            $summary = ExceptionSummary::parse($sample?->exception) ?? ['class' => 'Exception', 'message' => '', 'file' => null, 'line' => null];
            $jobs = collect($names[$row->fingerprint] ?? []);

            return [
                'fingerprint' => (string) $row->fingerprint,
                'class' => $summary['class'],
                'message' => $summary['message'],
                'file' => static::relativePath($summary['file']),
                'line' => $summary['line'],
                'count' => (int) $row->jobs_count,
                'jobs' => $jobs->pluck('name')->unique()->sort()->values()->all(),
                'queues' => $jobs->map(fn (object $job) => $job->connection.':'.$job->queue)->unique()->sort()->values()->all(),
                'first_seen' => (int) $row->first_seen,
                'last_seen' => (int) $row->last_seen,
                'sample' => (string) $sample?->uuid,
            ];
        })->values()->all();
    }

    /**
     * The summary of one group, if it still has failed jobs.
     *
     * @return array{fingerprint: string, class: string, message: string, file: string|null, line: int|null, count: int}|null
     */
    public function find(string $fingerprint): ?array
    {
        if (! Migrations::hasJobInsights()) {
            return null;
        }

        $failed = $this->failed()->where('fingerprint', $fingerprint);
        $count = (clone $failed)->count();
        $summary = ExceptionSummary::parse((clone $failed)->orderByDesc('id')->value('exception'));

        if ($count === 0 || $summary === null) {
            return null;
        }

        return ['fingerprint' => $fingerprint, 'count' => $count] + array_diff_key($summary, ['fingerprint' => true]);
    }

    /**
     * Number of failed jobs sharing the cause of this one.
     */
    public function countFor(?string $fingerprint): int
    {
        return $fingerprint && Migrations::hasJobInsights() ? $this->failed()->where('fingerprint', $fingerprint)->count() : 0;
    }

    /**
     * The path of a file relative to the application, shorter to read.
     */
    public static function relativePath(?string $file): ?string
    {
        $base = rtrim(base_path(), '/').'/';

        return $file !== null && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    /**
     * @return Builder
     */
    protected function failed(?string $queue = null)
    {
        return MonitoredJob::query()->toBase()
            ->where('status', MonitoredJob::FAILED)
            ->whereNotNull('fingerprint')
            ->when($queue, fn ($query) => $query->where('queue', $queue));
    }
}
