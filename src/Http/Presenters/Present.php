<?php

namespace IsoDoss\Meridian\Http\Presenters;

use Illuminate\Bus\Batch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Support\JobData;
use IsoDoss\Meridian\Support\JobEditor;
use IsoDoss\Meridian\Support\JobOrigin;
use IsoDoss\Meridian\Support\Migrations;

/**
 * Turns Meridian records into the props of the Inertia pages.
 * Dates are Unix timestamps: the interface formats them in the visitor's language.
 */
class Present
{
    /**
     * @return array<string, mixed>
     */
    public static function job(MonitoredJob $job, bool $details = false): array
    {
        $data = [
            'uuid' => $job->uuid,
            'name' => $job->name,
            'short_name' => $job->shortName(),
            'connection' => $job->connection,
            'queue' => $job->queue,
            'status' => $job->status,
            'delayed' => $job->isDelayed(),
            'attempts' => $job->attempts,
            'retries' => $job->retries,
            'tags' => $job->tags ?? [],
            'batch_id' => $job->batch_id,
            'wait' => $job->wait,
            'runtime' => $job->runtime,
            'queued_at' => static::time($job->queued_at),
            'completed_at' => static::time($job->completed_at),
            'failed_at' => static::time($job->failed_at),
            'progress' => $job->status === MonitoredJob::PROCESSING ? $job->progress : null,
            'progress_message' => $job->status === MonitoredJob::PROCESSING ? $job->progress_message : null,
            'remaining' => static::remaining($job),
            'cancelling' => $job->status === MonitoredJob::PROCESSING && $job->cancel_requested_at !== null,
            'url' => route('meridian.jobs.show', $job->uuid),
            'retry_url' => $job->status === MonitoredJob::FAILED && static::canManage() ? route('meridian.jobs.retry', $job->uuid) : null,
        ];

        if (! $details) {
            return $data;
        }

        return array_merge($data, [
            'job_id' => $job->job_id,
            'worker' => $job->worker,
            'available_at' => static::time($job->available_at),
            'reserved_at' => static::time($job->reserved_at),
            'payload' => $job->prettyPayload(),
            'data' => JobData::describe($job->payload),
            'retry_history' => app(JobRetryRepository::class)->history($job->uuid),
            'exception' => $job->exception,
            'batch_url' => $job->batch_id ? route('meridian.batches.show', $job->batch_id) : null,
            'delete_url' => static::canManage() && static::deletable($job) ? route('meridian.jobs.destroy', $job->uuid) : null,
            'error_group' => static::errorGroup($job),
            'edit' => static::edit($job),
            'origin' => JobOrigin::describe($job),
            'children' => JobOrigin::children($job),
        ]);
    }

    /**
     * The form changing the data of a failed job before retrying it.
     *
     * @return array{url: string, fields: array<int, array{name: string, type: string, value: string, editable: bool}>}|null
     */
    protected static function edit(MonitoredJob $job): ?array
    {
        if ($job->status !== MonitoredJob::FAILED || ! Meridian::canEditJobs(request())) {
            return null;
        }

        $fields = app(JobEditor::class)->fields($job);

        return $fields && collect($fields)->contains('editable', true)
            ? ['url' => route('meridian.jobs.retry-edited', $job->uuid), 'fields' => $fields]
            : null;
    }

    /**
     * Pending and finished jobs can be deleted, running ones cancelled once.
     */
    protected static function deletable(MonitoredJob $job): bool
    {
        if ($job->status !== MonitoredJob::PROCESSING) {
            return true;
        }

        return Migrations::hasCancellation() && $job->cancel_requested_at === null;
    }

    /**
     * Estimated seconds before a running job that reports its progress completes.
     */
    public static function remaining(MonitoredJob $job): ?int
    {
        $progress = $job->progress;

        if ($job->status !== MonitoredJob::PROCESSING || ! $job->reserved_at || $progress === null || $progress <= 0 || $progress >= 100) {
            return null;
        }

        $elapsed = max(0, now()->getTimestamp() - $job->reserved_at->getTimestamp());

        return (int) round($elapsed * (100 - $progress) / $progress);
    }

    /**
     * The other failed jobs with the same cause.
     *
     * @return array{count: int, url: string}|null
     */
    protected static function errorGroup(MonitoredJob $job): ?array
    {
        if ($job->status !== MonitoredJob::FAILED || ! $job->fingerprint) {
            return null;
        }

        return [
            'count' => app(ErrorGroupRepository::class)->countFor($job->fingerprint),
            'url' => route('meridian.jobs.index', ['status' => MonitoredJob::FAILED, 'error' => $job->fingerprint]),
        ];
    }

    /**
     * Whether the visitor can act on the jobs (computed once per request).
     */
    public static function canManage(): bool
    {
        static $cache = [];

        $request = request();

        return $cache[spl_object_id($request)] ??= Meridian::canManage($request);
    }

    /**
     * @return array<string, mixed>
     */
    public static function worker(Worker $worker): array
    {
        return [
            'name' => $worker->name,
            'hostname' => $worker->hostname,
            'pid' => $worker->pid,
            'supervisor' => $worker->supervisor,
            'connection' => $worker->connection,
            'queue' => $worker->queue,
            'status' => $worker->status,
            'jobs_processed' => $worker->jobs_processed,
            'memory' => $worker->memory,
            'started_at' => static::time($worker->started_at),
            'last_seen_at' => static::time($worker->last_seen_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function supervisor(Supervisor $supervisor): array
    {
        return [
            'name' => $supervisor->name,
            'short_name' => $supervisor->shortName(),
            'hostname' => $supervisor->hostname,
            'pid' => $supervisor->pid,
            'status' => $supervisor->status,
            'connection' => $supervisor->options['connection'] ?? null,
            'balance' => ($supervisor->options['balance'] ?? false) ?: null,
            'processes' => (object) ($supervisor->processes ?? []),
            'total_processes' => $supervisor->totalProcesses(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function batch(Batch $batch): array
    {
        $status = match (true) {
            $batch->cancelled() => 'cancelled',
            $batch->finished() && $batch->failedJobs > 0 => 'failed',
            $batch->finished() => 'finished',
            default => 'running',
        };

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'failed' => $batch->failedJobs,
            'processed' => $batch->processedJobs(),
            'progress' => $batch->progress(),
            'status' => $status,
            'created_at' => static::time($batch->createdAt),
            'finished_at' => static::time($batch->finishedAt),
            'cancelled_at' => static::time($batch->cancelledAt),
            'url' => route('meridian.batches.show', $batch->id),
            'retry_url' => $batch->failedJobs > 0 && static::canManage() ? route('meridian.batches.retry', $batch->id) : null,
        ];
    }

    /**
     * @param  callable(mixed): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public static function paginated(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => array_map($map, $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    public static function time(mixed $date): ?int
    {
        return $date instanceof \DateTimeInterface ? Carbon::instance($date)->getTimestamp() : null;
    }
}
