<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;

class ContinueCommand extends Command
{
    use SendsSupervisorCommands;

    protected $signature = 'meridian:continue {--all : Target the masters of every host, not only this one}';

    protected $description = 'Resume all paused workers';

    public function handle(): int
    {
        return $this->sendCommand('continue');
    }
}
