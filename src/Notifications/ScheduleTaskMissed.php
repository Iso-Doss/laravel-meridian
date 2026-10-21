<?php

namespace IsoDoss\Meridian\Notifications;

class ScheduleTaskMissed extends Alert
{
    public function __construct(public string $task, public int $dueAt) {}

    public function title(): string
    {
        return 'Scheduled task missed';
    }

    public function summary(): string
    {
        return "The scheduled task \"{$this->task}\" did not run at ".date('Y-m-d H:i', $this->dueAt).'.';
    }

    public function url(): string
    {
        return route('meridian.schedule.index');
    }
}
