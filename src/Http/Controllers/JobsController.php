<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\FailedJobRetrier;
use IsoDoss\Meridian\Support\PendingJobsImporter;
use IsoDoss\Meridian\Support\Translator;

class JobsController extends Controller
{
    use RendersPages;

    public function index(Request $request, ?string $status = null): View|Response
    {
        app(PendingJobsImporter::class)->import();

        return $this->page('jobs.index', 'Jobs/Index', [
            'status' => $status,
            'jobs' => $this->query($request, $status)->paginate(50)->withQueryString(),
            'queues' => MonitoredJob::query()->distinct()->orderBy('queue')->pluck('queue'),
            'counts' => MonitoredJob::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ], fn (array $data) => [
            'status' => $data['status'],
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'queue' => (string) $request->query('queue', ''),
            ],
            'jobs' => Present::paginated($data['jobs'], fn (MonitoredJob $job) => Present::job($job)),
            'queues' => $data['queues']->values(),
            'counts' => collect(MonitoredJob::STATUSES)->mapWithKeys(fn (string $s) => [$s => (int) ($data['counts'][$s] ?? 0)]),
            'retry_all_url' => route('meridian.jobs.retry-all'),
        ]);
    }

    public function show(string $uuid): View|Response
    {
        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();

        return $this->page('jobs.show', 'Jobs/Show', ['job' => $job], fn (array $data) => [
            'job' => Present::job($data['job'], details: true),
        ]);
    }

    /**
     * Retry a failed job.
     */
    public function retry(string $uuid, FailedJobRetrier $retrier): RedirectResponse
    {
        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();

        abort_unless($job->status === MonitoredJob::FAILED, 422, 'Only failed jobs can be retried.');

        if (! $retrier->retry($job)) {
            return back()->with('meridian.error', Translator::get('This job cannot be retried: its payload was not stored.'));
        }

        return back()->with('meridian.success', Translator::get('The job has been pushed back onto the queue.'));
    }

    /**
     * Retry every failed job matching the filters of the list (queue, search).
     */
    public function retryAll(Request $request, FailedJobRetrier $retrier): RedirectResponse
    {
        $result = $retrier->retryQuery(
            MonitoredJob::query()
                ->search($request->input('search'))
                ->when($request->input('queue'), fn ($query, $queue) => $query->where('queue', $queue))
        );

        $redirect = back();

        if ($result['retried'] > 0) {
            $redirect->with('meridian.success', Translator::get(':count failed job(s) pushed back onto the queue.', ['count' => $result['retried']]));
        }

        if ($result['skipped'] > 0) {
            $redirect->with('meridian.error', Translator::get(':count job(s) could not be retried: their payload was not stored.', ['count' => $result['skipped']]));
        } elseif ($result['retried'] === 0) {
            $redirect->with('meridian.error', Translator::get('There is no failed job to retry.'));
        }

        return $redirect;
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

        return redirect()->route('meridian.jobs.index')->with('meridian.success', Translator::get('The job has been deleted.'));
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
