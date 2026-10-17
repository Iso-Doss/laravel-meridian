<?php

namespace IsoDoss\Meridian\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A supervisor started by "php artisan meridian".
 *
 * @property string $name
 * @property string $master
 * @property string $hostname
 * @property int $pid
 * @property string $status
 * @property array<string, int> $processes
 * @property array<string, mixed> $options
 * @property string|null $command
 * @property Carbon $started_at
 * @property Carbon $last_seen_at
 */
class Supervisor extends Model
{
    protected $table = 'meridian_supervisors';

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
            'processes' => 'array',
            'options' => 'array',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('meridian.database');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAlive(Builder $query): void
    {
        $query->where('last_seen_at', '>=', time() - 30);
    }

    public function totalProcesses(): int
    {
        return (int) array_sum($this->processes ?? []);
    }

    public function shortName(): string
    {
        return str_contains($this->name, ':') ? substr($this->name, strrpos($this->name, ':') + 1) : $this->name;
    }
}
