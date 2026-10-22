<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Supervisor\SupervisorOptions;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Support\PrimaryKey;
use Throwable;

/**
 * Checks the installation of Meridian and tells how to fix what is wrong.
 */
class DoctorCommand extends Command
{
    protected $signature = 'meridian:doctor';

    protected $description = 'Check the installation of Meridian';

    /**
     * The tables and columns of each migration, with the release that added them.
     */
    public const MIGRATIONS = [
        ['meridian_jobs', null, 'the jobs (v1.0.0)', true],
        ['meridian_workers', null, 'the workers (v1.0.0)', true],
        ['meridian_supervisors', null, 'the supervisors (v1.0.0)', true],
        ['meridian_metrics', null, 'the metrics (v1.0.0)', true],
        ['meridian_monitored_tags', null, 'the monitored tags', false],
        ['meridian_job_retries', null, 'the retry history', false],
        ['meridian_jobs', 'fingerprint', 'the error groups and job progress (v1.7.0)', false],
        ['meridian_audit_logs', null, 'the audit log (v1.7.0)', false],
        ['meridian_schedule_runs', null, 'the scheduled tasks (v1.8.0)', false],
        ['meridian_jobs', 'cancel_requested_at', 'the cancellation of running jobs (v1.9.0)', false],
        ['meridian_jobs', 'parent_uuid', 'the origin of the jobs (v1.12.0)', false],
    ];

    protected int $failures = 0;

    protected int $warnings = 0;

    public function handle(): int
    {
        $this->components->info('Checking the installation of Meridian');

        if ($this->database() && $this->migrations()) {
            $this->volume();
        }

        $this->queues();
        $this->supervisors();
        $this->scheduler();
        $this->payloads();
        $this->access();

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} problem(s) to fix, {$this->warnings} warning(s).");

