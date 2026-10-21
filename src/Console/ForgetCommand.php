<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Repositories\JobRetryRepository;

class ForgetCommand extends Command
{
    protected $signature = 'meridian:forget
                            {uuid? : The UUID of the failed job}
                            {--all : Delete every failed job}';

    protected $description = 'Delete a failed job, or all of them, from Meridian and from the failed jobs table';

    public function handle(): int
    {
        $failer = app('queue.failer');

        if ($this->option('all')) {
            $failed = MonitoredJob::query()->where('status', MonitoredJob::FAILED);
            app(JobRetryRepository::class)->forget((clone $failed)->pluck('uuid'));
            $count = $failed->delete();
            $failer->flush();
            app(AuditLogRepository::class)->record('jobs.forgotten', null, ['count' => $count]);

            $this->components->info("{$count} failed job(s) deleted.");

            return self::SUCCESS;
        }

        $uuid = (string) $this->argument('uuid');

        if ($uuid === '') {
            $this->components->error('Give the UUID of a failed job, or --all.');

            return self::FAILURE;
        }

        $deleted = MonitoredJob::query()->where('uuid', $uuid)->where('status', MonitoredJob::FAILED)->delete();
        app(JobRetryRepository::class)->forget([$uuid]);
        $forgotten = $failer->forget($uuid);

        if (! $deleted && ! $forgotten) {
            $this->components->error("No failed job matches the [{$uuid}] UUID.");

            return self::FAILURE;
        }

        app(AuditLogRepository::class)->record('jobs.forgotten', $uuid, ['count' => 1]);

        $this->components->info("The failed job [{$uuid}] has been deleted.");

        return self::SUCCESS;
    }
}
