<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Repositories\MonitoredTagRepository;

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

        $monitoredBefore = $now - (int) config('meridian.trim.monitored', 10080) * 60;
        $completed = $this->chunkDelete(MonitoredJob::COMPLETED, 'completed_at', $now - $minutes('completed') * 60, $monitoredBefore)
            + $this->chunkDelete(MonitoredJob::SILENCED, 'completed_at', $now - $minutes('completed') * 60, $monitoredBefore);
        $failed = $this->chunkDelete(MonitoredJob::FAILED, 'failed_at', $now - $minutes('failed') * 60, $monitoredBefore);

        $metrics = Meridian::table('metrics')
            ->where('bucket', '<', $now - (int) config('meridian.trim.metrics', 10080) * 60)
            ->delete();

        $stale = $now - (int) config('meridian.trim.workers', 60) * 60;
        $workers = Meridian::table('workers')->where('last_seen_at', '<', $stale)->delete();
        $supervisors = Meridian::table('supervisors')->where('last_seen_at', '<', $stale)->delete();

        $this->components->info("Pruned {$completed} completed jobs, {$failed} failed jobs, {$metrics} metrics, ".($workers + $supervisors).' stale heartbeats.');

        return self::SUCCESS;
    }

    /**
     * Delete the jobs older than $before, except those carrying a monitored
     * tag, kept until $monitoredBefore.
     */
    protected function chunkDelete(string $status, string $column, int $before, int $monitoredBefore): int
    {
        $tags = app(MonitoredTagRepository::class)->all();
        $total = 0;

        do {
            $ids = MonitoredJob::query()
                ->where('status', $status)
                ->where($column, '<', $before)
                ->when($tags !== [] && $monitoredBefore < $before, fn ($query) => $query->where(function ($query) use ($column, $monitoredBefore, $tags) {
                    $query->where($column, '<', $monitoredBefore)
                        ->orWhere(fn ($query) => $query->whereNot(fn ($query) => $query->withAnyTag($tags)));
                }))
                ->limit(1000)
                ->pluck('uuid', 'id');

            app(JobRetryRepository::class)->forget($ids->values());
            $total += $ids->isEmpty() ? 0 : Meridian::table('jobs')->whereIn('id', $ids->keys())->delete();
        } while ($ids->count() === 1000);

        return $total;
    }
}
