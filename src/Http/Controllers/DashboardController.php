<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Models\Supervisor;
use IsoDoss\Meridian\Models\Worker;
use IsoDoss\Meridian\Repositories\MetricsRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\PendingJobsImporter;

class DashboardController extends Controller
{
    public function __construct(
        protected QueueRepository $queues,
        protected MetricsRepository $metrics,
    ) {}

    public function index(): View
    {
        app(PendingJobsImporter::class)->import();

        return view('meridian::dashboard', [
            'stats' => $this->stats(),
            'workload' => $this->queues->workload(),
            'throughput' => $this->metrics->throughputPerMinute(60),
            'supervisors' => Supervisor::query()->alive()->orderBy('name')->get(),
            'workers' => Worker::query()->alive()->orderBy('name')->get(),
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
