<?php

namespace IsoDoss\Meridian\Notifications;

class LongRunningJobDetected extends Alert
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $connection,
        public string $queue,
        public int $seconds,
    ) {}

    public function title(): string
    {
        return 'Long running job detected';
    }

    public function summary(): string
    {
        return "The {$this->name} job has been running for {$this->seconds} seconds on the \"{$this->queue}\" queue of the \"{$this->connection}\" connection.";
    }

    public function url(): string
    {
        return route('meridian.jobs.show', $this->uuid);
    }
}
