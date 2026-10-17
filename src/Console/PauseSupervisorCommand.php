<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;

class PauseSupervisorCommand extends Command
{
    use SendsSupervisorCommands;

    protected $signature = 'meridian:pause-supervisor
                            {name : The name of the supervisor, e.g. supervisor-1}
                            {--all : Target the masters of every host, not only this one}';

    protected $description = 'Pause the workers of one supervisor';

    public function handle(): int
    {
        return $this->sendCommand('pause', (string) $this->argument('name'));
    }
}
