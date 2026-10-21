<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Models\Supervisor;

class ListCommand extends Command
{
    protected $signature = 'meridian:list';

    protected $description = 'List the machines running Meridian (the master supervisors)';

    public function handle(): int
    {
        $masters = Supervisor::query()->alive()->orderBy('master')->get()->groupBy('master');

        if ($masters->isEmpty()) {
            $this->components->info('No master supervisor is running.');

            return self::SUCCESS;
        }

        $this->table(['Master', 'Host', 'PID', 'Supervisors', 'Processes', 'Status', 'Started'], $masters->map(function ($supervisors, $master) {
            $first = $supervisors->first();
            $paused = $supervisors->every(fn (Supervisor $supervisor) => $supervisor->status === 'paused');

            return [
                $master,
                $first->hostname,
                $first->pid,
                $supervisors->map(fn (Supervisor $supervisor) => $supervisor->shortName())->sort()->implode(', '),
                $supervisors->sum(fn (Supervisor $supervisor) => $supervisor->totalProcesses()),
                $paused ? 'paused' : 'running',
                $first->started_at->diffForHumans(),
            ];
        })->values()->all());

        return self::SUCCESS;
    }
}
