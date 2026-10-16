@extends('meridian::layout', ['title' => 'Metrics', 'poll' => true])
@use('Kakodjenou\Meridian\Support\Format')

@section('content')
    <h1>Metrics</h1>

    <div class="panel">
        <div class="panel-head">
            <div class="tabs">
                <a href="{{ route('meridian.metrics.index', ['type' => 'jobs', 'period' => $minutes]) }}" @class(['active' => $type === 'jobs'])>Jobs</a>
                <a href="{{ route('meridian.metrics.index', ['type' => 'queues', 'period' => $minutes]) }}" @class(['active' => $type === 'queues'])>Queues</a>
            </div>
            <div class="tabs">
                @foreach ($periods as $value => $label)
                    <a href="{{ route('meridian.metrics.index', ['type' => $type, 'period' => $value]) }}" @class(['active' => $minutes === $value])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $type === 'jobs' ? 'Job' : 'Queue' }}</th><th class="num">Processed</th><th class="num">Failed</th><th class="num">Failure rate</th><th class="num">Avg runtime</th><th class="num">Max runtime</th><th class="num">Avg wait</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php($total = $row->processed + $row->failed)
                    <tr>
                        <td><a href="{{ route('meridian.metrics.show', [$type, $row->key]) }}" title="{{ $row->key }}">{{ $type === 'jobs' ? class_basename($row->key) : $row->key }}</a></td>
                        <td class="num">{{ number_format($row->processed) }}</td>
                        <td class="num">{{ number_format($row->failed) }}</td>
                        <td class="num">{{ $total ? round($row->failed / $total * 100, 1) : 0 }}%</td>
                        <td class="num">{{ Format::runtime($row->runtime === null ? null : (int) round($row->runtime)) }}</td>
                        <td class="num">{{ Format::runtime($row->max_runtime === null ? null : (int) $row->max_runtime) }}</td>
                        <td class="num">{{ Format::seconds($row->wait === null ? null : (int) round($row->wait)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No job processed during this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
