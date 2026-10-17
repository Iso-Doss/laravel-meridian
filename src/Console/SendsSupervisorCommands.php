<?php

namespace IsoDoss\Meridian\Console;

use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\Supervisor;

trait SendsSupervisorCommands
{
    protected function sendCommand(string $command, ?string $supervisor = null): int
    {
        $query = Supervisor::query()->alive();

        if ($supervisor !== null) {
            $query->whereIn('name', Supervisor::query()->alive()->get(['name'])
                ->filter(fn (Supervisor $row) => $row->shortName() === $supervisor)
                ->pluck('name'));
        }

        if (! $this->option('all')) {
            $query->where('hostname', Meridian::hostname());
        }

        $count = (clone $query)->distinct()->count('master');
        $query->update(['command' => $command]);

        if ($count === 0) {
            $this->components->warn($supervisor === null
                ? 'No running Meridian master supervisor found.'
                : "No running supervisor named [{$supervisor}] found.");
        } else {
            $this->components->info($supervisor === null
                ? "Sent [{$command}] to {$count} master supervisor(s)."
                : "Sent [{$command}] to the [{$supervisor}] supervisor of {$count} master supervisor(s).");
        }

        return self::SUCCESS;
    }
}
