<?php

namespace IsoDoss\Meridian\Supervisor;

use Closure;
use Throwable;

/**
 * One "queue:work" child process.
 */
class WorkerProcess
{
    public ?DetachableProcess $process = null;

    /**
     * When the process was last started.
     */
    protected float $startedAt = 0;

    /**
     * Do not restart a crashing process before this time.
     */
    protected float $restartAfter = 0;

    /**
     * When the process was asked to stop.
     */
    public ?float $stoppingSince = null;

    /**
     * @param  array<int, string>  $command
     */
    public function __construct(public array $command, protected ?Closure $output = null) {}

    public function start(): static
    {
        $this->startedAt = microtime(true);
        $this->process = new DetachableProcess($this->command, base_path(), null, null, null);
        $this->process->start($this->output);

        return $this;
    }

    public function isRunning(): bool
    {
        return $this->process?->isRunning() ?? false;
    }

    public function pid(): ?int
    {
        return $this->process?->getPid();
    }

    /**
     * Restart the process if it died, with a cooldown when it keeps crashing.
     */
    public function monitor(): void
    {
        if ($this->isRunning() || $this->stoppingSince !== null) {
            return;
        }

        $now = microtime(true);

        // A worker that dies right after starting is probably misconfigured
        // (bad connection, syntax error...): wait a bit before trying again.
        if ($this->restartAfter === 0.0 && $this->process?->getExitCode() !== 0 && $now - $this->startedAt < 5) {
            $this->restartAfter = $now + 10;
        }

        if ($now < $this->restartAfter) {
            return;
        }

        $this->restartAfter = 0;
        $this->start();
    }

    public function signal(int $signal): void
    {
        if (! $this->isRunning()) {
            return;
        }

        try {
            $this->process?->signal($signal);
        } catch (Throwable) {
            //
        }
    }

    /**
     * Ask the worker to finish its current job and exit.
     */
    public function stop(): void
    {
        $this->stoppingSince ??= microtime(true);

        $this->signal(defined('SIGTERM') ? SIGTERM : 15);
    }

    /**
     * Let the worker finish its job on its own after the master exited.
     */
    public function detach(): void
    {
        $this->process?->detach();
    }

    /**
     * Force the worker to exit when a graceful stop takes more than $timeout seconds.
     */
    public function killIfStuck(int $timeout): void
    {
        if ($this->stoppingSince !== null && $this->isRunning() && microtime(true) - $this->stoppingSince > $timeout) {
            $this->signal(defined('SIGKILL') ? SIGKILL : 9);
        }
    }
}
