<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\PendingJobsImporter;

class DashboardController extends Controller
{
    use RendersPages;

    public function __construct(
        protected QueueRepository $queues,
        protected MetricsRepository $metrics,
    ) {}

    public function index(): View|Response
    {
        app(PendingJobsImporter::class)->import();

        return $this->page('dashboard', 'Dashboard', [
            'stats' => $this->stats(),
            'workload' => $this->queues->workload(),
            'throughput' => $this->metrics->throughputPerMinute(60),
            'supervisors' => Supervisor::query()->alive()->orderBy('name')->get(),
            'workers' => Worker::query()->alive()->orderBy('name')->get(),
        ], fn (array $data) => [
            'stats' => $data['stats'],
            'workload' => $data['workload']->values(),
            'throughput' => collect($data['throughput'])->map(fn (int $count, int $time) => ['time' => $time, 'count' => $count])->values(),
            'supervisors' => $data['supervisors']->map(fn (Supervisor $supervisor) => Present::supervisor($supervisor))->values(),
            'workers' => $data['workers']->map(fn (Worker $worker) => Present::worker($worker))->values(),
        ]);
    }

    public function stats(): array
    {
        $workload = $this->queues->workload();
        $longest = $workload->sortByDesc('wait')->first();

        return [
            'status' => $this->queues->status(),
            'jobsPerMinute' => $this->metrics->jobsPerMinute(60),
            'recentJobs' => $this->metrics->processedSince(60),
            'failedJobs' => $this->metrics->failedSince(10080),
            'pendingJobs' => (int) $workload->sum('pending'),
            'delayedJobs' => (int) $workload->sum('delayed'),
            'processing' => MonitoredJob::query()->where('status', MonitoredJob::PROCESSING)->count(),
            'processes' => Worker::query()->alive()->count(),
            'maxWait' => $longest ? ['queue' => $longest['connection'].':'.$longest['queue'], 'seconds' => $longest['wait']] : null,
        ];
    }

    public function apiStats(): JsonResponse
    {
        return response()->json($this->stats());
    }

    public function apiWorkload(): JsonResponse
    {
        return response()->json($this->queues->workload());
    }
}
