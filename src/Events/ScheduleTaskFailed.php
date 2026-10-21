<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A scheduled task failed (exception or non-zero exit code).
 */
class ScheduleTaskFailed
{
    use Dispatchable;

    public function __construct(public string $task, public string $error) {}
}
