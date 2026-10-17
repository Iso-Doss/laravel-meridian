<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\PendingJobsImporter;

class JobsController extends Controller
{
    public function index(Request $request, ?string $status = null): View
    {
        app(PendingJobsImporter::class)->import();

        return view('meridian::jobs.index', [
            'status' => $status,
            'jobs' => $this->query($request, $status)->paginate(50)->withQueryString(),
            'queues' => MonitoredJob::query()->distinct()->orderBy('queue')->pluck('queue'),
            'counts' => MonitoredJob::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(string $uuid): View
    {
        return view('meridian::jobs.show', [
            'job' => MonitoredJob::query()->where('uuid', $uuid)->firstOrFail(),
        ]);
    }

    /**
     * Retry a failed job: through "queue:retry" when it is still in the
     * failed_jobs table, otherwise by pushing its payload again.
     */
    public function retry(string $uuid): RedirectResponse
    {
        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();

        abort_unless($job->status === MonitoredJob::FAILED, 422, 'Only failed jobs can be retried.');

        if (app('queue.failer')->find($uuid)) {
            Artisan::call('queue:retry', ['id' => [$uuid]]);
        } elseif ($job->payload) {
            $payload = json_decode($job->payload, true);
            $payload['attempts'] = 0;

            Queue::connection($job->connection)->pushRaw(json_encode($payload), $job->queue);

            $job->update(['status' => MonitoredJob::PENDING, 'retries' => $job->retries + 1, 'failed_at' => null, 'available_at' => now()]);
        } else {
            return back()->with('meridian.error', 'This job cannot be retried: its payload was not stored.');
        }

        return back()->with('meridian.success', 'The job has been pushed back onto the queue.');
    }

    /**
     * Delete a pending job from the queue, or forget a failed job.
     */
    public function destroy(string $uuid, QueueRepository $queues): RedirectResponse
    {
        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();

        abort_if($job->status === MonitoredJob::PROCESSING, 422, 'A job being processed cannot be deleted.');

        if ($job->status === MonitoredJob::PENDING && $job->job_id) {
            $queues->jobsTable($job->connection)->where('id', $job->job_id)->whereNull('reserved_at')->delete();
        }

        if ($job->status === MonitoredJob::FAILED) {
            app('queue.failer')->forget($uuid);
        }

        $job->delete();

        return redirect()->route('meridian.jobs.index')->with('meridian.success', 'The job has been deleted.');
    }

    public function apiIndex(Request $request, ?string $status = null): JsonResponse
    {
        return response()->json(
            $this->query($request, $status)->paginate(50, ['id', 'uuid', 'connection', 'queue', 'name', 'status', 'attempts', 'tags', 'runtime', 'wait', 'queued_at', 'completed_at', 'failed_at'])
        );
    }

    public function apiShow(string $uuid): JsonResponse
    {
        return response()->json(MonitoredJob::query()->where('uuid', $uuid)->firstOrFail());
    }

    /**
     * @return Builder<MonitoredJob>
     */
    protected function query(Request $request, ?string $status)
    {
        return MonitoredJob::query()
            ->select(['id', 'uuid', 'connection', 'queue', 'name', 'status', 'attempts', 'retries', 'tags', 'batch_id', 'runtime', 'wait', 'queued_at', 'available_at', 'reserved_at', 'completed_at', 'failed_at', 'updated_at'])
            ->status($status)
            ->search($request->query('search'))
            ->when($request->query('queue'), fn ($q, $queue) => $q->where('queue', $queue))
            ->when($request->query('batch'), fn ($q, $batch) => $q->where('batch_id', $batch))
            ->orderByDesc('id');
    }
}
