<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;

class PauseCommand extends Command
{
    use SendsSupervisorCommands;

    protected $signature = 'meridian:pause {--all : Target the masters of every host, not only this one}';

    protected $description = 'Pause all workers';

    public function handle(): int
    {
        return $this->sendCommand('pause');
    }
}
