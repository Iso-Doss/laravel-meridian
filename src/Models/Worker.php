<?php

namespace Kakodjenou\Meridian\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A "queue:work" process reporting its heartbeat.
 *
 * @property string $name
 * @property string $hostname
 * @property int $pid
 * @property string|null $supervisor
 * @property string $connection
 * @property string $queue
 * @property string $status
 * @property int $jobs_processed
 * @property int $memory
 * @property Carbon $started_at
 * @property Carbon $last_seen_at
 */
class Worker extends Model
{
    protected $table = 'meridian_workers';

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $dateFormat = 'U';

    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'jobs_processed' => 'integer',
            'memory' => 'integer',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('meridian.database');
    }

    /**
     * Workers seen recently, or currently running a (possibly long) job.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAlive(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->where('last_seen_at', '>=', time() - (int) config('meridian.worker_timeout', 90))
                ->orWhere('status', 'working');
        });
    }
}
