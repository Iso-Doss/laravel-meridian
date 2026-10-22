<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\Alerts;
use IsoDoss\Meridian\Support\PendingJobsImporter;

class SnapshotCommand extends Command
{
    protected $signature = 'meridian:snapshot {--minutes=5 : Size of the snapshot window}';

    protected $description = 'Store a metrics snapshot and check the health of the queues';

    public const LAST_RUN = 'meridian:snapshot:last-run';

    public function handle(MetricsRepository $metrics, Alerts $alerts, PendingJobsImporter $importer): int
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

        // The schedule is defined in console only: keep the dashboard's copy up to date.
        app(ScheduleRepository::class)->sync();

        // Tells meridian:doctor that the scheduler runs.
        rescue(fn () => Cache::forever(self::LAST_RUN, time()), report: false);

        foreach ($alerts->check() as $alert) {
            $this->components->warn($alert);
        }

        return self::SUCCESS;
    }
}
