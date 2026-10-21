<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Too many jobs of a queue failed during the last "meridian.alerts.window" minutes.
 */
class FailureRateExceeded
{
    use Dispatchable;

    public function __construct(
        public string $connection,
        public string $queue,
        public int $failed,
        public int $processed,
        public int $minutes,
    ) {}

    /**
     * Percentage of the processed jobs that failed.
     */
    public function rate(): float
    {
        return $this->processed > 0 ? round($this->failed / $this->processed * 100, 1) : 0.0;
    }
}
