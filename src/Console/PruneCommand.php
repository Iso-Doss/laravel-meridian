<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;

class PruneCommand extends Command
{
    protected $signature = 'meridian:prune
                            {--completed= : Minutes to keep completed jobs}
                            {--failed= : Minutes to keep failed jobs}';

    protected $description = 'Delete old jobs, metrics and stale heartbeats recorded by Meridian';

    public function handle(): int
    {
        $now = time();
        $minutes = fn (string $key) => (int) ($this->option($key) ?? config("meridian.trim.{$key}"));

        $completed = $this->chunkDelete(MonitoredJob::COMPLETED, 'completed_at', $now - $minutes('completed') * 60);
        $failed = $this->chunkDelete(MonitoredJob::FAILED, 'failed_at', $now - $minutes('failed') * 60);

        $metrics = Meridian::table('metrics')
            ->where('bucket', '<', $now - (int) config('meridian.trim.metrics', 10080) * 60)
            ->delete();

        $stale = $now - (int) config('meridian.trim.workers', 60) * 60;
        $workers = Meridian::table('workers')->where('last_seen_at', '<', $stale)->delete();
        $supervisors = Meridian::table('supervisors')->where('last_seen_at', '<', $stale)->delete();

        $this->components->info("Pruned {$completed} completed jobs, {$failed} failed jobs, {$metrics} metrics, ".($workers + $supervisors).' stale heartbeats.');

        return self::SUCCESS;
    }

    protected function chunkDelete(string $status, string $column, int $before): int
    {
        $total = 0;

        do {
            $ids = Meridian::table('jobs')
                ->where('status', $status)
                ->where($column, '<', $before)
                ->limit(1000)
                ->pluck('id');

            $total += $ids->isEmpty() ? 0 : Meridian::table('jobs')->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $total;
    }
}
