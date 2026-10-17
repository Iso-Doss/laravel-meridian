<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;

class ContinueSupervisorCommand extends Command
{
    use SendsSupervisorCommands;

    protected $signature = 'meridian:continue-supervisor
                            {name : The name of the supervisor, e.g. supervisor-1}
                            {--all : Target the masters of every host, not only this one}';

    protected $description = 'Resume the workers of one paused supervisor';

    public function handle(): int
    {
        return $this->sendCommand('continue', (string) $this->argument('name'));
    }
}
