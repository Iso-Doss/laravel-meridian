@extends('meridian::layout', ['title' => $t('Metrics'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>{{ $t('Metrics') }}</h1>

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
                <thead><tr><th>{{ $type === 'jobs' ? $t('Job') : $t('Queue') }}</th><th class="num">{{ $t('Processed') }}</th><th class="num">{{ $t('Failed') }}</th><th class="num">{{ $t('Failure rate') }}</th><th class="num">{{ $t('Avg runtime') }}</th><th class="num">{{ $t('Max runtime') }}</th><th class="num">{{ $t('Avg wait') }}</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php($total = $row->processed + $row->failed)
                    <tr>
                        <td><a href="{{ route('meridian.metrics.show', [$type, $row->key]) }}" title="{{ $row->key }}">{{ $type === 'jobs' ? class_basename($row->key) : $row->key }}</a></td>
                        <td class="num">{{ Format::integer($row->processed, $locale) }}</td>
                        <td class="num">{{ Format::integer($row->failed, $locale) }}</td>
                        <td class="num">{{ Format::decimal($total ? $row->failed / $total * 100 : 0, 1, $locale) }} %</td>
                        <td class="num">{{ Format::runtime($row->runtime === null ? null : (int) round($row->runtime), $locale) }}</td>
                        <td class="num">{{ Format::runtime($row->max_runtime === null ? null : (int) $row->max_runtime, $locale) }}</td>
                        <td class="num">{{ Format::seconds($row->wait === null ? null : (int) round($row->wait)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">{{ $t('No job processed during this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
