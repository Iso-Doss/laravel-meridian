<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use IsoDoss\Meridian\Support\FileWatcher;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ListenCommand extends Command
{
    protected $signature = 'meridian:listen
                            {--environment= : The environment whose supervisors should be started}
                            {--poll=1 : Seconds between two checks of the files}';

    protected $description = 'Run Meridian and restart it whenever a file of the application changes (local development)';

    /**
     * Watched when config/meridian.php was published before the "watch" option existed.
     */
    public const DEFAULT_WATCH = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'composer.lock', '.env'];

    protected ?Process $meridian = null;

    protected bool $stopping = false;

    public function handle(): int
    {
        $watcher = new FileWatcher((array) (config('meridian.watch') ?? self::DEFAULT_WATCH), base_path());
        $fingerprint = $watcher->fingerprint();
        $poll = max(1, (int) $this->option('poll'));

        $this->listenForSignals();
        $this->start();
        $this->components->info('Watching the application files: Meridian restarts when they change.');

        while (! $this->shouldStop()) {
            sleep($poll);

            // SIGINT / SIGTERM may arrive while sleeping.
            if ($this->shouldStop()) {
                break;
            }

            $current = $watcher->fingerprint();

            if ($current !== $fingerprint) {
                $fingerprint = $current;
                $this->components->info('Changes detected: restarting Meridian...');
                $this->stop();
                $this->start();
            } elseif (! $this->meridian?->isRunning()) {
                $this->components->warn('Meridian stopped: starting it again...');
                $this->start();
            }
        }

        $this->stop();

        return self::SUCCESS;
    }

    protected function start(): void
    {
        $command = [(new PhpExecutableFinder)->find(false) ?: 'php', base_path('artisan'), 'meridian'];

        if ($environment = $this->option('environment')) {
            $command[] = '--environment='.$environment;
        }

        $this->meridian = new Process($command, base_path(), null, null, null);
        $this->meridian->start(fn ($type, $output) => $this->output->write($output));
    }

    /**
     * Ask Meridian to finish the running jobs, then wait for it.
     */
    protected function stop(): void
    {
        if (! $this->meridian?->isRunning()) {
            return;
        }

        $this->meridian->signal(defined('SIGTERM') ? SIGTERM : 15);
        $deadline = microtime(true) + 120;

        while ($this->meridian->isRunning() && microtime(true) < $deadline) {
            usleep(200_000);
        }

        $this->meridian->stop(0);
    }

    /**
     * Set by the signal handlers (pcntl async signals), so it may change
     * between two calls.
     *
     * @phpstan-impure
     */
    protected function shouldStop(): bool
    {
        return $this->stopping;
    }

    protected function listenForSignals(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->stopping = true;
            });
        }
    }
}
