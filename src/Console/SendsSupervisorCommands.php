<?php

namespace IsoDoss\Meridian\Console;

use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\Supervisor;

trait SendsSupervisorCommands
{
    protected function sendCommand(string $command): int
    {
        $query = Supervisor::query()->alive();

        if (! $this->option('all')) {
            $query->where('hostname', Meridian::hostname());
        }

        $count = (clone $query)->distinct()->count('master');
        $query->update(['command' => $command]);

        if ($count === 0) {
            $this->components->warn('No running Meridian master supervisor found.');
        } else {
            $this->components->info("Sent [{$command}] to {$count} master supervisor(s).");
        }

        return self::SUCCESS;
    }
}
