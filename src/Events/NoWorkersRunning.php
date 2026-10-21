<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Jobs are waiting but no worker has reported itself recently.
 */
class NoWorkersRunning
{
    use Dispatchable;

    public function __construct(public int $pending) {}
}
