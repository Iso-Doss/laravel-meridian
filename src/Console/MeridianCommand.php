<?php

namespace Kakodjenou\Meridian\Console;

use Illuminate\Console\Command;
use Kakodjenou\Meridian\Supervisor\MasterSupervisor;
use Kakodjenou\Meridian\Supervisor\SupervisorOptions;

class MeridianCommand extends Command
{
    protected $signature = 'meridian
                            {--environment= : The environment whose supervisors should be started}';

    protected $description = 'Start the Meridian master supervisor (manages queue:work processes)';

    public function handle(): int
    {
        $environment = $this->option('environment') ?: $this->laravel->environment();

        $options = SupervisorOptions::forEnvironment($environment);

        if ($options === []) {
            $this->components->error("No supervisor configured for the [{$environment}] environment in config/meridian.php.");

            return self::FAILURE;
        }

        $master = new MasterSupervisor($options, function ($type, $line) {
            $this->output->write($type === 'meridian' ? "<info>[meridian]</info> {$line}".PHP_EOL : $line);
        });

        $this->components->info("Meridian started successfully ({$master->name}).");

        foreach ($options as $option) {
            $this->components->twoColumnDetail(
                $option->name,
                sprintf('%s [%s] %d-%d processes, balance: %s',
                    $option->connection,
                    implode(', ', $option->queues),
                    $option->minProcesses,
                    $option->maxProcesses,
                    $option->balance ?: 'off',
                )
            );
        }

        return $master->run();
    }
}
