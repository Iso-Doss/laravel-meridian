<?php

namespace IsoDoss\Meridian\Repositories;

use Illuminate\Support\Facades\Schema;
use IsoDoss\Meridian\Meridian;
use Throwable;

/**
 * The history of the manual retries of each job (meridian_job_retries).
 */
class JobRetryRepository
{
    protected ?bool $ready = null;

    /**
     * False until the migration of v1.5.0 has run: the history is then not recorded.
     */
    public function isReady(): bool
    {
        try {
            return $this->ready ??= Schema::connection(config('meridian.database'))->hasTable('meridian_job_retries');
        } catch (Throwable) {
            return $this->ready = false;
        }
    }

    /**
     * @param  array<int, string>  $uuids
     */
    public function retried(array $uuids): void
    {
        if ($uuids === [] || ! $this->isReady()) {
            return;
        }

        $now = time();

        Meridian::table('job_retries')->insert(array_map(fn (string $uuid) => [
            'job_uuid' => $uuid,
            'status' => 'pending',
            'retried_at' => $now,
        ], $uuids));
    }

    /**
     * Update the retry in progress of a job, if any.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(string $uuid, array $values): void
    {
        if (! $this->isReady()) {
            return;
        }

        Meridian::table('job_retries')->where('job_uuid', $uuid)->whereNull('finished_at')->update($values);
    }

    /**
     * @return array<int, array{status: string, runtime: int|null, exception: string|null, retried_at: int, finished_at: int|null}>
     */
    public function history(string $uuid): array
    {
        if (! $this->isReady()) {
            return [];
        }

        return Meridian::table('job_retries')
            ->where('job_uuid', $uuid)
            ->orderBy('id')
            ->get(['status', 'runtime', 'exception', 'retried_at', 'finished_at'])
            ->map(fn (object $row) => [
                'status' => (string) $row->status,
                'runtime' => $row->runtime === null ? null : (int) $row->runtime,
                'exception' => $row->exception,
                'retried_at' => (int) $row->retried_at,
                'finished_at' => $row->finished_at === null ? null : (int) $row->finished_at,
            ])
            ->all();
    }

    /**
     * @param  iterable<int, string>  $uuids
     */
    public function forget(iterable $uuids): void
    {
        $uuids = collect($uuids)->values();

        if ($uuids->isEmpty() || ! $this->isReady()) {
            return;
        }

        foreach ($uuids->chunk(500) as $chunk) {
            Meridian::table('job_retries')->whereIn('job_uuid', $chunk->all())->delete();
        }
    }
}
