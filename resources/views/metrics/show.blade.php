@extends('meridian::layout', ['title' => $t('Metrics')])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1><a href="{{ route('meridian.metrics.index', $type) }}">{{ $t('Metrics') }}</a> / <span class="mono">{{ $name }}</span></h1>

    @if ($snapshots->isEmpty())
        <div class="panel"><div class="empty">{{ $t('No snapshot yet. Snapshots are stored by php artisan meridian:snapshot (scheduled every 5 minutes).') }}</div></div>
    @else
        @foreach (['processed' => ['Throughput', 'jobs per snapshot'], 'runtime' => ['Average runtime', 'milliseconds']] as $field => [$label, $unit])
            @php($values = $snapshots->pluck($field)->all())
            @php($max = max(1, max($values)))
            @php($width = 600 / max(1, count($values)))
            <div class="panel">
                <div class="panel-head"><h2>{{ $t($label) }} <span class="muted">({{ $t($unit) }})</span></h2><span class="muted">{{ $t('peak :value', ['value' => $field === 'runtime' ? Format::runtime((int) $max, $locale) : $max]) }}</span></div>
                <div class="panel-body">
                    <svg class="chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="{{ $t($label) }}">
                        @foreach ($snapshots as $i => $snapshot)
                            @php($h = $snapshot->$field ? max(2, $snapshot->$field / $max * 116) : 2)
                            <rect x="{{ $i * $width }}" y="{{ 120 - $h }}" width="{{ max(1, $width - 1) }}" height="{{ $h }}" @class(['zero' => ! $snapshot->$field])><title>{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($snapshot->bucket), $locale) }} — {{ $snapshot->$field }}</title></rect>
                        @endforeach
                    </svg>
                    <div class="muted chart-legend"><span>{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($snapshots->first()->bucket), $locale) }}</span><span>{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($snapshots->last()->bucket), $locale) }}</span></div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
