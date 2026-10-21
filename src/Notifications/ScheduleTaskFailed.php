<?php

namespace IsoDoss\Meridian\Notifications;

class ScheduleTaskFailed extends Alert
{
    public function __construct(public string $task, public string $error) {}

    public function title(): string
    {
        return 'Scheduled task failed';
    }

    public function summary(): string
    {
        return "The scheduled task \"{$this->task}\" failed: {$this->error}";
    }

    public function url(): string
    {
        return route('meridian.schedule.index');
    }
}
