<?php

namespace IsoDoss\Meridian\Supervisor;

use Closure;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use Throwable;

/**
 * Manages the worker pools of one configured supervisor.
 */
class Supervisor
{
    /**
     * @var array<string, ProcessPool>
     */
    public array $pools = [];

    public bool $paused = false;

    protected float $lastBalancedAt = 0;

    public function __construct(public SupervisorOptions $options, ?Closure $output = null)
    {
        foreach ($options->pools() as $queue) {
            $this->pools[$queue] = new ProcessPool($options, $queue, $output);
        }
    }

    /**
     * Start the initial processes.
     */
    public function start(): void
    {
        $initial = match ($this->options->balance) {
            'auto' => array_fill_keys(array_keys($this->pools), $this->options->minProcesses),
            default => app(Balancer::class)->balance($this->options, [], array_fill_keys(array_keys($this->pools), 0)),
        };

        foreach ($initial as $queue => $processes) {
            $this->pools[$queue]->scale($processes);
        }

        $this->lastBalancedAt = microtime(true);
    }

    /**
     * One iteration of the supervisor loop.
     */
    public function loop(): void
    {
        foreach ($this->pools as $pool) {
            $pool->monitor();
        }

        if (! $this->paused
            && $this->options->autoScaling()
            && microtime(true) - $this->lastBalancedAt >= $this->options->balanceCooldown) {
            $this->autoScale();
        }
    }

    public function autoScale(): void
    {
        $this->lastBalancedAt = microtime(true);

        try {
            $queues = app(QueueRepository::class);
            $metrics = app(MetricsRepository::class);

            $workload = [];

            foreach (array_keys($this->pools) as $queue) {
                $workload[$queue] = [
                    'size' => $queues->readyNow($this->options->connection, $queue),
                    'runtime' => $metrics->runtimeForQueue($queue),
                ];
            }

            $desired = app(Balancer::class)->balance($this->options, $workload, $this->processCounts());

            foreach ($desired as $queue => $processes) {
                $this->pools[$queue]->scale($processes);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, int>
     */
    public function processCounts(): array
    {
        return array_map(fn (ProcessPool $pool) => $pool->count(), $this->pools);
    }

    public function totalProcessCount(): int
    {
        return array_sum($this->processCounts());
    }

    public function pause(): void
    {
        $this->paused = true;

        foreach ($this->pools as $pool) {
            $pool->pause();
        }
    }

    public function continue(): void
    {
        $this->paused = false;

        foreach ($this->pools as $pool) {
            $pool->continue();
        }
    }

    public function restart(): void
    {
        foreach ($this->pools as $pool) {
            $pool->restart();
        }
    }

    public function terminate(): void
    {
        foreach ($this->pools as $pool) {
            $pool->terminate();
        }
    }

    public function hasRunningProcesses(): bool
    {
        foreach ($this->pools as $pool) {
            $pool->monitor();

            if ($pool->hasRunningProcesses()) {
                return true;
            }
        }

        return false;
    }
}
