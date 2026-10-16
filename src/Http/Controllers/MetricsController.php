<?php

namespace Kakodjenou\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kakodjenou\Meridian\Repositories\MetricsRepository;

class MetricsController extends Controller
{
    public const PERIODS = [60 => '1 hour', 360 => '6 hours', 1440 => '24 hours', 10080 => '7 days'];

    public function index(Request $request, MetricsRepository $metrics, string $type = 'jobs'): View
    {
        $type = $type === 'queues' ? 'queues' : 'jobs';
        $minutes = array_key_exists((int) $request->query('period'), self::PERIODS) ? (int) $request->query('period') : 1440;

        return view('meridian::metrics.index', [
            'type' => $type,
            'minutes' => $minutes,
            'periods' => self::PERIODS,
            'rows' => $metrics->breakdown($type === 'jobs' ? 'name' : 'queue', $minutes),
        ]);
    }

    public function show(Request $request, MetricsRepository $metrics, string $type, string $name): View
    {
        $type = $type === 'queues' ? 'queues' : 'jobs';

        return view('meridian::metrics.show', [
            'type' => $type,
            'name' => $name,
            'snapshots' => $metrics->snapshots($type === 'jobs' ? 'job' : 'queue', $name),
        ]);
    }
}
