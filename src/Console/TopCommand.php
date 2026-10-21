<?php

namespace IsoDoss\Meridian\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use IsoDoss\Meridian\Http\Controllers\DashboardController;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Repositories\ScheduleRepository;
use IsoDoss\Meridian\Support\Format;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TopCommand extends Command
{
    protected $signature = 'meridian:top
                            {--interval=2 : Seconds between two refreshes}
                            {--once : Show the view once and exit}';

    protected $description = 'Live view of the queues, workers, running jobs and errors in the terminal';

    public function handle(): int
    {
        $output = $this->output->getOutput();
        $section = $output instanceof ConsoleOutputInterface && ! $this->option('once') ? $output->section() : null;
        $interval = max(1, (int) $this->option('interval'));

        do {
            $buffer = new BufferedOutput($output->getVerbosity(), $output->isDecorated());
            $this->render($buffer, $interval);

            if ($section) {
                $section->overwrite($buffer->fetch());
                sleep($interval);
            } else {
                $output->writeln(explode("\n", rtrim($buffer->fetch(), "\n")));
            }
        } while ($section !== null);

        return self::SUCCESS;
    }

    protected function render(OutputInterface $out, int $interval): void
    {
        $stats = app(DashboardController::class)->stats();
        $status = match ($stats['status']) {
            'running' => '<fg=green>● running</>',
            'paused' => '<fg=yellow>● paused</>',
            default => '<fg=red>● inactive</>',
        };

        $out->writeln(sprintf('<options=bold>Meridian</> · %s · %s · <fg=gray>%s</>', config('app.name'), $status, now()->format('Y-m-d H:i:s')));
        $out->writeln(sprintf(
            'Jobs/min <options=bold>%s</>  ·  Past hour <options=bold>%d</>  ·  Pending <options=bold>%d</> (+%d delayed)  ·  Processing <options=bold>%d</>  ·  Failed (7 days) <options=bold>%s</>  ·  Workers <options=bold>%d</>',
            Format::number($stats['jobsPerMinute']),
            $stats['recentJobs'],
            $stats['pendingJobs'],
            $stats['delayedJobs'],
            $stats['processing'],
            $stats['failedJobs'] > 0 ? "<fg=red>{$stats['failedJobs']}</>" : '0',
            $stats['processes'],
        ));

        $this->section($out, 'Queues', ['Queue', 'Pending', 'Delayed', 'Reserved', 'Processes', 'Wait'], app(QueueRepository::class)->workload()->map(fn (array $queue) => [
            $queue['connection'].':'.$queue['queue'],
            $queue['pending'],
            $queue['delayed'],
            $queue['reserved'],
            $queue['processes'],
            Format::seconds($queue['wait']),
        ])->all(), [1, 2, 3, 4]);

        $running = MonitoredJob::query()->where('status', MonitoredJob::PROCESSING)->orderBy('reserved_at')->limit(10)->get();

        $this->section($out, 'Running jobs', ['Job', 'Queue', 'Running for', 'Progress'], $running->map(fn (MonitoredJob $job) => [
            $job->shortName(),
            $job->connection.':'.$job->queue,
            Format::seconds($job->reserved_at ? max(0, time() - $job->reserved_at->getTimestamp()) : null),
            $this->progress($job),
        ])->all());

        $this->section($out, 'Supervisors', ['Supervisor', 'Host', 'Processes', 'Status'], Supervisor::query()->alive()->orderBy('name')->get()->map(fn (Supervisor $supervisor) => [
            $supervisor->shortName(),
            $supervisor->hostname,
            $supervisor->totalProcesses(),
            $supervisor->status === 'running' ? '<fg=green>running</>' : '<fg=yellow>'.$supervisor->status.'</>',
        ])->all(), [2]);

        $this->section($out, 'Errors', ['Error', 'Jobs', 'Count', 'Last seen'], collect(app(ErrorGroupRepository::class)->groups(limit: 5))->map(fn (array $group) => [
            class_basename($group['class']).': '.Str::limit($group['message'], 70),
            implode(', ', array_map('class_basename', $group['jobs'])),
            "<fg=red>{$group['count']}</>",
            Format::ago(now()->setTimestamp($group['last_seen'])),
        ])->all(), [2]);

        $failing = collect(app(ScheduleRepository::class)->tasks())->whereIn('health', ['failed', 'missed']);

        if ($failing->isNotEmpty()) {
            $this->section($out, 'Scheduled tasks needing attention', ['Task', 'Status'], $failing->map(fn (array $task) => [
                Str::limit($task['name'], 80),
                "<fg=red>{$task['health']}</>",
            ])->all());
        }

        if (! $this->option('once')) {
            $out->writeln("<fg=gray>Refreshing every {$interval} s · Ctrl+C to quit</>");
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $rightAligned
     */
    protected function section(OutputInterface $out, string $title, array $headers, array $rows, array $rightAligned = []): void
    {
        $out->writeln('');
        $out->writeln("<options=bold>{$title}</>");

        if ($rows === []) {
            $out->writeln('<fg=gray>  none</>');

            return;
        }

        $width = fn (mixed $cell) => Helper::width(Helper::removeDecoration($out->getFormatter(), (string) $cell));
        $widths = [];

        foreach (array_merge([$headers], $rows) as $row) {
            foreach (array_values($row) as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, $width($cell));
            }
        }

        $line = function (array $row, bool $header = false) use ($widths, $width, $rightAligned) {
            $cells = [];

            foreach (array_values($row) as $index => $cell) {
                $padding = str_repeat(' ', $widths[$index] - $width($cell));
                $cells[] = in_array($index, $rightAligned, true) ? $padding.$cell : $cell.$padding;
            }

            $text = rtrim(implode('   ', $cells));

            return $header ? "<fg=gray>{$text}</>" : $text;
        };

        $out->writeln($line($headers, header: true));

        foreach ($rows as $row) {
            $out->writeln($line($row));
        }
    }

    protected function progress(MonitoredJob $job): string
    {
        if ($job->progress === null) {
            return '—';
        }

        $filled = (int) round($job->progress / 10);
        $remaining = Present::remaining($job);

        return '<fg=cyan>'.str_repeat('█', $filled).'</><fg=gray>'.str_repeat('░', 10 - $filled).'</> '.$job->progress.'%'
            .($remaining !== null ? ' · ~'.Format::seconds($remaining).' left' : '');
    }
}
