@extends('meridian::layout', ['title' => 'Batch', 'poll' => true])
@use('Kakodjenou\Meridian\Support\Format')

@section('content')
    <h1>
        {{ $batch->name ?: 'Batch' }}
        @if ($batch->failedJobs > 0)
            <form class="inline" style="margin-left:auto" method="post" action="{{ route('meridian.batches.retry', $batch->id) }}">@csrf <button class="btn primary">Retry failed jobs</button></form>
        @endif
    </h1>

    <div class="panel">
        <dl class="meta">
            <dt>ID</dt><dd class="mono">{{ $batch->id }}</dd>
            <dt>Progress</dt><dd>{{ $batch->progress() }}% · {{ $batch->totalJobs - $batch->pendingJobs }} / {{ $batch->totalJobs }} processed · {{ $batch->failedJobs }} failed</dd>
            <dt>Created</dt><dd>{{ Format::date($batch->createdAt) }}</dd>
            @if ($batch->finishedAt)<dt>Finished</dt><dd>{{ Format::date($batch->finishedAt) }}</dd>@endif
            @if ($batch->cancelledAt)<dt>Cancelled</dt><dd>{{ Format::date($batch->cancelledAt) }}</dd>@endif
        </dl>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Jobs</h2></div>
        @include('meridian::partials.jobs-table')
    </div>
@endsection
