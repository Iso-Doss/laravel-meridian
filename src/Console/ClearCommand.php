<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;

class ClearCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'meridian:clear
                            {connection? : The queue connection (the default one when omitted)}
                            {--queue= : The queue to clear (the default queue of the connection when omitted)}
                            {--force : Clear without confirmation in production}';

    protected $description = 'Delete the jobs waiting on a queue and their records in Meridian';

    public function handle(QueueRepository $queues): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $connection = (string) ($this->argument('connection') ?: config('queue.default'));

        if (! Meridian::watches($connection)) {
            $this->components->error("The [{$connection}] connection is not a database queue watched by Meridian.");

            return self::FAILURE;
        }

        $queue = (string) ($this->option('queue') ?: config("queue.connections.{$connection}.queue", 'default'));

        // Jobs being processed finish normally; only the waiting ones are removed.
        $cleared = $queues->jobsTable($connection)->where('queue', $queue)->whereNull('reserved_at')->delete();

        $pending = MonitoredJob::query()
            ->where('connection', $connection)
            ->where('queue', $queue)
            ->where('status', MonitoredJob::PENDING);

        app(JobRetryRepository::class)->forget((clone $pending)->pluck('uuid'));
        $pending->delete();

        app(AuditLogRepository::class)->record('queue.cleared', "{$connection}:{$queue}", ['count' => $cleared]);

        $this->components->info("{$cleared} job(s) deleted from the [{$connection}:{$queue}] queue.");

        return self::SUCCESS;
    }
}