            return self::FAILURE;
        }

        $this->warnings > 0
            ? $this->components->warn("No problem, {$this->warnings} warning(s).")
            : $this->components->info('Everything looks good.');

        return self::SUCCESS;
    }

    protected function database(): bool
    {
        try {
            $connection = DB::connection(config('meridian.database'));
            $connection->getPdo();
        } catch (Throwable $e) {
            $this->problem('Database', 'Meridian cannot connect to its database: '.$e->getMessage());

            return false;
        }

        $this->pass('Database', $connection->getName().' ('.$connection->getDriverName().')');

        return true;
    }

    /**
     * @return bool Whether the tables of Meridian exist.
     */
    protected function migrations(): bool
    {
        $missing = [];

        foreach (self::MIGRATIONS as [$table, $column, $feature, $required]) {
            if (Migrations::has($table, $column)) {
                continue;
            }

            if ($required) {
                $this->problem('Migrations', "The table {$table} is missing: run php artisan migrate.");

                return false;
            }

            $missing[] = $feature;
        }

        $missing === []
            ? $this->pass('Migrations', 'up to date')
            : $this->warning('Migrations', 'Not run yet for '.implode(', ', $missing).': publish them (php artisan vendor:publish --tag=meridian-migrations) and run php artisan migrate.');

        $type = PrimaryKey::of('meridian_jobs') ?? 'auto-incrementing integers';
        $this->pass('Primary keys', $type === 'uuid' ? 'UUID' : ($type === 'ulid' ? 'ULID' : $type));

        return true;
    }

    protected function volume(): void
    {
        $count = Meridian::table('jobs')->count();
        $label = number_format($count).' recorded job(s)';

        $count > 1_000_000
            ? $this->warning('Volume', "{$label}: lower meridian.trim.completed, or check that meridian:prune runs.")
            : $this->pass('Volume', $label);
    }

    protected function queues(): void
    {
        $connections = collect(config('queue.connections', []))
            ->keys()
            ->filter(fn ($name) => Meridian::watches((string) $name))
            ->values();

        if ($connections->isEmpty()) {
            $this->problem('Queues', 'No queue connection is recorded: Meridian records the connections using the "database" driver (or those listed in meridian.connections).');

            return;
        }

        foreach ($connections as $name) {
            $config = config("queue.connections.{$name}");
            $table = $config['table'] ?? 'jobs';

            $this->tableExists($config['connection'] ?? null, $table)
                ? $this->pass("Queue {$name}", "table {$table}".($name === config('queue.default') ? ', default connection' : ''))
                : $this->problem("Queue {$name}", "The table {$table} is missing: run php artisan make:queue-table and php artisan migrate.");
        }

        if (! $connections->contains(config('queue.default'))) {
            $this->warning('Default queue', 'The default connection ('.config('queue.default').') is not recorded: set QUEUE_CONNECTION=database to monitor the jobs dispatched without a connection.');
        }

        $failed = config('queue.failed', []);

        if (in_array($failed['driver'] ?? null, ['database', 'database-uuids'], true)) {
            $this->tableExists($failed['database'] ?? null, $failed['table'] ?? 'failed_jobs')
                ? $this->pass('Failed jobs', 'table '.($failed['table'] ?? 'failed_jobs'))
                : $this->warning('Failed jobs', 'The table '.($failed['table'] ?? 'failed_jobs').' is missing: run php artisan make:queue-failed-table and php artisan migrate.');
        }
    }

    protected function supervisors(): void
    {
        $environment = app()->environment();
        $supervisors = SupervisorOptions::forEnvironment($environment);

        if ($supervisors === []) {
            $this->warning('Supervisors', "None configured for the \"{$environment}\" environment: add it to meridian.environments (or a \"*\" entry).");

            return;
        }

        foreach ($supervisors as $options) {
            if (! Meridian::watches($options->connection)) {
                $this->warning("Supervisor {$options->name}", "Its connection ({$options->connection}) is not recorded by Meridian.");
            }
        }

        try {
            $running = Supervisor::query()->alive()->count();
        } catch (Throwable) {
            $running = 0;
        }

        $running > 0
            ? $this->pass('Supervisors', count($supervisors).' configured, '.$running.' running')
            : $this->warning('Supervisors', count($supervisors).' configured, none running: start php artisan meridian (with Supervisor or systemd in production).');
    }

    protected function scheduler(): void
    {
        if (! config('meridian.schedule', true)) {
            $this->pass('Scheduler', 'disabled (meridian.schedule): schedule meridian:snapshot and meridian:prune yourself');

            return;
        }

        try {
            $last = Cache::get(SnapshotCommand::LAST_RUN);
        } catch (Throwable $e) {
            $this->warning('Scheduler', 'The cache cannot be read ('.$e->getMessage().'): Meridian cannot tell whether the scheduler runs.');

            return;
        }

        if ($last === null) {
            $this->warning('Scheduler', 'meridian:snapshot never ran: run php artisan schedule:work locally, or add the cron entry of the scheduler in production.');
        } elseif (time() - (int) $last > 15 * 60) {
            $this->warning('Scheduler', 'meridian:snapshot last ran '.now()->subSeconds(time() - (int) $last)->diffForHumans().': check that the scheduler still runs.');
        } else {
            $this->pass('Scheduler', 'meridian:snapshot ran '.now()->subSeconds(time() - (int) $last)->diffForHumans());
        }
    }

    protected function payloads(): void
    {
        if (! config('meridian.store_payload')) {
            $this->pass('Payloads', 'not stored');

            return;
        }

        $keys = (array) config('meridian.redact', []);

        $keys === []
            ? $this->warning('Payloads', 'Stored without redaction: list the sensitive keys in meridian.redact.')
            : $this->pass('Payloads', 'stored, '.count($keys).' key(s) redacted');
    }

    protected function access(): void
    {
        if (! app()->environment('local') && Meridian::$authUsing === null) {
            $this->warning('Dashboard', 'Only reachable in the local environment: define who can open it with Meridian::auth() (see App\Providers\MeridianServiceProvider).');
        } else {
            $this->pass('Dashboard', url(config('meridian.route.web.prefix', 'meridian')));
        }

        if (config('meridian.prometheus.enabled') && ! config('meridian.prometheus.token')) {
            $this->warning('Prometheus', 'Enabled without MERIDIAN_PROMETHEUS_TOKEN: scrapers need the dashboard authorization.');
        }
    }

    protected function tableExists(?string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection ?? DB::getDefaultConnection())->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function pass(string $check, string $detail): void
    {
        $this->components->twoColumnDetail($check, "<fg=green;options=bold>OK</> <fg=gray>{$detail}</>");
    }

    protected function warning(string $check, string $detail): void
    {
        $this->warnings++;
        $this->components->twoColumnDetail($check, '<fg=yellow;options=bold>WARN</>');
        $this->line("  <fg=yellow>↳</> {$detail}");
    }

    protected function problem(string $check, string $detail): void
    {
        $this->failures++;
        $this->components->twoColumnDetail($check, '<fg=red;options=bold>FAIL</>');
        $this->line("  <fg=red>↳</> {$detail}");
    }
}
