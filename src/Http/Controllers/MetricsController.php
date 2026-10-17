<?php

namespace IsoDoss\Meridian\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Response;
use IsoDoss\Meridian\Http\Controllers\Concerns\RendersPages;
use IsoDoss\Meridian\Repositories\MetricsRepository;

class MetricsController extends Controller
{
    use RendersPages;

    public const PERIODS = [60 => '1 hour', 360 => '6 hours', 1440 => '24 hours', 10080 => '7 days'];

    public function index(Request $request, MetricsRepository $metrics, string $type = 'jobs'): View|Response
    {
        $type = $type === 'queues' ? 'queues' : 'jobs';
        $minutes = array_key_exists((int) $request->query('period'), self::PERIODS) ? (int) $request->query('period') : 1440;

        return $this->page('metrics.index', 'Metrics/Index', [
            'type' => $type,
            'minutes' => $minutes,
            'periods' => self::PERIODS,
            'rows' => $metrics->breakdown($type === 'jobs' ? 'name' : 'queue', $minutes),
        ], fn (array $data) => [
            'type' => $type,
            'minutes' => $minutes,
            'periods' => collect(self::PERIODS)->map(fn (string $label, int $value) => [
                'value' => $value,
                'label' => $label,
                'url' => route('meridian.metrics.index', ['type' => $type, 'period' => $value]),
            ])->values(),
            'tabs' => collect(['jobs', 'queues'])->mapWithKeys(fn (string $tab) => [
                $tab => route('meridian.metrics.index', ['type' => $tab, 'period' => $minutes]),
            ]),
            'rows' => $data['rows']->map(function (object $row) use ($type) {
                $processed = (int) $row->processed;
                $failed = (int) $row->failed;

                return [
                    'key' => $row->key,
                    'label' => $type === 'jobs' ? class_basename((string) $row->key) : $row->key,
                    'processed' => $processed,
                    'failed' => $failed,
                    'failure_rate' => $processed + $failed > 0 ? round($failed / ($processed + $failed) * 100, 1) : 0,
                    'runtime' => $row->runtime === null ? null : (int) round((float) $row->runtime),
                    'max_runtime' => $row->max_runtime === null ? null : (int) $row->max_runtime,
                    'wait' => $row->wait === null ? null : (int) round((float) $row->wait),
                    'url' => route('meridian.metrics.show', [$type, $row->key]),
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, MetricsRepository $metrics, string $type, string $name): View|Response
    {
        $type = $type === 'queues' ? 'queues' : 'jobs';

        return $this->page('metrics.show', 'Metrics/Show', [
            'type' => $type,
            'name' => $name,
            'snapshots' => $metrics->snapshots($type === 'jobs' ? 'job' : 'queue', $name),
        ], fn (array $data) => [
            'type' => $type,
            'name' => $name,
            'back_url' => route('meridian.metrics.index', $type),
            'snapshots' => $data['snapshots']->map(fn (object $snapshot) => [
                'bucket' => (int) $snapshot->bucket,
                'processed' => (int) $snapshot->processed,
                'failed' => (int) $snapshot->failed,
                'runtime' => (int) $snapshot->runtime,
                'wait' => (int) $snapshot->wait,
            ])->values(),
        ]);
    }
}
