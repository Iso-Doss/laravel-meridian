<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Models\Supervisor;

class SupervisorStatusCommand extends Command
{
    protected $signature = 'meridian:supervisor-status {name : The name of the supervisor, e.g. supervisor-1}';

    protected $description = 'Show the status of a supervisor';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        $supervisors = Supervisor::query()->alive()->get()
            ->filter(fn (Supervisor $supervisor) => $supervisor->shortName() === $name)
            ->values();

        if ($supervisors->isEmpty()) {
            $this->components->error("The [{$name}] supervisor is not running.");

            return self::FAILURE;
        }

        foreach ($supervisors as $supervisor) {
            $message = "{$supervisor->shortName()} ({$supervisor->master}) is {$supervisor->status}.";

            $supervisor->status === 'paused' ? $this->components->warn($message) : $this->components->info($message);
        }

        return self::SUCCESS;
    }
}
