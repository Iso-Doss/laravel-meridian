<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Repositories\MonitoredTagRepository;
use IsoDoss\Meridian\Support\Translator;

class MonitoringController extends Controller
{
    use RendersPages;

    public function __construct(protected MonitoredTagRepository $tags) {}

    public function index(): View|Response
    {
        return $this->page('monitoring.index', 'Monitoring/Index', [
            'tags' => $this->tags->withCounts(),
            'ready' => $this->tags->isReady(),
        ], fn (array $data) => [
            'tags' => array_map(fn (array $tag) => $tag + [
                'url' => route('meridian.jobs.index', ['tag' => $tag['tag']]),
            ], $data['tags']),
            'ready' => $data['ready'],
            'store_url' => route('meridian.monitoring.store'),
            'destroy_url' => route('meridian.monitoring.destroy'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tag = trim((string) $request->validate(['tag' => ['required', 'string', 'max:255']])['tag']);

        $this->tags->monitor($tag);

        return back()->with('meridian.success', Translator::get('The tag :tag is now monitored.', ['tag' => $tag]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $tag = (string) $request->validate(['tag' => ['required', 'string']])['tag'];

        $this->tags->stopMonitoring($tag);

        return back()->with('meridian.success', Translator::get('The tag :tag is no longer monitored.', ['tag' => $tag]));
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json($this->tags->withCounts());
    }
}
