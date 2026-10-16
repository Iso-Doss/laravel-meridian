<?php

namespace Kakodjenou\Meridian\Supervisor;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Builds the "php artisan queue:work ..." command line of a worker process.
 */
class WorkerCommand
{
    /**
     * @return array<int, string>
     */
    public static function for(SupervisorOptions $options, string $queue): array
    {
        $command = [
            (new PhpExecutableFinder)->find(false) ?: 'php',
            base_path('artisan'),
            'queue:work',
            $options->connection,
            '--name='.$options->name,
            '--queue='.$queue,
            '--backoff='.$options->backoff,
            '--max-time='.$options->maxTime,
            '--max-jobs='.$options->maxJobs,
            '--memory='.$options->memory,
            '--sleep='.$options->sleep,
            '--timeout='.$options->timeout,
            '--tries='.$options->tries,
            '--rest='.$options->rest,
        ];

        if ($options->force) {
            $command[] = '--force';
        }

        if ($options->nice > 0 && DIRECTORY_SEPARATOR === '/') {
            array_unshift($command, 'nice', '-n', (string) $options->nice);
        }

        return $command;
    }
}
