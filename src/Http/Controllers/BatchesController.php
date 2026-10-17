<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Http\Presenters\Present;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Support\Translator;

class BatchesController extends Controller
{
    use RendersPages;

    public function index(BatchRepository $batches): View|Response
    {
        return $this->page('batches.index', 'Batches/Index', [
            'batches' => $this->safely(fn () => $batches->get(50, request('before'))) ?? [],
        ], fn (array $data) => [
            'batches' => array_values(array_map(fn (Batch $batch) => Present::batch($batch), $data['batches'])),
        ]);
    }

    public function show(BatchRepository $batches, string $id): View|Response
    {
        $batch = $this->safely(fn () => $batches->find($id));

        abort_if($batch === null, 404);

        return $this->page('batches.show', 'Batches/Show', [
            'batch' => $batch,
            'jobs' => MonitoredJob::query()->where('batch_id', $id)->orderByDesc('id')->paginate(50),
        ], fn (array $data) => [
            'batch' => Present::batch($data['batch']),
            'jobs' => Present::paginated($data['jobs'], fn (MonitoredJob $job) => Present::job($job)),
        ]);
    }

    public function retry(string $id): RedirectResponse
    {
        Artisan::call('queue:retry-batch', ['id' => [$id]]);

        return back()->with('meridian.success', Translator::get('The failed jobs of the batch have been pushed back onto the queue.'));
    }

    protected function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException) {
            // The job_batches table does not exist.
            return null;
        }
    }
}
