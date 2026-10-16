<?php

namespace Kakodjenou\Meridian\Supervisor;

use Closure;
use Illuminate\Support\Str;
use Kakodjenou\Meridian\Meridian;
use Kakodjenou\Meridian\Models\Supervisor as SupervisorModel;
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
                return $this->shutdown();
            }

            $this->processPendingCommands();

            foreach ($this->supervisors as $supervisor) {
                $supervisor->loop();
            }

            if (microtime(true) - $this->lastPersistedAt >= 3) {
                $this->persist();
            }
        }
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

        $timeout = max(array_map(fn (Supervisor $s) => $s->options->timeout, $this->supervisors) ?: [60]) + 10;
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $running = array_filter($this->supervisors, fn (Supervisor $s) => $s->hasRunningProcesses());

            if ($running === []) {
                break;
            }

            usleep(250_000);
        }

        try {
            SupervisorModel::query()->where('master', $this->name)->delete();
        } catch (Throwable) {
            //
        }

        return 0;
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
            $command = SupervisorModel::query()
                ->where('master', $this->name)
                ->whereNotNull('command')
                ->value('command');

            if (! $command) {
                return;
            }

            SupervisorModel::query()->where('master', $this->name)->update(['command' => null]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $this->info("Received command [{$command}].");

        match ($command) {
            'pause' => $this->pause(),
            'continue' => $this->continue(),
            'restart' => $this->restart(),
            'terminate' => $this->terminate(),
            default => null,
        };
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
                'status' => $this->working ? 'running' : 'paused',
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
