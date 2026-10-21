<?php

namespace IsoDoss\Meridian\Notifications;

class LongWaitDetected extends Alert
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}

    public function title(): string
    {
        return 'Long wait detected';
    }

    public function summary(): string
    {
        return "The \"{$this->queue}\" queue on the \"{$this->connection}\" connection has a wait time of {$this->seconds} seconds.";
    }
}
