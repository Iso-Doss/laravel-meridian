<?php

namespace IsoDoss\Meridian\Supervisor;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Finds the Meridian processes running on this machine (Unix only).
 */
class ProcessInspector
{
    /**
     * Every process of the machine.
     *
     * @return array<int, array{pid: int, ppid: int, command: string}>
     */
    public function processes(): array
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            return [];
        }

        try {
            $process = new Process(['ps', '-axo', 'pid=,ppid=,command=']);
            $process->run();
        } catch (Throwable) {
            return [];
        }

        $processes = [];

        foreach (explode("\n", $process->getOutput()) as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(.+)$/', $line, $match)) {
                $processes[] = ['pid' => (int) $match[1], 'ppid' => (int) $match[2], 'command' => trim($match[3])];
            }
        }

        return $processes;
    }

    /**
     * The "php artisan meridian" masters of this application.
     *
     * @return array<int, array{pid: int, ppid: int, command: string}>
     */
    public function masters(): array
    {
        $artisan = preg_quote(base_path('artisan'), '/');

        return array_values(array_filter(
            $this->processes(),
            fn (array $process) => (bool) preg_match('/(^|\s)(\S*\/)?artisan meridian(\s|$)|'.$artisan.' meridian(\s|$)/', $process['command'])
        ));
    }

    /**
     * The workers started by a Meridian master that is no longer running
     * (killed without terminating its workers).
     *
     * @return array<int, array{pid: int, ppid: int, command: string}>
     */
    public function orphanedWorkers(): array
    {
        $masters = array_column($this->masters(), 'pid');
        $artisan = base_path('artisan');
        $names = $this->supervisorNames();

        return array_values(array_filter($this->processes(), function (array $process) use ($masters, $artisan, $names) {
            if (! str_contains($process['command'], $artisan.' queue:work')
                || ! preg_match('/--name=(\S+)/', $process['command'], $name)) {
                return false;
            }

            return in_array($name[1], $names, true) && ! in_array($process['ppid'], $masters, true);
        }));
    }

    public function isRunning(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return in_array($pid, array_column($this->processes(), 'pid'), true);
    }

    public function kill(int $pid, int $signal): bool
    {
        return function_exists('posix_kill') && posix_kill($pid, $signal);
    }

    /**
     * The names of the supervisors configured in any environment.
     *
     * @return array<int, string>
     */
    protected function supervisorNames(): array
    {
        return collect(config('meridian.environments', []))
            ->flatMap(fn ($supervisors) => array_keys((array) $supervisors))
            ->merge(array_keys((array) config('meridian.defaults', [])))
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();
    }
}
