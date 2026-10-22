@extends('meridian::layout', ['title' => $job->shortName()])
@use('IsoDoss\Meridian\Support\Format')
@use('IsoDoss\Meridian\Models\MonitoredJob')

@section('content')
    @php($pending = $job->status === MonitoredJob::PENDING)
    @php($running = $job->status === MonitoredJob::PROCESSING)
    @php($deletable = ! $running || (\IsoDoss\Meridian\Support\Migrations::hasCancellation() && $job->cancel_requested_at === null))
    <h1>
        {{ $job->shortName() }}
        <span class="badge {{ $job->status }}">{{ $t(Format::status($job->isDelayed() ? 'delayed' : $job->status)) }}</span>
        @if ($running && $job->cancel_requested_at)<span class="badge cancelling">{{ $t('Cancelling…') }}</span>@endif
        <span class="filters actions">
            @if ($job->status === MonitoredJob::FAILED && $canManage)
                <form class="inline" method="post" action="{{ route('meridian.jobs.retry', $job->uuid) }}" data-confirm="{{ $t('Retry this job?') }}">@csrf <button class="btn primary">{{ $t('Retry') }}</button></form>
            @endif
            @if ($deletable && $canManage)
                <form class="inline" method="post" action="{{ route('meridian.jobs.destroy', $job->uuid) }}" data-confirm="{{ $pending || $running ? $t('Cancel this job?') : $t('Delete this job?') }}">
                    @csrf @method('DELETE') <button class="btn danger">{{ $pending || $running ? $t('Cancel job') : $t('Delete') }}</button>
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
            @if ($origin = \IsoDoss\Meridian\Support\JobOrigin::describe($job))
                <dt>{{ $t('Dispatched by') }}</dt>
                <dd>
                    <span class="badge">{{ $t(\IsoDoss\Meridian\Support\JobOrigin::TYPES[$origin['type']] ?? $origin['type']) }}</span>
                    @if ($origin['parent'])<a class="mono" href="{{ $origin['parent']['url'] }}">{{ $origin['label'] }}</a>@else<span class="mono">{{ $origin['label'] }}</span>@endif
                    @if ($origin['parent'] && $origin['parent']['status'])<span class="badge {{ $origin['parent']['status'] }}">{{ $t(Format::status($origin['parent']['status'])) }}</span>@endif
                    @if ($origin['user'])<span class="muted">· {{ $origin['user'] }}</span>@endif
                </dd>
            @endif
            <dt>{{ $t('Attempts') }}</dt><dd>{{ $job->attempts }} @if($job->retries)<span class="muted">· {{ $t('retried manually :count time(s)', ['count' => $job->retries]) }}</span>@endif</dd>
            <dt>{{ $t('Queued at') }}</dt><dd>{{ Format::date($job->queued_at, $locale) }}</dd>
            @if ($job->available_at->ne($job->queued_at))<dt>{{ $t('Available at') }}</dt><dd>{{ Format::date($job->available_at, $locale) }}</dd>@endif
            <dt>{{ $t('Started at') }}</dt><dd>{{ Format::date($job->reserved_at, $locale) }} @if($job->worker)<span class="muted">· {{ $t('by :worker', ['worker' => $job->worker]) }}</span>@endif</dd>
            @if ($job->completed_at)<dt>{{ $t('Completed at') }}</dt><dd>{{ Format::date($job->completed_at, $locale) }}</dd>@endif
            @if ($job->failed_at)<dt>{{ $t('Failed at') }}</dt><dd>{{ Format::date($job->failed_at, $locale) }}</dd>@endif
            <dt>{{ $t('Wait') }}</dt><dd>{{ Format::seconds($job->wait) }}</dd>
            <dt>{{ $t('Runtime') }}</dt><dd>{{ Format::runtime($job->runtime, $locale) }}</dd>
            @if ($job->status === MonitoredJob::PROCESSING && $job->progress !== null)
                <dt>{{ $t('Progress') }}</dt><dd>@include('meridian::partials.job-progress', ['detailed' => true])</dd>
            @endif
        </dl>
    </div>

    @php($children = \IsoDoss\Meridian\Support\JobOrigin::children($job))
    @if ($children['count'] > 0)
        <div class="panel">
            <div class="panel-head"><h2>{{ $t('Dispatched jobs') }}</h2><span class="muted">{{ $t(':count job(s) dispatched by this job', ['count' => $children['count']]) }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ $t('Job') }}</th><th>{{ $t('Status') }}</th><th>{{ $t('Queued at') }}</th></tr></thead>
                    <tbody>
                    @foreach ($children['jobs'] as $child)
                        <tr>
                            <td><a class="mono" href="{{ $child['url'] }}">{{ $child['name'] }}</a></td>
                            <td><span class="badge {{ $child['status'] }}">{{ $t(Format::status($child['status'])) }}</span></td>
                            <td>{{ $child['queued_at'] ? Format::date(\Illuminate\Support\Carbon::createFromTimestamp($child['queued_at']), $locale) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($job->exception)
        <div class="panel">
            @php($similar = $job->status === MonitoredJob::FAILED ? app(\IsoDoss\Meridian\Repositories\ErrorGroupRepository::class)->countFor($job->fingerprint) : 0)
            <div class="panel-head">
                <h2>{{ $t('Exception') }}</h2>
                @if ($similar > 1)<a href="{{ route('meridian.jobs.index', ['status' => 'failed', 'error' => $job->fingerprint]) }}">{{ $t(':count failed jobs with the same error', ['count' => $similar]) }}</a>@endif
            </div>
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
        @php($fields = $job->status === MonitoredJob::FAILED && \IsoDoss\Meridian\Meridian::canEditJobs(request()) ? app(\IsoDoss\Meridian\Support\JobEditor::class)->fields($job) : null)
        <div class="panel">
            <div class="panel-head"><h2>{{ $t('Data') }}</h2></div>
            @if ($fields && collect($fields)->contains('editable', true))
                <details class="edit-data" @if ($errors->any()) open @endif>
                    <summary class="btn">✎ {{ $t('Edit and retry') }}</summary>
                    <form method="post" action="{{ route('meridian.jobs.retry-edited', $job->uuid) }}">
                        @csrf
                        <p class="flash warn">{{ $t('Change the values that made the job fail, then retry it. Models, enums, dates and other objects are kept as they are.') }}</p>
                        <dl class="meta">
                            @foreach ($fields as $field)
                                <dt><label for="field-{{ $field['name'] }}" class="mono">{{ $field['name'] }}</label> <span class="muted mono">{{ $field['type'] }}</span></dt>
                                <dd>
                                    @if (! $field['editable'])
                                        <span class="mono muted">{{ $field['value'] }}</span>
                                    @elseif ($field['type'] === 'bool')
                                        <select id="field-{{ $field['name'] }}" name="data[{{ $field['name'] }}]">
                                            @foreach (['true', 'false'] as $choice)<option value="{{ $choice }}" @selected(old('data.'.$field['name'], $field['value']) === $choice)>{{ $choice }}</option>@endforeach
                                        </select>
                                    @elseif ($field['type'] === 'json' || str_contains($field['value'], "\n") || strlen($field['value']) > 80)
                                        <textarea id="field-{{ $field['name'] }}" name="data[{{ $field['name'] }}]" class="mono" rows="{{ min(12, max(2, substr_count($field['value'], "\n") + 1)) }}">{{ old('data.'.$field['name'], $field['value']) }}</textarea>
                                    @else
                                        <input id="field-{{ $field['name'] }}" name="data[{{ $field['name'] }}]" class="mono" value="{{ old('data.'.$field['name'], $field['value']) }}">
                                    @endif
                                    @error('data.'.$field['name'])<div class="field-error">{{ $message }}</div>@enderror
                                </dd>
                            @endforeach
                        </dl>
                        @error('data')<div class="field-error">{{ $message }}</div>@enderror
                        <div class="panel-body"><button class="btn primary">↻ {{ $t('Retry with these values') }}</button></div>
                    </form>
                </details>
            @endif
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
