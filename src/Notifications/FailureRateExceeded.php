<?php

namespace IsoDoss\Meridian\Notifications;

class FailureRateExceeded extends Alert
{
    public function __construct(
        public string $connection,
        public string $queue,
        public int $failed,
        public int $processed,
        public int $minutes,
    ) {}

    public function title(): string
    {
        return 'High failure rate detected';
    }

    public function summary(): string
    {
        $rate = $this->processed > 0 ? round($this->failed / $this->processed * 100, 1) : 0;

        return "{$this->failed} of the {$this->processed} jobs processed on the \"{$this->queue}\" queue of the \"{$this->connection}\" connection failed in the last {$this->minutes} minutes ({$rate}%).";
    }

    public function url(): string
    {
        return route('meridian.errors.index');
    }
}
