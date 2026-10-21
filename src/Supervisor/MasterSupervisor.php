<?php

namespace IsoDoss\Meridian\Supervisor;

use Closure;
use Illuminate\Support\Str;
use IsoDoss\Meridian\Events\MasterSupervisorOutOfMemory;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor as SupervisorModel;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\Migrations;
use Throwable;

/**
 * The long running "php artisan meridian" process.
 *
 * It runs every supervisor configured for the current environment, reacts
 * to POSIX signals and to commands sent through the database
 * (meridian:pause, meridian:continue, meridian:terminate).
 */
class MasterSupervisor
{
    public string $name;

    /**
     * @var array<int, Supervisor>
     */
    public array $supervisors = [];

    public bool $working = true;

    /**
     * Exit code of run(): 12 when the master restarts because of its memory.
     */
    public int $exitCode = 0;

    protected bool $terminating = false;

    protected int $startedAt;

    protected float $lastPersistedAt = 0;

    /**
     * @param  array<int, SupervisorOptions>  $options
     */
    public function __construct(array $options, protected ?Closure $output = null)
    {
        $this->name = Meridian::hostname().'-'.Str::lower(Str::random(4));
        $this->startedAt = time();

        foreach ($options as $option) {
            $this->supervisors[] = new Supervisor($option, $output);
        }
    }

    public function run(): int
    {
        $this->listenForSignals();

        foreach ($this->supervisors as $supervisor) {
            $supervisor->start();
        }

        $this->persist();

        while (true) {
            usleep(1_000_000);

            if ($this->terminating) {
                $this->shutdown();

                return $this->exitCode;
            }

            $this->processPendingCommands();
            $this->stopCancelledJobs();

            foreach ($this->supervisors as $supervisor) {
                $supervisor->loop();
            }

            if (microtime(true) - $this->lastPersistedAt >= 3) {
                $this->persist();
            }

            if ($this->exceedsMemoryLimit()) {
                $this->info('Memory limit exceeded: terminating so that the process monitor restarts Meridian.');
                event(new MasterSupervisorOutOfMemory($this, memory_get_usage(true)));
                $this->terminate();
                $this->exitCode = 12;
            }
        }
    }

    public function exceedsMemoryLimit(): bool
    {
        $limit = (int) config('meridian.memory_limit', 64);

        return $limit > 0 && memory_get_usage(true) / 1024 / 1024 > $limit;
    }

    public function pause(): void
    {
        $this->working = false;

        foreach ($this->supervisors as $supervisor) {
            $supervisor->pause();
        }

        $this->persist();
    }

    public function continue(): void
    {
        $this->working = true;

        foreach ($this->supervisors as $supervisor) {
            $supervisor->continue();
        }

        $this->persist();
    }

    public function restart(): void
    {
        foreach ($this->supervisors as $supervisor) {
            $supervisor->restart();
        }
    }

    public function terminate(): void
    {
        $this->terminating = true;
    }

    /**
     * Gracefully stop every worker, then exit.
     */
    protected function shutdown(): int
    {
        $this->info('Shutting down the workers...');

        foreach ($this->supervisors as $supervisor) {
            $supervisor->terminate();
        }

        // Like Horizon's fast_termination: exit now, the workers finish their
        // jobs on their own, so that the next deployment starts right away.
        if (config('meridian.fast_termination', false)) {
            $this->info('Fast termination: the workers finish their current job on their own.');

            foreach ($this->supervisors as $supervisor) {
                $supervisor->detach();
            }

            $this->forget();

            return 0;
        }

        $timeout = max(array_map(fn (Supervisor $s) => $s->options->timeout, $this->supervisors) ?: [60]) + 10;
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $running = array_filter($this->supervisors, fn (Supervisor $s) => $s->hasRunningProcesses());

            if ($running === []) {
                break;
            }

            usleep(250_000);
        }

        $this->forget();

