@extends('meridian::layout', ['title' => $t('Dashboard'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>{{ $t('Dashboard') }} <span class="badge {{ $stats['status'] }}">{{ $t(Format::status($stats['status'])) }}</span></h1>

    <div class="cards">
        <div class="card"><div class="label">{{ $t('Jobs per minute') }}</div><div class="value">{{ Format::number($stats['jobsPerMinute'], $locale) }}</div><div class="sub">{{ $t('average, last hour') }}</div></div>
        <div class="card"><div class="label">{{ $t('Jobs past hour') }}</div><div class="value">{{ Format::number($stats['recentJobs'], $locale) }}</div><div class="sub">{{ $t('completed + failed') }}</div></div>
        <div class="card"><div class="label">{{ $t('Failed jobs') }}</div><div class="value">{{ Format::number($stats['failedJobs'], $locale) }}</div><div class="sub"><a href="{{ route('meridian.jobs.index', 'failed') }}">{{ $t('past 7 days') }}</a></div></div>
        <div class="card"><div class="label">{{ $t('Pending') }}</div><div class="value">{{ Format::number($stats['pendingJobs'], $locale) }}</div><div class="sub">{{ $t('+ :count delayed', ['count' => Format::integer($stats['delayedJobs'], $locale)]) }}</div></div>
        <div class="card"><div class="label">{{ $t('Processing') }}</div><div class="value">{{ Format::integer($stats['processing'], $locale) }}</div><div class="sub">{{ $t(':count live worker(s)', ['count' => $stats['processes']]) }}</div></div>
        <div class="card"><div class="label">{{ $t('Max wait time') }}</div><div class="value">{{ $stats['maxWait'] ? Format::seconds($stats['maxWait']['seconds']) : '—' }}</div><div class="sub">{{ $stats['maxWait']['queue'] ?? $t('no pending jobs') }}</div></div>
        <div class="card"><div class="label">{{ $t('Max runtime') }}</div><div class="value">{{ $stats['maxRuntime'] ? Format::runtime($stats['maxRuntime']['runtime'], $locale) : '—' }}</div><div class="sub">{{ $stats['maxRuntime']['queue'] ?? $t('no job in the last hour') }}</div></div>
        <div class="card"><div class="label">{{ $t('Max throughput') }}</div><div class="value">{{ $stats['maxThroughput'] ? $t(':count jobs', ['count' => Format::integer($stats['maxThroughput']['count'], $locale)]) : '—' }}</div><div class="sub">{{ $stats['maxThroughput'] ? $t(':queue, last hour', ['queue' => $stats['maxThroughput']['queue']]) : $t('no job in the last hour') }}</div></div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Throughput') }}</h2><span class="muted">{{ $t('jobs processed per minute, last 60 minutes') }}</span></div>
        <div class="panel-body">
            @php($max = max(1, max($throughput)))
            <svg class="chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="{{ $t('Jobs per minute') }}">
                @foreach (array_values($throughput) as $i => $count)
                    @php($h = $count ? max(2, $count / $max * 116) : 2)
                    <rect x="{{ $i * 10 + 1 }}" y="{{ 120 - $h }}" width="8" height="{{ $h }}" rx="1" @class(['zero' => ! $count])><title>{{ $t(':time — :count job(s)', ['time' => Format::time(array_keys($throughput)[$i]), 'count' => $count]) }}</title></rect>
                @endforeach
            </svg>
            <div class="muted chart-legend"><span>{{ Format::time(array_key_first($throughput)) }}</span><span>{{ $t('peak :count/min', ['count' => $max]) }}</span><span>{{ Format::time(array_key_last($throughput)) }}</span></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Current workload') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Queue') }}</th><th class="num">{{ $t('Pending') }}</th><th class="num">{{ $t('Delayed') }}</th><th class="num">{{ $t('Reserved') }}</th><th class="num">{{ $t('Processes') }}</th><th class="num">{{ $t('Wait') }}</th></tr></thead>
                <tbody>
                @forelse ($workload as $queue)
                    <tr>
                        <td><span class="muted">{{ $queue['connection'] }}:</span><a href="{{ route('meridian.jobs.index', ['status' => 'pending', 'queue' => $queue['queue']]) }}">{{ $queue['queue'] }}</a></td>
                        <td class="num">{{ Format::integer($queue['pending'], $locale) }}</td>
                        <td class="num">{{ Format::integer($queue['delayed'], $locale) }}</td>
                        <td class="num">{{ Format::integer($queue['reserved'], $locale) }}</td>
                        <td class="num">{{ $queue['processes'] }}</td>
                        <td class="num">{{ Format::seconds($queue['wait']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $t('No database queue connection found in config/queue.php.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Supervisors') }}</h2><span class="muted"><code>php artisan meridian</code></span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Supervisor') }}</th><th>{{ $t('Host') }}</th><th>{{ $t('Connection') }}</th><th>{{ $t('Queues') }}</th><th class="num">{{ $t('Processes') }}</th><th>{{ $t('Balancing') }}</th><th>{{ $t('Status') }}</th></tr></thead>
                <tbody>
                @forelse ($supervisors as $supervisor)
                    <tr>
                        <td>{{ $supervisor->shortName() }}</td>
                        <td>{{ $supervisor->hostname }} <span class="muted">#{{ $supervisor->pid }}</span></td>
                        <td>{{ $supervisor->options['connection'] ?? '' }}</td>
                        <td>@foreach ($supervisor->processes as $queue => $count)<span class="tag">{{ $queue }}: {{ $count }}</span>@endforeach</td>
                        <td class="num">{{ $supervisor->totalProcesses() }}</td>
                        <td>{{ ($supervisor->options['balance'] ?? false) ?: $t('off') }}</td>
                        <td><span class="badge {{ $supervisor->status }}">{{ $t(Format::status($supervisor->status)) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">{{ $t('No supervisor running. Workers started with queue:work still appear below.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Workers') }}</h2><span class="muted">{{ $t(':count live', ['count' => $workers->count()]) }}</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Process') }}</th><th>{{ $t('Supervisor') }}</th><th>{{ $t('Queue') }}</th><th>{{ $t('Status') }}</th><th class="num">{{ $t('Jobs') }}</th><th class="num">{{ $t('Memory') }}</th><th>{{ $t('Started') }}</th><th>{{ $t('Last seen') }}</th></tr></thead>
                <tbody>
                @forelse ($workers as $worker)
                    <tr>
                        <td class="mono">{{ $worker->name }}</td>
                        <td>{{ $worker->supervisor ?? '—' }}</td>
                        <td><span class="muted">{{ $worker->connection }}:</span>{{ $worker->queue }}</td>
                        <td><span class="badge {{ $worker->status }}">{{ $t(Format::status($worker->status)) }}</span></td>
                        <td class="num">{{ Format::integer($worker->jobs_processed, $locale) }}</td>
                        <td class="num">{{ Format::bytes($worker->memory, $locale) }}</td>
                        <td>{{ Format::ago($worker->started_at, $locale) }}</td>
                        <td>{{ Format::ago($worker->last_seen_at, $locale) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">{{ $t('No worker is running.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
