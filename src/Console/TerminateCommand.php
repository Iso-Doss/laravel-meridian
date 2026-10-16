<?php

namespace Kakodjenou\Meridian\Console;

use Illuminate\Console\Command;

class TerminateCommand extends Command
{
    use SendsSupervisorCommands;

    protected $signature = 'meridian:terminate {--all : Target the masters of every host, not only this one}';

    protected $description = 'Gracefully terminate the master supervisor (use it on deploy)';

    public function handle(): int
    {
        return $this->sendCommand('terminate');
    }
}
