@extends('meridian::layout', ['title' => $job->shortName()])
@use('Kakodjenou\Meridian\Support\Format')
@use('Kakodjenou\Meridian\Models\MonitoredJob')

@section('content')
    <h1>
        {{ $job->shortName() }}
        <span class="badge {{ $job->status }}">{{ $job->isDelayed() ? 'delayed' : $job->status }}</span>
        <span style="margin-left:auto" class="filters">
            @if ($job->status === MonitoredJob::FAILED)
                <form class="inline" method="post" action="{{ route('meridian.jobs.retry', $job->uuid) }}">@csrf <button class="btn primary">Retry</button></form>
            @endif
            @if (in_array($job->status, [MonitoredJob::PENDING, MonitoredJob::FAILED, MonitoredJob::COMPLETED], true))
                <form class="inline" method="post" action="{{ route('meridian.jobs.destroy', $job->uuid) }}" onsubmit="return confirm('{{ $job->status === MonitoredJob::PENDING ? 'Remove this job from the queue?' : 'Delete this job record?' }}')">
                    @csrf @method('DELETE') <button class="btn danger">{{ $job->status === MonitoredJob::PENDING ? 'Cancel job' : 'Delete' }}</button>
                </form>
            @endif
        </span>
    </h1>

    <div class="panel">
        <dl class="meta">
            <dt>Class</dt><dd class="mono">{{ $job->name }}</dd>
            <dt>UUID</dt><dd class="mono">{{ $job->uuid }}</dd>
            <dt>Queue</dt><dd>{{ $job->connection }}:{{ $job->queue }} @if($job->job_id)<span class="muted">(jobs.id {{ $job->job_id }})</span>@endif</dd>
            <dt>Tags</dt><dd>@forelse ($job->tags ?? [] as $tag)<a class="tag" href="{{ route('meridian.jobs.index', ['search' => $tag]) }}">{{ $tag }}</a>@empty<span class="muted">—</span>@endforelse</dd>
            @if ($job->batch_id)<dt>Batch</dt><dd><a class="mono" href="{{ route('meridian.batches.show', $job->batch_id) }}">{{ $job->batch_id }}</a></dd>@endif
            <dt>Attempts</dt><dd>{{ $job->attempts }} @if($job->retries)<span class="muted">· retried manually {{ $job->retries }} time(s)</span>@endif</dd>
            <dt>Queued at</dt><dd>{{ Format::date($job->queued_at) }}</dd>
            @if ($job->available_at->ne($job->queued_at))<dt>Available at</dt><dd>{{ Format::date($job->available_at) }}</dd>@endif
            <dt>Started at</dt><dd>{{ Format::date($job->reserved_at) }} @if($job->worker)<span class="muted">by {{ $job->worker }}</span>@endif</dd>
            @if ($job->completed_at)<dt>Completed at</dt><dd>{{ Format::date($job->completed_at) }}</dd>@endif
            @if ($job->failed_at)<dt>Failed at</dt><dd>{{ Format::date($job->failed_at) }}</dd>@endif
            <dt>Wait</dt><dd>{{ Format::seconds($job->wait) }}</dd>
            <dt>Runtime</dt><dd>{{ Format::runtime($job->runtime) }}</dd>
        </dl>
    </div>

    @if ($job->exception)
        <div class="panel">
            <div class="panel-head"><h2>Exception</h2></div>
            <div class="panel-body"><pre>{{ $job->exception }}</pre></div>
        </div>
    @endif

    <div class="panel">
        <div class="panel-head"><h2>Payload</h2></div>
        <div class="panel-body">
            @if ($payload = $job->prettyPayload())
                <pre>{{ $payload }}</pre>
            @else
                <span class="muted">Payload not stored (see <code>meridian.store_payload</code>).</span>
            @endif
        </div>
    </div>
@endsection
