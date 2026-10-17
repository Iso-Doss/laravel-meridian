<?php

namespace IsoDoss\Meridian\Listeners;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Carbon;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\JobPayload;
use IsoDoss\Meridian\Support\Tags;
use Throwable;

/**
 * Records every step of a job's life into the "meridian_jobs" table.
 *
 * This replaces what Horizon does with its Redis repositories: the database
 * queue deletes a job row once it is processed, so we keep our own history.
 */
class RecordJobLifecycle
{
    /**
     * Start time (hrtime) of the jobs currently running in this process.
     *
     * @var array<string, int>
     */
    protected array $timers = [];

    /**
     * Last exception thrown by the jobs currently running in this process
     * (JobReleasedAfterException only carries it since Laravel 13).
     *
     * @var array<string, string>
     */
    protected array $exceptions = [];

    public function __construct(protected WorkerHeartbeat $heartbeat) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobQueued::class => 'queued',
            JobProcessing::class => 'processing',
            JobProcessed::class => 'processed',
            JobExceptionOccurred::class => 'exceptionOccurred',
            JobReleasedAfterException::class => 'released',
            JobFailed::class => 'failed',
            JobRetryRequested::class => 'retryRequested',
        ];
    }

    public function queued(JobQueued $event): void
    {
        if (! Meridian::watches($event->connectionName)) {
            return;
        }

        $this->safely(function () use ($event) {
            $payload = json_decode((string) $event->payload, true) ?: [];

            if (! isset($payload['uuid'])) {
                return;
            }

            $now = time();
            $job = $event->job;

            MonitoredJob::query()->upsert([[
                'uuid' => $payload['uuid'],
                'connection' => $event->connectionName,
                'queue' => $this->queueName($event->connectionName, $event->queue),
                'name' => $payload['displayName'] ?? (is_object($job) ? get_class($job) : 'Closure'),
                'status' => MonitoredJob::PENDING,
                'attempts' => 0,
                'tags' => json_encode(Tags::for($job)),
                'batch_id' => JobPayload::batchId($job),
                'job_id' => $event->id === null ? null : (string) $event->id,
                'payload' => config('meridian.store_payload') ? $event->payload : null,
                'queued_at' => $now,
                'available_at' => $this->availableAt($event->delay, $now),
                'updated_at' => $now,
            ]], ['uuid'], ['job_id', 'status', 'available_at', 'updated_at']);
        });
    }

    public function processing(JobProcessing $event): void
    {
        if (! Meridian::watches($event->connectionName) || ! $uuid = $event->job->uuid()) {
            return;
        }

        $this->timers[$uuid] = hrtime(true);

        $this->heartbeat->working($event->connectionName, $event->job->getQueue());

        $this->safely(function () use ($event, $uuid) {
            $now = time();

            $updated = MonitoredJob::query()->where('uuid', $uuid)->update([
                'status' => MonitoredJob::PROCESSING,
                'attempts' => $event->job->attempts(),
                'job_id' => (string) $event->job->getJobId(),
                'worker' => Meridian::processName(),
                'reserved_at' => $now,
                'wait' => Meridian::db()->raw('CASE WHEN wait IS NULL AND available_at <= '.$now.' THEN '.$now.' - available_at ELSE wait END'),
                'updated_at' => $now,
            ]);

            // The job was pushed before Meridian was installed, by another
            // application, or with pushRaw(): record it now.
            if ($updated === 0) {
                $this->recordFromJob($event->connectionName, $event->job, MonitoredJob::PROCESSING);
            }
        });
    }

    public function processed(JobProcessed $event): void
    {
        $job = $event->job;

        if (! Meridian::watches($event->connectionName) || ! $uuid = $job->uuid()) {
            return;
        }

        // Failures are handled by the JobFailed event.
        if ($job->hasFailed()) {
            return;
        }

        $this->heartbeat->idle($event->connectionName, $job->getQueue(), processed: true);

        $runtime = $this->stopTimer($uuid);

        $this->safely(function () use ($job, $uuid, $runtime) {
            $query = MonitoredJob::query()->where('uuid', $uuid);

            if ($job->isReleased()) {
                $query->update([
                    'status' => MonitoredJob::PENDING,
                    'available_at' => time(),
                    'runtime' => $runtime,
                    'updated_at' => time(),
                ]);

                return;
            }

            $record = $query->first(['id', 'name', 'tags']);

            if ($record && Meridian::isSilenced($record->name, $record->tags ?? [])) {
                $record->delete();

                return;
            }

            $query->update([
                'status' => MonitoredJob::COMPLETED,
                'runtime' => $runtime,
                'completed_at' => time(),
                'updated_at' => time(),
            ]);
        });
    }

    public function exceptionOccurred(JobExceptionOccurred $event): void
    {
        if (Meridian::watches($event->connectionName) && $uuid = $event->job->uuid()) {
            $this->exceptions[$uuid] = (string) $event->exception;
        }
    }

    public function released(JobReleasedAfterException $event): void
    {
        $job = $event->job;

        if (! Meridian::watches($event->connectionName) || ! $uuid = $job->uuid()) {
            return;
        }

        $runtime = $this->stopTimer($uuid);
        $exception = $this->pullException($uuid);

        $this->safely(fn () => MonitoredJob::query()->where('uuid', $uuid)->update([
            'status' => MonitoredJob::PENDING,
            'runtime' => $runtime,
            'exception' => $exception,
            'available_at' => time() + (int) ($event->backoff ?? 0),
            'updated_at' => time(),
        ]));
    }

    public function failed(JobFailed $event): void
    {
        $job = $event->job;

        if (! Meridian::watches($event->connectionName) || ! $uuid = $job->uuid()) {
            return;
        }

        $this->heartbeat->idle($event->connectionName, $job->getQueue(), processed: true);

        $runtime = $this->stopTimer($uuid);
        unset($this->exceptions[$uuid]);

        $this->safely(function () use ($event, $job, $uuid, $runtime) {
            $updated = MonitoredJob::query()->where('uuid', $uuid)->update([
                'status' => MonitoredJob::FAILED,
                'attempts' => $job->attempts(),
                'runtime' => $runtime,
                'exception' => (string) $event->exception,
                'failed_at' => time(),
                'updated_at' => time(),
            ]);

            if ($updated === 0) {
                $this->recordFromJob($event->connectionName, $job, MonitoredJob::FAILED, (string) $event->exception);
            }
        });
    }

    public function retryRequested(JobRetryRequested $event): void
    {
        $uuid = $event->payload()['uuid'] ?? null;

        if (! $uuid || ! Meridian::watches($event->job->connection ?? null)) {
            return;
        }

        $this->safely(fn () => MonitoredJob::query()->where('uuid', $uuid)->update([
            'status' => MonitoredJob::PENDING,
            'attempts' => 0,
            'retries' => Meridian::db()->raw('retries + 1'),
            'failed_at' => null,
            'available_at' => time(),
            'updated_at' => time(),
        ]));
    }

    protected function recordFromJob(string $connection, Job $job, string $status, ?string $exception = null): void
    {
        $payload = $job->payload();
        $now = time();
        $command = JobPayload::command($payload);

        MonitoredJob::query()->insertOrIgnore([
            'uuid' => $job->uuid(),
            'connection' => $connection,
            'queue' => $job->getQueue(),
            'name' => $job->resolveName(),
            'status' => $status,
            'attempts' => $job->attempts(),
            'tags' => json_encode(Tags::for($command)),
            'batch_id' => JobPayload::batchId($command),
            'job_id' => (string) $job->getJobId(),
            'worker' => Meridian::processName(),
            'payload' => config('meridian.store_payload') ? $job->getRawBody() : null,
            'exception' => $exception,
            'queued_at' => $payload['createdAt'] ?? $now,
            'available_at' => $now,
            'reserved_at' => $now,
            'failed_at' => $status === MonitoredJob::FAILED ? $now : null,
            'updated_at' => $now,
        ]);
    }

    protected function queueName(string $connection, mixed $queue): string
    {
        if ($queue instanceof \UnitEnum) {
            $queue = $queue instanceof \BackedEnum ? $queue->value : $queue->name;
        }

        return (string) ($queue ?: config("queue.connections.{$connection}.queue", 'default'));
    }

    protected function availableAt(mixed $delay, int $now): int
    {
        return match (true) {
            $delay instanceof DateTimeInterface => $delay->getTimestamp(),
            $delay instanceof DateInterval => Carbon::now()->add($delay)->getTimestamp(),
            is_numeric($delay) => $now + (int) $delay,
            default => $now,
        };
    }

    protected function pullException(string $uuid): ?string
    {
        $exception = $this->exceptions[$uuid] ?? null;

        unset($this->exceptions[$uuid]);

        return $exception;
    }

    protected function stopTimer(string $uuid): ?int
    {
        if (! isset($this->timers[$uuid])) {
            return null;
        }

        $runtime = (int) round((hrtime(true) - $this->timers[$uuid]) / 1e6);

        unset($this->timers[$uuid]);

        return $runtime;
    }

    /**
     * Monitoring must never break the application or the worker.
     */
    protected function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
