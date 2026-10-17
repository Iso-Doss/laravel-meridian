<?php

namespace IsoDoss\Meridian\Http\Presenters;

use Illuminate\Bus\Batch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Support\JobData;

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
            'url' => route('meridian.jobs.show', $job->uuid),
            'retry_url' => $job->status === MonitoredJob::FAILED ? route('meridian.jobs.retry', $job->uuid) : null,
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
            'exception' => $job->exception,
            'batch_url' => $job->batch_id ? route('meridian.batches.show', $job->batch_id) : null,
            'delete_url' => $job->status !== MonitoredJob::PROCESSING ? route('meridian.jobs.destroy', $job->uuid) : null,
        ]);
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
            'retry_url' => $batch->failedJobs > 0 ? route('meridian.batches.retry', $batch->id) : null,
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
