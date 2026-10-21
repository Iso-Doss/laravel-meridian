<?php

namespace IsoDoss\Meridian\Notifications;

class NoWorkersRunning extends Alert
{
    public function __construct(public int $pending) {}

    public function title(): string
    {
        return 'No worker running';
    }

    public function summary(): string
    {
        return "{$this->pending} job(s) are waiting but no worker is running.";
    }
}
