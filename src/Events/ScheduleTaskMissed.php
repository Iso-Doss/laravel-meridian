<?php

namespace IsoDoss\Meridian\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A scheduled task did not run when it was due.
 */
class ScheduleTaskMissed
{
    use Dispatchable;

    public function __construct(public string $task, public int $dueAt) {}
}
