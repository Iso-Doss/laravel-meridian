<?php

namespace Kakodjenou\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LongWaitDetected
{
    use Dispatchable;

    public function __construct(
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}
}
