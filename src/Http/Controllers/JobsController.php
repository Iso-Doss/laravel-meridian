<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\AuditLogRepository;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Repositories\JobRetryRepository;
use IsoDoss\Meridian\Repositories\QueueRepository;
use IsoDoss\Meridian\Support\Exporter;
use IsoDoss\Meridian\Support\FailedJobRetrier;
use IsoDoss\Meridian\Support\JobEditor;
use IsoDoss\Meridian\Support\Migrations;
use IsoDoss\Meridian\Support\PendingJobsImporter;
use IsoDoss\Meridian\Support\Translator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobsController extends Controller
{
    use RendersPages;

    public function __construct(protected AuditLogRepository $audit) {}

    public function index(Request $request, ?string $status = null): View|Response
    {
        app(PendingJobsImporter::class)->import();

        return $this->page('jobs.index', 'Jobs/Index', [
            'status' => $status,
            'jobs' => $this->query($request, $status)->paginate(50)->withQueryString(),
            'queues' => MonitoredJob::query()->distinct()->orderBy('queue')->pluck('queue'),
            'counts' => MonitoredJob::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'error' => $request->query('error') ? app(ErrorGroupRepository::class)->find((string) $request->query('error')) : null,
        ], fn (array $data) => [
            'status' => $data['status'],
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'queue' => (string) $request->query('queue', ''),
                'tag' => (string) $request->query('tag', ''),
                'error' => (string) $request->query('error', ''),
            ],
            'error' => $data['error'],
            'jobs' => Present::paginated($data['jobs'], fn (MonitoredJob $job) => Present::job($job)),
            'queues' => $data['queues']->values(),
            'counts' => collect(MonitoredJob::STATUSES)->mapWithKeys(fn (string $s) => [$s => (int) ($data['counts'][$s] ?? 0)]),
            'retry_all_url' => Meridian::canManage($request) ? route('meridian.jobs.retry-all') : null,
            'export_url' => route('meridian.jobs.export'),
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

        $this->audit->record('job.retried', $job->uuid, ['job' => $job->name, 'queue' => $job->connection.':'.$job->queue]);

        return back()->with('meridian.success', Translator::get('The job has been pushed back onto the queue.'));
    }

    /**
     * Change the data of a failed job, then retry it.
     */
    public function retryEdited(Request $request, string $uuid, JobEditor $editor): RedirectResponse
    {
        abort_unless(Meridian::canEditJobs($request), 403);

        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();
        $changed = $editor->retry($job, (array) $request->input('data', []));

        $this->audit->record('job.edited', $job->uuid, [
            'job' => $job->name,
            'queue' => $job->connection.':'.$job->queue,
            'changed' => $changed === [] ? '—' : implode(', ', $changed),
        ]);

        return back()->with('meridian.success', Translator::get('The job has been pushed back onto the queue.'));
    }

    /**
     * Retry every failed job matching the filters of the list (queue, search, tag, error group).
     */
    public function retryAll(Request $request, FailedJobRetrier $retrier): RedirectResponse
    {
        // Read before the retry: the group no longer exists afterwards.
        $error = $request->input('error') ? app(ErrorGroupRepository::class)->find((string) $request->input('error')) : null;

        $result = $retrier->retryQuery(
            MonitoredJob::query()
                ->search($request->input('search'))
                ->when($request->input('queue'), fn ($query, $queue) => $query->where('queue', $queue))
                ->when($request->input('tag'), fn ($query, $tag) => $query->withTag((string) $tag))
                ->when($request->input('error') && Migrations::hasJobInsights(), fn ($query) => $query->where('fingerprint', (string) $request->input('error')))
        );

        if ($result['retried'] > 0) {
            $this->audit->record('jobs.retried', null, [
                'count' => $result['retried'],
                'queue' => $request->input('queue'),
                'search' => $request->input('search'),
                'tag' => $request->input('tag'),
                'error' => $error ? $error['class'].': '.Str::limit($error['message'], 120) : $request->input('error'),
            ]);
        }

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
     * Delete a pending job from the queue, forget a failed job, or ask a
     * running job to stop.
     */
    public function destroy(string $uuid, QueueRepository $queues): RedirectResponse
    {
        $job = MonitoredJob::query()->where('uuid', $uuid)->firstOrFail();

        if ($job->status === MonitoredJob::PROCESSING) {
            return $this->cancelRunning($job);
        }

        if ($job->status === MonitoredJob::PENDING && $job->job_id) {
            $queues->jobsTable($job->connection)->where('id', $job->job_id)->whereNull('reserved_at')->delete();
        }

        if ($job->status === MonitoredJob::FAILED) {
            app('queue.failer')->forget($uuid);
        }

        $job->delete();
        app(JobRetryRepository::class)->forget([$uuid]);

        $this->audit->record($job->status === MonitoredJob::PENDING ? 'job.cancelled' : 'job.deleted', $uuid, [
            'job' => $job->name,
            'queue' => $job->connection.':'.$job->queue,
            'status' => $job->status,
        ]);

        return redirect()->route('meridian.jobs.index')->with('meridian.success', Translator::get('The job has been deleted.'));
    }

    /**
     * The job stops when it checks $this->cancelled(), or Meridian's supervisor
     * stops its worker after "cancel_timeout" seconds.
     */
    protected function cancelRunning(MonitoredJob $job): RedirectResponse
    {
        abort_unless(Migrations::hasCancellation(), 422, 'Run the migrations of Meridian to cancel running jobs.');

        $job->update(['cancel_requested_at' => time()]);

        $this->audit->record('job.cancelled', $job->uuid, [
            'job' => $job->name,
            'queue' => $job->connection.':'.$job->queue,
            'status' => $job->status,
        ]);

        return back()->with('meridian.success', Translator::get('Cancellation requested: the job stops at its next check, or its worker is stopped within :seconds seconds when Meridian supervises it.', [
            'seconds' => (int) config('meridian.cancel_timeout', 10),
        ]));
    }

    /**
     * Download the jobs matching the filters of the list as CSV or JSON.
     */
    public function export(Request $request): StreamedResponse
    {
        $status = in_array($request->query('status'), MonitoredJob::STATUSES, true) ? (string) $request->query('status') : null;
        $json = $request->query('format') === 'json';
        $query = $this->query($request, $status)->addSelect(['exception', 'worker']);

        return Exporter::download('meridian-jobs'.($status ? "-{$status}" : ''), $json ? 'json' : 'csv', fn () => $query->lazyByIdDesc(500)->map(fn (MonitoredJob $job) => [
            'uuid' => $job->uuid,
            'job' => $job->name,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'status' => $job->status,
            'attempts' => $job->attempts,
            'retries' => $job->retries,
            'tags' => $job->tags ?? [],
            'batch_id' => $job->batch_id,
            'worker' => $job->worker,
            'queued_at' => $job->queued_at,
            'started_at' => $job->reserved_at,
            'completed_at' => $job->completed_at,
            'failed_at' => $job->failed_at,
            'wait_seconds' => $job->wait,
            'runtime_ms' => $job->runtime,
            'exception' => $json ? $job->exception : ($job->exception ? strtok($job->exception, "\n") : null),
        ]));
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
        $insights = Migrations::hasJobInsights();

        return MonitoredJob::query()
            ->select(array_merge(
                ['id', 'uuid', 'connection', 'queue', 'name', 'status', 'attempts', 'retries', 'tags', 'batch_id', 'runtime', 'wait', 'queued_at', 'available_at', 'reserved_at', 'completed_at', 'failed_at', 'updated_at'],
                $insights ? ['fingerprint', 'progress', 'progress_message'] : [],
                Migrations::hasCancellation() ? ['cancel_requested_at'] : [],
            ))
            ->status($status)
            ->search($request->query('search'))
            ->when($request->query('queue'), fn ($q, $queue) => $q->where('queue', $queue))
            ->when($request->query('tag'), fn ($q, $tag) => $q->withTag((string) $tag))
            ->when($request->query('batch'), fn ($q, $batch) => $q->where('batch_id', $batch))
            ->when($insights && $request->query('error'), fn ($q) => $q->where('fingerprint', (string) $request->query('error')))
            ->orderByDesc('id');
    }
}
