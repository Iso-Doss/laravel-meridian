<?php

namespace IsoDoss\Meridian\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\JobRetryRepository;

/**
 * Pushes failed jobs back onto their queue: through "queue:retry" when they
 * are still in the failed jobs table, otherwise from the payload Meridian stored.
 */
class FailedJobRetrier
{
    /**
     * Retry one failed job. False when it cannot be retried (no stored payload).
     */
    public function retry(MonitoredJob $job): bool
    {
        return $this->retryMany([$job])['retried'] === 1;
    }

    /**
     * Retry every failed job of the query, by chunks.
     *
     * @param  Builder<MonitoredJob>  $query
     * @return array{retried: int, skipped: int}
     */
    public function retryQuery(Builder $query): array
    {
        $totals = ['retried' => 0, 'skipped' => 0];

        // Walk the ids forward: retried jobs leave the "failed" status while we go.
        $lastId = null;

        do {
            $jobs = (clone $query)
                ->where('status', MonitoredJob::FAILED)
                ->when($lastId !== null, fn ($query) => $query->where('id', '>', $lastId))
                ->orderBy('id')
                ->limit(500)
                ->get();

            if ($jobs->isEmpty()) {
                break;
            }

            $lastId = $jobs->last()->id;
            $result = $this->retryMany($jobs->all());
            $totals['retried'] += $result['retried'];
            $totals['skipped'] += $result['skipped'];
        } while ($jobs->count() === 500);

        return $totals;
    }

    /**
     * @param  array<int, MonitoredJob>  $jobs
     * @return array{retried: int, skipped: int}
     */
    protected function retryMany(array $jobs): array
    {
        $failer = app('queue.failer');
        $inFailedTable = [];
        $retried = 0;
        $skipped = 0;

        foreach ($jobs as $job) {
            if ($job->status !== MonitoredJob::FAILED) {
                continue;
            }

            if ($failer->find($job->uuid)) {
                $inFailedTable[] = $job->uuid;

                continue;
            }

            if (! $job->payload) {
                $skipped++;

                continue;
            }

            $payload = json_decode($job->payload, true);
            $payload['attempts'] = 0;

            Queue::connection($job->connection)->pushRaw((string) json_encode($payload), $job->queue);

            $job->update([
                'status' => MonitoredJob::PENDING,
                'retries' => $job->retries + 1,
                'failed_at' => null,
                'available_at' => now(),
            ]);
            app(JobRetryRepository::class)->retried([$job->uuid]);

            $retried++;
        }

        if ($inFailedTable !== []) {
            // Fires JobRetryRequested for each job: the recorder marks them as pending.
            Artisan::call('queue:retry', ['id' => $inFailedTable]);
            $retried += count($inFailedTable);
        }

        return ['retried' => $retried, 'skipped' => $skipped];
    }
}
