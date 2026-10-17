@extends('meridian::layout', ['title' => 'Metrics'])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1><a href="{{ route('meridian.metrics.index', $type) }}">Metrics</a> / <span class="mono">{{ $name }}</span></h1>

    @if ($snapshots->isEmpty())
        <div class="panel"><div class="empty">No snapshot yet. Snapshots are stored by <code>php artisan meridian:snapshot</code> (scheduled every 5 minutes).</div></div>
    @else
        @foreach (['processed' => 'Throughput (jobs per snapshot)', 'runtime' => 'Average runtime (ms)'] as $field => $label)
            @php($values = $snapshots->pluck($field)->all())
            @php($max = max(1, max($values)))
            @php($width = 600 / max(1, count($values)))
            <div class="panel">
                <div class="panel-head"><h2>{{ $label }}</h2><span class="muted">peak {{ $field === 'runtime' ? Format::runtime((int) $max) : $max }}</span></div>
                <div class="panel-body">
                    <svg class="chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="{{ $label }}">
                        @foreach ($snapshots as $i => $snapshot)
                            @php($h = $snapshot->$field ? max(2, $snapshot->$field / $max * 116) : 2)
                            <rect x="{{ $i * $width }}" y="{{ 120 - $h }}" width="{{ max(1, $width - 1) }}" height="{{ $h }}" @class(['zero' => ! $snapshot->$field])><title>{{ date('Y-m-d H:i', $snapshot->bucket) }} — {{ $snapshot->$field }}</title></rect>
                        @endforeach
                    </svg>
                    <div class="muted" style="display:flex;justify-content:space-between;font-size:12px"><span>{{ date('Y-m-d H:i', $snapshots->first()->bucket) }}</span><span>{{ date('Y-m-d H:i', $snapshots->last()->bucket) }}</span></div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
