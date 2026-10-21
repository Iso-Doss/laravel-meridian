@extends('meridian::layout', ['title' => $t('Metrics'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>{{ $t('Metrics') }}</h1>

    @php($days = collect($hourly)->groupBy(fn ($point) => \Illuminate\Support\Carbon::createFromTimestamp($point['time'], config('app.timezone'))->format('Y-m-d'))->take(-7))
    {{-- The scale follows the 95th percentile: one exceptional hour does not flatten the others. --}}
    @php($counts = collect($hourly)->map(fn ($point) => $point['processed'] + $point['failed'])->filter()->sort()->values())
    @php($peak = max(1, $counts[(int) floor($counts->count() * 0.95)] ?? 1))
    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Jobs per hour') }}</h2><span class="muted">{{ $t('last 7 days, in your timezone; hours where 10% or more of the jobs failed are outlined') }}</span></div>
        <div class="panel-body table-wrap">
            <table class="heatmap">
                <thead><tr><th></th>@for ($hour = 0; $hour < 24; $hour++)<th>{{ $hour % 3 === 0 ? sprintf('%02d', $hour) : '' }}</th>@endfor</tr></thead>
                <tbody>
                @foreach ($days as $day => $points)
                    @php($byHour = $points->keyBy(fn ($point) => (int) \Illuminate\Support\Carbon::createFromTimestamp($point['time'], config('app.timezone'))->format('G')))
                    <tr>
                        <th>{{ \Illuminate\Support\Carbon::parse($day)->locale($locale)->isoFormat('ddd D') }}</th>
                        @for ($hour = 0; $hour < 24; $hour++)
                            @php($point = $byHour[$hour] ?? null)
                            @php($count = $point ? $point['processed'] + $point['failed'] : 0)
                            <td @class(['heat-'.($count ? min(4, (int) ceil($count / $peak * 4)) : 0) => $point, 'heat-failed' => $point && $point['failed'] > 0 && $point['failed'] / ($point['processed'] + $point['failed']) >= 0.1]) @if ($point) title="{{ sprintf('%02d:00', $hour) }} — {{ $t(':count job(s)', ['count' => Format::integer($count, $locale)]) }}" @endif></td>
                        @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div class="tabs">
                <a href="{{ route('meridian.metrics.index', ['type' => 'jobs', 'period' => $minutes]) }}" @class(['active' => $type === 'jobs'])>{{ $t('Jobs') }}</a>
                <a href="{{ route('meridian.metrics.index', ['type' => 'queues', 'period' => $minutes]) }}" @class(['active' => $type === 'queues'])>{{ $t('Queues') }}</a>
            </div>
            <div class="tabs">
                @foreach ($periods as $value => $label)
                    <a href="{{ route('meridian.metrics.index', ['type' => $type, 'period' => $value]) }}" @class(['active' => $minutes === $value])>{{ $t($label) }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $type === 'jobs' ? $t('Job') : $t('Queue') }}</th><th class="num">{{ $t('Processed') }}</th><th class="num">{{ $t('Failed') }}</th><th class="num">{{ $t('Failure rate') }}</th><th class="num">{{ $t('Avg runtime') }}</th><th class="num" title="{{ $t('95% of the jobs ran faster') }}">p95</th><th class="num" title="{{ $t('99% of the jobs ran faster') }}">p99</th><th class="num">{{ $t('Max runtime') }}</th><th class="num">{{ $t('Avg wait') }}</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php($total = $row->processed + $row->failed)
                    <tr>
                        <td><a href="{{ route('meridian.metrics.show', [$type, $row->key]) }}" title="{{ $row->key }}">{{ $type === 'jobs' ? class_basename($row->key) : $row->key }}</a></td>
                        <td class="num">{{ Format::integer($row->processed, $locale) }}@include('meridian::metrics.trend', ['current' => (float) $row->processed, 'previous' => $row->previous?->processed, 'worse' => false, 'points' => false])</td>
                        <td class="num">{{ Format::integer($row->failed, $locale) }}</td>
                        @php($previous = $row->previous ? \IsoDoss\Meridian\Http\Controllers\MetricsController::summary($row->previous) : null)
                        <td class="num">{{ Format::decimal($total ? $row->failed / $total * 100 : 0, 1, $locale) }} %@include('meridian::metrics.trend', ['current' => $total ? $row->failed / $total * 100 : 0.0, 'previous' => $previous['failure_rate'] ?? null, 'worse' => true, 'points' => true])</td>
                        <td class="num">{{ Format::runtime($row->runtime === null ? null : (int) round($row->runtime), $locale) }}@include('meridian::metrics.trend', ['current' => $row->runtime === null ? null : (float) $row->runtime, 'previous' => $previous['runtime'] ?? null, 'worse' => true, 'points' => false])</td>
                        <td class="num">{{ Format::runtime($row->percentiles[95], $locale) }}</td>
                        <td class="num">{{ Format::runtime($row->percentiles[99], $locale) }}</td>
                        <td class="num">{{ Format::runtime($row->max_runtime === null ? null : (int) $row->max_runtime, $locale) }}</td>
                        <td class="num">{{ Format::seconds($row->wait === null ? null : (int) round($row->wait)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">{{ $t('No job processed during this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