        return 0;
    }

    /**
     * Remove this master from the dashboard.
     */
    protected function forget(): void
    {
        try {
            SupervisorModel::query()->where('master', $this->name)->delete();
        } catch (Throwable) {
            //
        }
    }

    protected function listenForSignals(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGTERM, fn () => $this->terminate());
        pcntl_signal(SIGINT, fn () => $this->terminate());
        pcntl_signal(SIGUSR1, fn () => $this->restart());
        pcntl_signal(SIGUSR2, fn () => $this->pause());
        pcntl_signal(SIGCONT, fn () => $this->continue());
    }

    /**
     * Commands written in the database by meridian:pause / continue / terminate.
     */
    protected function processPendingCommands(): void
    {
        try {
            $commands = SupervisorModel::query()
                ->where('master', $this->name)
                ->whereNotNull('command')
                ->pluck('command', 'name');

            if ($commands->isEmpty()) {
                return;
            }

            SupervisorModel::query()->whereIn('name', $commands->keys())->update(['command' => null]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        // A command written on every row of this master (meridian:pause...)
        // applies to the master; on some rows only, to those supervisors.
        if ($commands->unique()->count() === 1 && $commands->count() === count($this->supervisors)) {
            $command = (string) $commands->first();
            $this->info("Received command [{$command}].");

            match ($command) {
                'pause' => $this->pause(),
                'continue' => $this->continue(),
                'restart' => $this->restart(),
                'terminate' => $this->terminate(),
                default => null,
            };

            return;
        }

        foreach ($commands as $row => $command) {
            $supervisor = $this->supervisorNamed(substr((string) $row, strlen($this->name) + 1));

            if ($supervisor === null) {
                continue;
            }

            $this->info("Received command [{$command}] for [{$supervisor->options->name}].");

            match ($command) {
                'pause' => $supervisor->pause(),
                'continue' => $supervisor->continue(),
                'restart' => $supervisor->restart(),
                'terminate' => $this->terminate(),
                default => null,
            };
        }

        $this->persist();
    }

    /**
     * Stop the workers of this host running a job cancelled from the
     * dashboard that did not stop by itself within "cancel_timeout" seconds.
     */
    protected function stopCancelledJobs(): void
    {
        if (! Migrations::hasCancellation()) {
            return;
        }

        $prefix = Meridian::hostname().':';

        try {
            $jobs = MonitoredJob::query()
                ->where('status', MonitoredJob::PROCESSING)
                ->whereNotNull('cancel_requested_at')
                ->where('cancel_requested_at', '<=', time() - (int) config('meridian.cancel_timeout', 10))
                ->get(['id', 'uuid', 'name', 'connection', 'job_id', 'worker'])
                ->filter(fn (MonitoredJob $job) => str_starts_with((string) $job->worker, $prefix));
        } catch (Throwable $e) {
            report($e);

            return;
        }

        foreach ($jobs as $job) {
            $pid = (int) substr((string) $job->worker, strlen($prefix));
            $process = null;

            foreach ($this->supervisors as $supervisor) {
                $process ??= $supervisor->findByPid($pid);
            }

            // Not one of our workers: it is stopped by the master running it, if any.
            if ($process === null) {
                continue;
            }

            $process->signal(defined('SIGKILL') ? SIGKILL : 9);

            try {
                if ($job->job_id) {
                    app(QueueRepository::class)->jobsTable($job->connection)->where('id', $job->job_id)->delete();
                }

                app(JobRetryRepository::class)->forget([$job->uuid]);
                $job->delete();
            } catch (Throwable $e) {
                report($e);
            }

            $this->info("Stopped the worker running the cancelled job [{$job->name}] ({$job->uuid}).");
        }
    }

    protected function supervisorNamed(string $name): ?Supervisor
    {
        foreach ($this->supervisors as $supervisor) {
            if ($supervisor->options->name === $name) {
                return $supervisor;
            }
        }

        return null;
    }

    /**
     * Save the state of each supervisor for the dashboard.
     */
    public function persist(): void
    {
        $this->lastPersistedAt = microtime(true);

        try {
            $rows = array_map(fn (Supervisor $supervisor) => [
                'name' => $this->name.':'.$supervisor->options->name,
                'master' => $this->name,
                'hostname' => Meridian::hostname(),
                'pid' => getmypid(),
                'status' => $supervisor->paused ? 'paused' : 'running',
                'processes' => json_encode($supervisor->processCounts()),
                'options' => json_encode($supervisor->options->toArray()),
                'started_at' => $this->startedAt,
                'last_seen_at' => time(),
            ], $this->supervisors);

            SupervisorModel::query()->upsert(
                $rows,
                ['name'],
                ['status', 'processes', 'options', 'last_seen_at']
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function info(string $message): void
    {
        if ($this->output) {
            ($this->output)('meridian', $message);
        }
    }
}
