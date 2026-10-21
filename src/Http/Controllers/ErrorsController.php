<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Meridian;
use IsoDoss\Meridian\Models\MonitoredJob;
use IsoDoss\Meridian\Repositories\ErrorGroupRepository;
use IsoDoss\Meridian\Support\Migrations;

class ErrorsController extends Controller
{
    use RendersPages;

    public function __construct(protected ErrorGroupRepository $errors) {}

    public function index(Request $request): View|Response
    {
        $queue = (string) $request->query('queue', '');
        $canManage = Meridian::canManage($request);

        $groups = array_map(fn (array $group) => $group + [
            'url' => route('meridian.jobs.index', ['status' => MonitoredJob::FAILED, 'error' => $group['fingerprint']]),
            'sample_url' => route('meridian.jobs.show', $group['sample']),
            'retry_url' => $canManage ? route('meridian.jobs.retry-all') : null,
        ], $this->errors->groups($queue ?: null));

        return $this->page('errors.index', 'Errors/Index', [
            'groups' => $groups,
            'ready' => Migrations::hasJobInsights(),
            'queues' => MonitoredJob::query()->where('status', MonitoredJob::FAILED)->distinct()->orderBy('queue')->pluck('queue'),
            'queue' => $queue,
        ], fn (array $data) => [
            'groups' => $data['groups'],
            'ready' => $data['ready'],
            'queues' => $data['queues']->values(),
            'filters' => ['queue' => $queue],
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        return response()->json($this->errors->groups($request->query('queue') ?: null));
    }
}
