<?php

namespace Kakodjenou\Meridian\Http\Controllers;

use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Kakodjenou\Meridian\Models\MonitoredJob;

class BatchesController extends Controller
{
    public function index(BatchRepository $batches): View
    {
        return view('meridian::batches.index', [
            'batches' => $this->safely(fn () => $batches->get(50, request('before'))) ?? [],
        ]);
    }

    public function show(BatchRepository $batches, string $id): View
    {
        $batch = $this->safely(fn () => $batches->find($id));

        abort_if($batch === null, 404);

        return view('meridian::batches.show', [
            'batch' => $batch,
            'jobs' => MonitoredJob::query()->where('batch_id', $id)->orderByDesc('id')->paginate(50),
        ]);
    }

    public function retry(string $id): RedirectResponse
    {
        Artisan::call('queue:retry-batch', ['id' => [$id]]);

        return back()->with('meridian.success', 'The failed jobs of the batch have been pushed back onto the queue.');
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
