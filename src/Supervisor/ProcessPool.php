<?php

namespace IsoDoss\Meridian\Supervisor;

use Closure;

/**
 * A set of identical workers processing the same queue(s).
 */
class ProcessPool
{
    /**
     * @var array<int, WorkerProcess>
     */
    public array $processes = [];

    /**
     * Processes asked to stop that are still finishing their job.
     *
     * @var array<int, WorkerProcess>
     */
    public array $terminating = [];

    public bool $paused = false;

    public function __construct(
        public SupervisorOptions $options,
        public string $queue,
        protected ?Closure $output = null,
    ) {}

    public function count(): int
    {
        return count($this->processes);
    }

    public function scale(int $processes): void
    {
        $processes = max(0, $processes);

        while ($this->count() < $processes) {
            $process = new WorkerProcess(WorkerCommand::for($this->options, $this->queue), $this->output);
            $this->processes[] = $process->start();

            if ($this->paused) {
                $process->signal(defined('SIGUSR2') ? SIGUSR2 : 12);
            }
        }

        while ($this->count() > $processes) {
            $process = array_pop($this->processes);
            $process->stop();
            $this->terminating[] = $process;
        }
    }

    /**
     * Restart dead workers and forget the ones that finished terminating.
     */
    public function monitor(): void
    {
        foreach ($this->processes as $process) {
            $process->monitor();
        }

        $this->terminating = array_values(array_filter($this->terminating, function (WorkerProcess $process) {
            $process->killIfStuck($this->options->timeout + 5);

            return $process->isRunning();
        }));
    }

    public function pause(): void
    {
        $this->paused = true;

        foreach ($this->processes as $process) {
            $process->signal(defined('SIGUSR2') ? SIGUSR2 : 12);
        }
    }

    public function continue(): void
    {
        $this->paused = false;

        foreach ($this->processes as $process) {
            $process->signal(defined('SIGCONT') ? SIGCONT : 18);
        }
    }

    public function restart(): void
    {
        $count = $this->count();

        $this->scale(0);
        $this->scale($count);
    }

    public function terminate(): void
    {
        $this->scale(0);
    }

    /**
     * Let the workers finish their jobs on their own after the master exited.
     */
    public function detach(): void
    {
        foreach (array_merge($this->processes, $this->terminating) as $process) {
            $process->detach();
        }
    }

    /**
     * The worker process running with this PID, if any.
     */
    public function findByPid(int $pid): ?WorkerProcess
    {
        foreach ($this->processes as $process) {
            if ($process->pid() === $pid) {
                return $process;
            }
        }

        return null;
    }

    public function hasRunningProcesses(): bool
    {
        foreach (array_merge($this->processes, $this->terminating) as $process) {
            if ($process->isRunning()) {
                return true;
            }
        }

        return false;
    }
}
