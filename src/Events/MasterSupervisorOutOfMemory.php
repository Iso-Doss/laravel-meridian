<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;
use IsoDoss\Meridian\Supervisor\MasterSupervisor;

/**
 * "php artisan meridian" exceeded meridian.memory_limit and restarts.
 */
class MasterSupervisorOutOfMemory
{
    use Dispatchable;

    public function __construct(
        public MasterSupervisor $master,
        public int $memory,
    ) {}
}
