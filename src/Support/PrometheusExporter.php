<?php

namespace IsoDoss\Meridian\Support;

use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Repositories\ScheduleRepository;

/**
 * The metrics of Meridian in the Prometheus text format (also read by the
 * OpenTelemetry Collector, Datadog, Grafana Agent / Alloy, Victoria Metrics...).
 */
class PrometheusExporter
{
    /**
     * @var array<string, array{help: string, type: string, samples: array<int, array{labels: array<string, string>, value: int|float}>}>
     */
    protected array $metrics = [];

    public function __construct(
        protected QueueRepository $queues,
        protected MetricsRepository $history,
        protected ScheduleRepository $schedule,
        protected ErrorGroupRepository $errors,
    ) {}

    public function render(): string
    {
        $this->metrics = [];

        $status = $this->queues->status();
        $this->gauge('meridian_up', 'Whether Meridian workers are running (1), paused (0.5) or inactive (0).', match ($status) {
            'running' => 1,
            'paused' => 0.5,
            default => 0,
        });

        foreach ($this->queues->workload() as $queue) {
            $labels = ['connection' => $queue['connection'], 'queue' => $queue['queue']];

            $this->gauge('meridian_queue_pending_jobs', 'Jobs waiting on the queue.', $queue['pending'], $labels);
            $this->gauge('meridian_queue_delayed_jobs', 'Delayed jobs of the queue.', $queue['delayed'], $labels);
            $this->gauge('meridian_queue_reserved_jobs', 'Jobs of the queue being processed.', $queue['reserved'], $labels);
            $this->gauge('meridian_queue_wait_seconds', 'Wait time of the oldest pending job.', $queue['wait'], $labels);
            $this->gauge('meridian_queue_processes', 'Worker processes of the queue.', $queue['processes'], $labels);
        }

        $since = time() - 3600;

        $rows = MonitoredJob::query()->toBase()
            ->where(fn ($query) => $query->where('completed_at', '>=', $since)->orWhere('failed_at', '>=', $since))
            ->groupBy('connection', 'queue')
            ->selectRaw('connection, queue, COUNT(*) AS processed_jobs, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS failed_jobs, AVG(runtime) AS avg_runtime, MAX(runtime) AS max_runtime', [MonitoredJob::FAILED])
            ->get();

        foreach ($rows as $row) {
            $labels = ['connection' => (string) $row->connection, 'queue' => (string) $row->queue];

            $this->gauge('meridian_queue_jobs_processed_last_hour', 'Jobs processed (completed or failed) during the last hour.', (int) $row->processed_jobs, $labels);
            $this->gauge('meridian_queue_jobs_failed_last_hour', 'Jobs failed during the last hour.', (int) $row->failed_jobs, $labels);
            $this->gauge('meridian_queue_runtime_avg_seconds', 'Average runtime of the jobs of the last hour.', round((float) $row->avg_runtime / 1000, 3), $labels);
            $this->gauge('meridian_queue_runtime_max_seconds', 'Maximum runtime of the jobs of the last hour.', round((float) $row->max_runtime / 1000, 3), $labels);
        }

        $this->gauge('meridian_jobs_per_minute', 'Jobs processed per minute, averaged over the last hour.', $this->history->jobsPerMinute(60));
        $this->gauge('meridian_jobs_processing', 'Jobs being processed.', MonitoredJob::query()->where('status', MonitoredJob::PROCESSING)->count());
        $this->gauge('meridian_failed_jobs', 'Failed jobs recorded by Meridian (past 7 days).', $this->history->failedSince(10080));
        $this->gauge('meridian_error_groups', 'Distinct errors of the failed jobs.', count($this->errors->groups(limit: 1000)));

        foreach (Worker::query()->alive()->get(['status'])->countBy('status') as $workerStatus => $count) {
            $this->gauge('meridian_workers', 'Live worker processes.', $count, ['status' => (string) $workerStatus]);
        }

        foreach (Supervisor::query()->alive()->get(['status'])->countBy('status') as $supervisorStatus => $count) {
            $this->gauge('meridian_supervisors', 'Live supervisors.', $count, ['status' => (string) $supervisorStatus]);
        }

        foreach ($this->schedule->tasks() as $task) {
            $labels = ['task' => $task['name']];

            $this->gauge('meridian_schedule_task_healthy', 'Whether the last run of the scheduled task succeeded and none was missed (1) or not (0).', in_array($task['health'], ['success', 'running', 'skipped'], true) ? 1 : 0, $labels);

            if ($task['last_run']) {
                $this->gauge('meridian_schedule_task_last_run_timestamp_seconds', 'Start time of the last run of the scheduled task.', $task['last_run']['started_at'], $labels);
                $this->gauge('meridian_schedule_task_last_runtime_seconds', 'Runtime of the last run of the scheduled task.', round((int) $task['last_run']['runtime'] / 1000, 3), $labels);
            }
        }

        return $this->format();
    }

    /**
     * @param  array<string, string>  $labels
     */
    protected function gauge(string $name, string $help, int|float $value, array $labels = []): void
    {
        $this->metrics[$name] ??= ['help' => $help, 'type' => 'gauge', 'samples' => []];
        $this->metrics[$name]['samples'][] = ['labels' => $labels, 'value' => $value];
    }

    protected function format(): string
    {
        $lines = [];

        foreach ($this->metrics as $name => $metric) {
            $lines[] = "# HELP {$name} {$metric['help']}";
            $lines[] = "# TYPE {$name} {$metric['type']}";

            foreach ($metric['samples'] as $sample) {
                $labels = collect($sample['labels'])
                    ->map(fn (string $value, string $key) => $key.'="'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"')
                    ->implode(',');

                $lines[] = $name.($labels === '' ? '' : '{'.$labels.'}').' '.$this->number($sample['value']);
            }
        }

        return implode("\n", $lines)."\n";
    }

    protected function number(int|float $value): string
    {
        return is_int($value) || floor($value) === $value ? (string) (int) $value : rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}
