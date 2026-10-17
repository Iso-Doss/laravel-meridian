@extends('meridian::layout', ['title' => 'Dashboard', 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>Dashboard <span class="badge {{ $stats['status'] }}">{{ ucfirst($stats['status']) }}</span></h1>

    <div class="cards">
        <div class="card"><div class="label">Jobs per minute</div><div class="value">{{ $stats['jobsPerMinute'] }}</div><div class="sub">average, last hour</div></div>
        <div class="card"><div class="label">Jobs past hour</div><div class="value">{{ Format::number($stats['recentJobs']) }}</div><div class="sub">completed + failed</div></div>
        <div class="card"><div class="label">Failed jobs</div><div class="value">{{ Format::number($stats['failedJobs']) }}</div><div class="sub"><a href="{{ route('meridian.jobs.index', 'failed') }}">past 7 days</a></div></div>
        <div class="card"><div class="label">Pending</div><div class="value">{{ Format::number($stats['pendingJobs']) }}</div><div class="sub">+ {{ $stats['delayedJobs'] }} delayed</div></div>
        <div class="card"><div class="label">Processing</div><div class="value">{{ $stats['processing'] }}</div><div class="sub">{{ $stats['processes'] }} live worker(s)</div></div>
        <div class="card"><div class="label">Max wait time</div><div class="value">{{ $stats['maxWait'] ? Format::seconds($stats['maxWait']['seconds']) : '—' }}</div><div class="sub">{{ $stats['maxWait']['queue'] ?? 'no pending jobs' }}</div></div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Throughput</h2><span class="muted">jobs processed per minute, last 60 minutes</span></div>
        <div class="panel-body">
            @php($max = max(1, max($throughput)))
            <svg class="chart" viewBox="0 0 600 120" preserveAspectRatio="none" role="img" aria-label="Jobs per minute">
                @foreach (array_values($throughput) as $i => $count)
                    @php($h = $count ? max(2, $count / $max * 116) : 2)
                    <rect x="{{ $i * 10 + 1 }}" y="{{ 120 - $h }}" width="8" height="{{ $h }}" rx="1" @class(['zero' => ! $count])><title>{{ date('H:i', array_keys($throughput)[$i]) }} — {{ $count }} job(s)</title></rect>
                @endforeach
            </svg>
            <div class="muted" style="display:flex;justify-content:space-between;font-size:12px"><span>{{ date('H:i', array_key_first($throughput)) }}</span><span>peak {{ $max }}/min</span><span>{{ date('H:i', array_key_last($throughput)) }}</span></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Current workload</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Queue</th><th class="num">Pending</th><th class="num">Delayed</th><th class="num">Reserved</th><th class="num">Processes</th><th class="num">Wait</th></tr></thead>
                <tbody>
                @forelse ($workload as $queue)
                    <tr>
                        <td><span class="muted">{{ $queue['connection'] }}:</span><a href="{{ route('meridian.jobs.index', ['status' => 'pending', 'queue' => $queue['queue']]) }}">{{ $queue['queue'] }}</a></td>
                        <td class="num">{{ number_format($queue['pending']) }}</td>
                        <td class="num">{{ number_format($queue['delayed']) }}</td>
                        <td class="num">{{ number_format($queue['reserved']) }}</td>
                        <td class="num">{{ $queue['processes'] }}</td>
                        <td class="num">{{ Format::seconds($queue['wait']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No database queue connection found in config/queue.php.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Supervisors</h2><span class="muted"><code>php artisan meridian</code></span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supervisor</th><th>Host</th><th>Connection</th><th>Queues</th><th class="num">Processes</th><th>Balancing</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($supervisors as $supervisor)
                    <tr>
                        <td>{{ $supervisor->shortName() }}</td>
                        <td>{{ $supervisor->hostname }} <span class="muted">#{{ $supervisor->pid }}</span></td>
                        <td>{{ $supervisor->options['connection'] ?? '' }}</td>
                        <td>@foreach ($supervisor->processes as $queue => $count)<span class="tag">{{ $queue }}: {{ $count }}</span>@endforeach</td>
                        <td class="num">{{ $supervisor->totalProcesses() }}</td>
                        <td>{{ ($supervisor->options['balance'] ?? false) ?: 'off' }}</td>
                        <td><span class="badge {{ $supervisor->status }}">{{ $supervisor->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No supervisor running. Workers started with <code>queue:work</code> still appear below.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Workers</h2><span class="muted">{{ $workers->count() }} live</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Process</th><th>Supervisor</th><th>Queue</th><th>Status</th><th class="num">Jobs</th><th class="num">Memory</th><th>Started</th><th>Last seen</th></tr></thead>
                <tbody>
                @forelse ($workers as $worker)
                    <tr>
                        <td class="mono">{{ $worker->name }}</td>
                        <td>{{ $worker->supervisor ?? '—' }}</td>
                        <td><span class="muted">{{ $worker->connection }}:</span>{{ $worker->queue }}</td>
                        <td><span class="badge {{ $worker->status }}">{{ $worker->status }}</span></td>
                        <td class="num">{{ number_format($worker->jobs_processed) }}</td>
                        <td class="num">{{ Format::bytes($worker->memory) }}</td>
                        <td>{{ Format::ago($worker->started_at) }}</td>
                        <td>{{ Format::ago($worker->last_seen_at) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No worker is running.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
