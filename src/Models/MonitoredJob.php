<?php

namespace IsoDoss\Meridian\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $name
 * @property string $status
 * @property int $attempts
 * @property int $retries
 * @property array<int, string>|null $tags
 * @property string|null $batch_id
 * @property string|null $job_id
 * @property string|null $worker
 * @property string|null $payload
 * @property string|null $exception
 * @property int|null $wait
 * @property int|null $runtime
 * @property Carbon $queued_at
 * @property Carbon $available_at
 * @property Carbon|null $reserved_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 */
class MonitoredJob extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const STATUSES = [self::PENDING, self::PROCESSING, self::COMPLETED, self::FAILED];

    protected $table = 'meridian_jobs';

    protected $guarded = [];

    protected $dateFormat = 'U';

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'attempts' => 'integer',
            'retries' => 'integer',
            'wait' => 'integer',
            'runtime' => 'integer',
            'queued_at' => 'datetime',
            'available_at' => 'datetime',
            'reserved_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('meridian.database');
    }

    /**
     * Short class name, e.g. "SendInvoice" for "App\Jobs\SendInvoice".
     */
    public function shortName(): string
    {
        return class_basename($this->name);
    }

    /**
     * The job is waiting for a delay to expire.
     */
    public function isDelayed(): bool
    {
        return $this->status === self::PENDING && $this->available_at->isFuture();
    }

    /**
     * Decoded payload, pretty printed (with the serialized command left as is).
     */
    public function prettyPayload(): ?string
    {
        if (! $this->payload) {
            return null;
        }

        $decoded = json_decode($this->payload, true);

        return $decoded === null
            ? $this->payload
            : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
    }

    /**
     * Search by job name, uuid or tag.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $term = trim($term);

        $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', '%'.$term.'%')
                ->orWhere('uuid', $term)
                ->orWhere('tags', 'like', '%'.trim(json_encode($term), '"').'%');
        });
    }
}
