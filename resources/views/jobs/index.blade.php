@php($titles = ['all' => 'All jobs', 'pending' => 'Pending jobs', 'processing' => 'Processing jobs', 'completed' => 'Completed jobs', 'failed' => 'Failed jobs'])
@extends('meridian::layout', ['title' => $t($titles[$status ?? 'all']), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>
        {{ $t($titles[$status ?? 'all']) }}
        @if ($status === 'failed' && $jobs->total() > 0)
            <form class="inline actions" method="post" action="{{ route('meridian.jobs.retry-all') }}"
                  data-confirm="{{ $t('Retry :count failed job(s)?', ['count' => Format::integer($jobs->total(), $locale)]) }}">
                @csrf
                @if (request('queue'))<input type="hidden" name="queue" value="{{ request('queue') }}">@endif
                @if (request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
                <button class="btn primary">↻ {{ $t('Retry all') }}</button>
            </form>
        @endif
    </h1>

    <div class="panel">
        <div class="panel-head">
            <div class="tabs">
                <a href="{{ route('meridian.jobs.index', array_filter(['queue' => request('queue'), 'search' => request('search')])) }}" @class(['active' => ! $status])>{{ $t('All') }}</a>
                @foreach (\IsoDoss\Meridian\Models\MonitoredJob::STATUSES as $s)
                    <a href="{{ route('meridian.jobs.index', array_filter(['status' => $s, 'queue' => request('queue'), 'search' => request('search')])) }}" @class(['active' => $status === $s])>
                        {{ $t(Format::status($s)) }} <span class="muted">{{ Format::integer($counts[$s] ?? 0, $locale) }}</span>
                    </a>
                @endforeach
            </div>
            <form method="get" class="filters">
                <select name="queue" data-autosubmit aria-label="{{ $t('Filter by queue') }}">
                    <option value="">{{ $t('All queues') }}</option>
                    @foreach ($queues as $queue)
                        <option value="{{ $queue }}" @selected(request('queue') === $queue)>{{ $queue }}</option>
                    @endforeach
                </select>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ $t('Job, tag or UUID…') }}" aria-label="{{ $t('Search') }}">
            </form>
        </div>
        @include('meridian::partials.jobs-table')
    </div>
@endsection
