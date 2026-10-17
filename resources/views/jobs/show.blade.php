@extends('meridian::layout', ['title' => $job->shortName()])
@use('IsoDoss\Meridian\Support\Format')
@use('IsoDoss\Meridian\Models\MonitoredJob')

@section('content')
    @php($pending = $job->status === MonitoredJob::PENDING)
    <h1>
        {{ $job->shortName() }}
        <span class="badge {{ $job->status }}">{{ $t(Format::status($job->isDelayed() ? 'delayed' : $job->status)) }}</span>
        <span class="filters actions">
            @if ($job->status === MonitoredJob::FAILED)
                <form class="inline" method="post" action="{{ route('meridian.jobs.retry', $job->uuid) }}" data-confirm="{{ $t('Retry this job?') }}">@csrf <button class="btn primary">{{ $t('Retry') }}</button></form>
            @endif
            @if ($job->status !== MonitoredJob::PROCESSING)
                <form class="inline" method="post" action="{{ route('meridian.jobs.destroy', $job->uuid) }}" data-confirm="{{ $pending ? $t('Cancel this job?') : $t('Delete this job?') }}">
                    @csrf @method('DELETE') <button class="btn danger">{{ $pending ? $t('Cancel job') : $t('Delete') }}</button>
                </form>
            @endif
        </span>
    </h1>

    <div class="panel">
        <dl class="meta">
            <dt>{{ $t('Class') }}</dt><dd class="mono">{{ $job->name }}</dd>
            <dt>UUID</dt><dd class="mono">{{ $job->uuid }}</dd>
            <dt>{{ $t('Queue') }}</dt><dd>{{ $job->connection }}:{{ $job->queue }} @if($job->job_id)<span class="muted">(jobs.id {{ $job->job_id }})</span>@endif</dd>
            <dt>{{ $t('Tags') }}</dt><dd>@forelse ($job->tags ?? [] as $tag)<a class="tag" href="{{ route('meridian.jobs.index', ['tag' => $tag]) }}">{{ $tag }}</a>@empty<span class="muted">—</span>@endforelse</dd>
            @if ($job->batch_id)<dt>{{ $t('Batch') }}</dt><dd><a class="mono" href="{{ route('meridian.batches.show', $job->batch_id) }}">{{ $job->batch_id }}</a></dd>@endif
            <dt>{{ $t('Attempts') }}</dt><dd>{{ $job->attempts }} @if($job->retries)<span class="muted">· {{ $t('retried manually :count time(s)', ['count' => $job->retries]) }}</span>@endif</dd>
            <dt>{{ $t('Queued at') }}</dt><dd>{{ Format::date($job->queued_at, $locale) }}</dd>
            @if ($job->available_at->ne($job->queued_at))<dt>{{ $t('Available at') }}</dt><dd>{{ Format::date($job->available_at, $locale) }}</dd>@endif
            <dt>{{ $t('Started at') }}</dt><dd>{{ Format::date($job->reserved_at, $locale) }} @if($job->worker)<span class="muted">· {{ $t('by :worker', ['worker' => $job->worker]) }}</span>@endif</dd>
            @if ($job->completed_at)<dt>{{ $t('Completed at') }}</dt><dd>{{ Format::date($job->completed_at, $locale) }}</dd>@endif
            @if ($job->failed_at)<dt>{{ $t('Failed at') }}</dt><dd>{{ Format::date($job->failed_at, $locale) }}</dd>@endif
            <dt>{{ $t('Wait') }}</dt><dd>{{ Format::seconds($job->wait) }}</dd>
            <dt>{{ $t('Runtime') }}</dt><dd>{{ Format::runtime($job->runtime, $locale) }}</dd>
        </dl>
    </div>

    @if ($job->exception)
        <div class="panel">
            <div class="panel-head"><h2>{{ $t('Exception') }}</h2></div>
            <div class="panel-body"><pre>{{ $job->exception }}</pre></div>
        </div>
    @endif

    @php($history = app(\IsoDoss\Meridian\Repositories\JobRetryRepository::class)->history($job->uuid))
    @if ($history !== [])
        <div class="panel">
            <div class="panel-head"><h2>{{ $t('Retries') }}</h2><span class="muted">{{ $t(':count manual retry(ies)', ['count' => count($history)]) }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>#</th><th>{{ $t('Retried at') }}</th><th>{{ $t('Status') }}</th><th>{{ $t('Finished at') }}</th><th class="num">{{ $t('Runtime') }}</th><th>{{ $t('Exception') }}</th></tr></thead>
                    <tbody>
                    @foreach ($history as $index => $attempt)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($attempt['retried_at']), $locale) }}</td>
                            <td><span class="badge {{ $attempt['status'] }}">{{ $t(Format::status($attempt['status'])) }}</span></td>
                            <td>{{ $attempt['finished_at'] ? Format::date(\Illuminate\Support\Carbon::createFromTimestamp($attempt['finished_at']), $locale) : '—' }}</td>
                            <td class="num">{{ Format::runtime($attempt['runtime'], $locale) }}</td>
                            <td class="wrap mono" title="{{ $attempt['exception'] }}">{{ $attempt['exception'] ? \Illuminate\Support\Str::limit(strtok($attempt['exception'], "\n"), 120) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($data = \IsoDoss\Meridian\Support\JobData::describe($job->payload))
        <div class="panel">
            <div class="panel-head"><h2>{{ $t('Data') }}</h2></div>
            @if ($data['error'])
                <div class="panel-body muted">{{ $t('The data of this job cannot be read: :error', ['error' => $data['error']]) }}</div>
            @elseif ($data['properties'] === [])
                <div class="panel-body muted">{{ $t('This job has no data.') }}</div>
            @else
                <dl class="meta">
                    @foreach ($data['properties'] as $property)
                        <dt class="mono">{{ $property['name'] }}</dt><dd class="mono">{{ $property['value'] }}</dd>
                    @endforeach
                </dl>
            @endif
        </div>
    @endif

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Payload') }}</h2></div>
        <div class="panel-body">
            @if ($payload = $job->prettyPayload())
                <pre>{{ $payload }}</pre>
            @else
                <span class="muted">{{ $t('Payload not stored (see meridian.store_payload).') }}</span>
            @endif
        </div>
    </div>
@endsection
