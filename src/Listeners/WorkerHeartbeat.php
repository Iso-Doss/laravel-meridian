<?php

namespace IsoDoss\Meridian\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use IsoDoss\Meridian\Meridian;
use Throwable;

/**
 * Lets every "queue:work" process report itself in "meridian_workers",
 * whether it is managed by "php artisan meridian" or by Supervisor/systemd.
 */
class WorkerHeartbeat
{
    protected int $startedAt;

    protected int $lastBeat = 0;

    protected ?string $lastStatus = null;

    protected int $jobsProcessed = 0;

    protected ?string $supervisor = null;

    public function __construct()
    {
        $this->startedAt = time();
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Looping::class => 'looping',
            WorkerStopping::class => 'stopping',
        ];
    }

    public function looping(Looping $event): void
    {
        $this->supervisor = $event->workerOptions->name ?? $this->supervisor;

        $this->idle($event->connectionName, $event->queue);
    }

    public function idle(?string $connection, ?string $queue, bool $processed = false): void
    {
        if ($processed) {
            $this->jobsProcessed++;
        }

        $this->beat('idle', $connection, $queue);
    }

    public function working(?string $connection, ?string $queue): void
    {
        $this->beat('working', $connection, $queue);
    }

    public function stopping(WorkerStopping $event): void
    {
        try {
            Meridian::table('workers')->where('name', Meridian::processName())->delete();
        } catch (Throwable) {
            //
        }
    }

    protected function beat(string $status, ?string $connection, ?string $queue): void
    {
        if (! app()->runningInConsole() || ! Meridian::watches($connection)) {
            return;
        }

        $now = time();

        if ($status === $this->lastStatus && $now - $this->lastBeat < (int) config('meridian.heartbeat', 5)) {
            return;
        }

        $this->lastBeat = $now;
        $this->lastStatus = $status;

        try {
            Meridian::table('workers')->upsert([[
                'name' => Meridian::processName(),
                'hostname' => Meridian::hostname(),
                'pid' => getmypid(),
                'supervisor' => $this->supervisor,
                'connection' => $connection,
                'queue' => (string) $queue,
                'status' => $status,
                'jobs_processed' => $this->jobsProcessed,
                'memory' => memory_get_usage(true),
                'started_at' => $this->startedAt,
                'last_seen_at' => $now,
            ]], ['name'], ['supervisor', 'queue', 'status', 'jobs_processed', 'memory', 'last_seen_at']);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
