<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A job has been running for longer than "meridian.alerts.long_running" seconds.
 */
class LongRunningJobDetected
{
    use Dispatchable;

    public function __construct(
        public string $uuid,
        public string $name,
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}
}
