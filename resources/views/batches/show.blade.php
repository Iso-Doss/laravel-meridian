@extends('meridian::layout', ['title' => $batch->name ?: $t('Batch'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>
        {{ $batch->name ?: $t('Batch') }}
        @if ($batch->failedJobs > 0)
            <form class="inline actions" method="post" action="{{ route('meridian.batches.retry', $batch->id) }}" data-confirm="{{ $t('Retry the failed jobs of this batch?') }}">@csrf <button class="btn primary">{{ $t('Retry failed jobs') }}</button></form>
        @endif
    </h1>

    <div class="panel">
        <dl class="meta">
            <dt>ID</dt><dd class="mono">{{ $batch->id }}</dd>
            <dt>{{ $t('Progress') }}</dt><dd>{{ $t(':progress% · :processed / :total processed · :failed failed', ['progress' => $batch->progress(), 'processed' => $batch->totalJobs - $batch->pendingJobs, 'total' => $batch->totalJobs, 'failed' => $batch->failedJobs]) }}</dd>
            <dt>{{ $t('Created') }}</dt><dd>{{ Format::date($batch->createdAt, $locale) }}</dd>
            @if ($batch->finishedAt)<dt>{{ $t('Finished') }}</dt><dd>{{ Format::date($batch->finishedAt, $locale) }}</dd>@endif
            @if ($batch->cancelledAt)<dt>{{ $t('Cancelled') }}</dt><dd>{{ Format::date($batch->cancelledAt, $locale) }}</dd>@endif
        </dl>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>{{ $t('Jobs') }}</h2></div>
        @include('meridian::partials.jobs-table')
    </div>
@endsection
