<?php

namespace Kakodjenou\Meridian\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Kakodjenou\Meridian\Events\LongWaitDetected;
use Kakodjenou\Meridian\Repositories\MetricsRepository;
use Kakodjenou\Meridian\Repositories\QueueRepository;
use Kakodjenou\Meridian\Support\PendingJobsImporter;

class SnapshotCommand extends Command
{
    protected $signature = 'meridian:snapshot {--minutes=5 : Size of the snapshot window}';

    protected $description = 'Store a metrics snapshot and check the queues wait times';

    public function handle(MetricsRepository $metrics, QueueRepository $queues, PendingJobsImporter $importer): int
    {
        $period = max(1, (int) $this->option('minutes')) * 60;
        $to = intdiv(time(), $period) * $period;

        // Snapshot the last complete window (and refresh the previous one,
        // in case the scheduler skipped a run).
        $rows = $metrics->snapshot($to - $period, $to) + $metrics->snapshot($to - 2 * $period, $to - $period);

        $this->components->info("Metrics snapshot stored ({$rows} rows).");

        $imported = $importer->import();

        if ($imported > 0) {
            $this->components->info("Imported {$imported} job(s) pushed without event (bulk / pushRaw).");
        }

        $this->checkWaitTimes($queues);

        return self::SUCCESS;
    }

    protected function checkWaitTimes(QueueRepository $queues): void
    {
        $thresholds = config('meridian.waits', []);

        foreach ($queues->waitTimes() as $key => $seconds) {
            $threshold = $thresholds[$key] ?? $thresholds['*'] ?? null;

            if ($threshold === null || $threshold <= 0 || $seconds < $threshold) {
                continue;
            }

            $cooldown = (int) config('meridian.notifications.cooldown', 5) * 60;

            if (! Cache::add('meridian:long-wait:'.$key, true, $cooldown)) {
                continue;
            }

            [$connection, $queue] = explode(':', $key, 2);

            event(new LongWaitDetected($connection, $queue, $seconds));

            $this->components->warn("Long wait detected on [{$key}]: {$seconds}s.");
        }
    }
}
