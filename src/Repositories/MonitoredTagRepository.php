<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Database\QueryException;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;

/**
 * Tags whose jobs are listed on the monitoring page and kept longer
 * (trim.monitored), like Horizon's monitored tags.
 */
class MonitoredTagRepository
{
    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        try {
            return Meridian::table('monitored_tags')->orderBy('tag')->pluck('tag')->all();
        } catch (QueryException) {
            // The table of v1.4.0 has not been migrated yet.
            return [];
        }
    }

    public function monitor(string $tag): void
    {
        Meridian::table('monitored_tags')->insertOrIgnore(['tag' => $tag, 'created_at' => time()]);
    }

    public function stopMonitoring(string $tag): void
    {
        Meridian::table('monitored_tags')->where('tag', $tag)->delete();
    }

    public function isReady(): bool
    {
        try {
            Meridian::table('monitored_tags')->limit(1)->get();

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * The monitored tags with their number of jobs.
     *
     * @return array<int, array{tag: string, jobs: int, failed: int, pending: int}>
     */
    public function withCounts(): array
    {
        return array_map(function (string $tag) {
            $counts = MonitoredJob::query()
                ->withTag($tag)
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return [
                'tag' => $tag,
                'jobs' => (int) $counts->sum(),
                'failed' => (int) ($counts[MonitoredJob::FAILED] ?? 0),
                'pending' => (int) ($counts[MonitoredJob::PENDING] ?? 0) + (int) ($counts[MonitoredJob::PROCESSING] ?? 0),
            ];
        }, $this->all());
    }
}
