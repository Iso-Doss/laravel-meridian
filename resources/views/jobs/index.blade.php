@extends('meridian::layout', ['title' => ucfirst($status ?? 'all').' jobs', 'poll' => true])

@section('content')
    <h1>{{ ucfirst($status ?? 'All') }} jobs</h1>

    <div class="panel">
        <div class="panel-head">
            <div class="tabs">
                <a href="{{ route('meridian.jobs.index', array_filter(['queue' => request('queue'), 'search' => request('search')])) }}" @class(['active' => ! $status])>All</a>
                @foreach (\IsoDoss\Meridian\Models\MonitoredJob::STATUSES as $s)
                    <a href="{{ route('meridian.jobs.index', array_filter(['status' => $s, 'queue' => request('queue'), 'search' => request('search')])) }}" @class(['active' => $status === $s])>
                        {{ ucfirst($s) }} <span class="muted">{{ number_format($counts[$s] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
            <form method="get" class="filters">
                <select name="queue" onchange="this.form.submit()">
                    <option value="">All queues</option>
                    @foreach ($queues as $queue)
                        <option value="{{ $queue }}" @selected(request('queue') === $queue)>{{ $queue }}</option>
                    @endforeach
                </select>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Job, tag or UUID…">
            </form>
        </div>
        @include('meridian::partials.jobs-table')
    </div>
@endsection
