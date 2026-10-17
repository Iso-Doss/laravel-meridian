<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Models\Supervisor;

class SupervisorsCommand extends Command
{
    protected $signature = 'meridian:supervisors';

    protected $description = 'List the running supervisors';

    public function handle(): int
    {
        $supervisors = Supervisor::query()->alive()->orderBy('name')->get();

        if ($supervisors->isEmpty()) {
            $this->components->info('No supervisor is running.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Master', 'Host', 'PID', 'Status', 'Processes', 'Queues', 'Balancing'],
            $supervisors->map(fn (Supervisor $supervisor) => [
                $supervisor->shortName(),
                $supervisor->master,
                $supervisor->hostname,
                $supervisor->pid,
                $supervisor->status,
                $supervisor->totalProcesses(),
                collect($supervisor->processes)->map(fn ($count, $queue) => "{$queue}: {$count}")->implode(', '),
                ($supervisor->options['balance'] ?? false) ?: 'off',
            ])
        );

        return self::SUCCESS;
    }
}
