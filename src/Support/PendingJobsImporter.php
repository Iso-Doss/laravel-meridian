<?php

namespace Kakodjenou\Meridian\Support;

use Illuminate\Support\Facades\Cache;
use Kakodjenou\Meridian\Meridian;
use Kakodjenou\Meridian\Models\MonitoredJob;
use Kakodjenou\Meridian\Repositories\QueueRepository;
use Throwable;

/**
 * Imports the jobs present in the "jobs" table that Meridian has not seen
 * being pushed: jobs inserted with Queue::bulk() (job batches), pushRaw(),
 * by another application or before Meridian was installed.
 */
class PendingJobsImporter
{
    public function __construct(protected QueueRepository $queues) {}

    public function import(int $limit = 1000): int
    {
        $imported = 0;

        foreach (Meridian::watchedConnections() as $connection) {
            try {
                $imported += $this->importConnection($connection, $limit);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $imported;
    }

    protected function importConnection(string $connection, int $limit): int
    {
        $cursorKey = 'meridian:import-cursor:'.$connection;
        $cursor = (int) Cache::get($cursorKey, 0);

        $rows = $this->queues->jobsTable($connection)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);

        if ($rows->isEmpty()) {
            return 0;
        }

        Cache::forever($cursorKey, (int) $rows->last()->id);

        $decoded = $rows->mapWithKeys(function ($row) {
            $payload = json_decode($row->payload, true);

            return isset($payload['uuid']) ? [$payload['uuid'] => [$row, $payload]] : [];
        });

        $known = MonitoredJob::query()->whereIn('uuid', $decoded->keys())->pluck('uuid')->flip();

        $records = $decoded->reject(fn ($item, $uuid) => $known->has($uuid))
            ->map(function ($item, $uuid) use ($connection) {
                [$row, $payload] = $item;
                $command = JobPayload::command($payload);

                return [
                    'uuid' => $uuid,
                    'connection' => $connection,
                    'queue' => $row->queue,
                    'name' => $payload['displayName'] ?? 'Unknown',
                    'status' => $row->reserved_at ? MonitoredJob::PROCESSING : MonitoredJob::PENDING,
                    'attempts' => (int) $row->attempts,
                    'tags' => json_encode(Tags::for($command)),
                    'batch_id' => JobPayload::batchId($command),
                    'job_id' => (string) $row->id,
                    'payload' => config('meridian.store_payload') ? $row->payload : null,
                    'queued_at' => (int) ($payload['createdAt'] ?? $row->created_at),
                    'available_at' => (int) $row->available_at,
                    'reserved_at' => $row->reserved_at,
                    'updated_at' => time(),
                ];
            })
            ->values();

        foreach ($records->chunk(200) as $chunk) {
            MonitoredJob::query()->insertOrIgnore($chunk->all());
        }

        return $records->count();
    }
}
