<?php

namespace Kakodjenou\Meridian\Console;

use Illuminate\Console\Command;
use Kakodjenou\Meridian\Models\Supervisor;
use Kakodjenou\Meridian\Models\Worker;
use Kakodjenou\Meridian\Repositories\QueueRepository;

class StatusCommand extends Command
{
    protected $signature = 'meridian:status';

    protected $description = 'Show the status of Meridian, its supervisors and workers';

    public function handle(QueueRepository $queues): int
    {
        $status = $queues->status();

        match ($status) {
            'running' => $this->components->info('Meridian is running.'),
            'paused' => $this->components->warn('Meridian is paused.'),
            default => $this->components->error('Meridian is inactive.'),
        };

        $supervisors = Supervisor::query()->alive()->orderBy('name')->get();

        if ($supervisors->isNotEmpty()) {
            $this->table(['Supervisor', 'Host', 'PID', 'Status', 'Processes'], $supervisors->map(fn (Supervisor $s) => [
                $s->name,
                $s->hostname,
                $s->pid,
                $s->status,
                collect($s->processes)->map(fn ($n, $q) => "{$q}: {$n}")->implode(', '),
            ]));
        }

        $this->table(['Queue', 'Pending', 'Delayed', 'Reserved', 'Processes', 'Wait'], $queues->workload()->map(fn ($q) => [
            $q['connection'].':'.$q['queue'], $q['pending'], $q['delayed'], $q['reserved'], $q['processes'], $q['wait'].'s',
        ]));

        $this->components->twoColumnDetail('Live workers', (string) Worker::query()->alive()->count());

        return $status === 'inactive' ? self::FAILURE : self::SUCCESS;
    }
}
