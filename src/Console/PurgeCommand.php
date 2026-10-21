<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Supervisor\ProcessInspector;

class PurgeCommand extends Command
{
    protected $signature = 'meridian:purge
                            {--signal=SIGTERM : The signal sent to the orphaned workers}';

    protected $description = 'Terminate the workers left by a Meridian master that is gone, and forget the dead processes of this machine';

    public function handle(ProcessInspector $inspector): int
    {
        if (DIRECTORY_SEPARATOR !== '/' || ! function_exists('posix_kill')) {
            $this->components->error('meridian:purge requires a Unix system with the posix extension.');

            return self::FAILURE;
        }

        $signal = $this->signal();

        if ($signal === null) {
            $this->components->error("Unknown signal [{$this->option('signal')}].");

            return self::FAILURE;
        }

        $orphans = $inspector->orphanedWorkers();

        foreach ($orphans as $process) {
            $inspector->kill($process['pid'], $signal);
            $this->components->twoColumnDetail("Sent {$this->option('signal')} to <fg=yellow>{$process['pid']}</>", $process['command']);
        }

        // Rows of this machine whose process is gone (killed without terminating).
        $stale = 0;

        foreach (['supervisors', 'workers'] as $table) {
            Meridian::table($table)->where('hostname', Meridian::hostname())->get(['name', 'pid'])
                ->reject(fn (object $row) => $inspector->isRunning((int) $row->pid))
                ->each(function (object $row) use ($table, &$stale) {
                    $stale += Meridian::table($table)->where('name', $row->name)->delete();
                });
        }

        $this->components->info(count($orphans).' orphaned worker(s) terminated, '.$stale.' dead process(es) forgotten.');

        return self::SUCCESS;
    }

    protected function signal(): ?int
    {
        $signal = strtoupper((string) $this->option('signal'));

        if (is_numeric($signal)) {
            return (int) $signal;
        }

        $name = str_starts_with($signal, 'SIG') ? $signal : 'SIG'.$signal;

        return defined($name) ? (int) constant($name) : null;
    }
}
