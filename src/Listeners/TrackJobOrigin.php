<?php

namespace IsoDoss\Meridian\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\JobOrigin;

/**
 * Remembers the job or the scheduled task running in this process, so that
 * the jobs it dispatches know their origin.
 */
class TrackJobOrigin
{
    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessing::class => 'jobStarting',
            // Left after JobFailed: the failed() method of a job may dispatch jobs too.
            JobProcessed::class => 'jobFinished',
            JobReleasedAfterException::class => 'jobFinished',
            JobFailed::class => 'jobFinished',
            CommandStarting::class => 'commandStarting',
            CommandFinished::class => 'commandFinished',
            ScheduledTaskStarting::class => 'taskStarting',
            ScheduledTaskFinished::class => 'taskFinished',
            ScheduledTaskFailed::class => 'taskFinished',
        ];
    }

    public function jobStarting(JobProcessing $event): void
    {
        if ($uuid = $event->job->uuid()) {
            JobOrigin::enterJob($uuid, $event->job->resolveName());
        }
    }

    public function jobFinished(): void
    {
        JobOrigin::leaveJob();
    }

    public function commandStarting(CommandStarting $event): void
    {
        JobOrigin::enterCommand((string) ($event->command ?? 'unknown'));
    }

    public function commandFinished(): void
    {
        JobOrigin::leaveCommand();
    }

    public function taskStarting(ScheduledTaskStarting $event): void
    {
        JobOrigin::enterTask(ScheduleRepository::name($event->task));
    }

    public function taskFinished(): void
    {
        JobOrigin::leaveTask();
    }
}
