@extends('meridian::layout', ['title' => $task['name'], 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')
@use('Illuminate\Support\Carbon')

@section('content')
    <h1>
        {{ $task['name'] }}
        <span class="badge {{ $task['health'] }}">{{ $t(Format::status($task['health'])) }}</span>
        <span class="filters actions"><a class="btn" href="{{ route('meridian.schedule.index') }}">← {{ $t('Schedule') }}</a></span>
    </h1>

    <div class="panel">
        <div class="panel-head">
            <div>@include('meridian::schedule.flags')</div>
            <span class="muted">{{ $t('Next run') }}: {{ $task['next_run_at'] ? Format::date(Carbon::createFromTimestamp($task['next_run_at']), $locale) : '—' }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Started at') }}</th><th>{{ $t('Status') }}</th><th class="num">{{ $t('Duration') }}</th><th class="num">{{ $t('Exit code') }}</th><th>{{ $t('Host') }}</th><th>{{ $t('Exception') }}</th></tr></thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td>{{ Format::date(Carbon::createFromTimestamp($run['started_at']), $locale) }}</td>
                        <td><span class="badge {{ $run['status'] }}">{{ $t(Format::status($run['status'])) }}</span></td>
                        <td class="num">{{ Format::runtime($run['runtime'], $locale) }}</td>
                        <td class="num">{{ $run['exit_code'] ?? '—' }}</td>
                        <td>{{ $run['host'] ?? '—' }}</td>
                        <td class="wrap mono" title="{{ $run['exception'] }}">{{ $run['exception'] ? \Illuminate\Support\Str::limit(strtok($run['exception'], "\n"), 120) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $t('This task has not run yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('meridian::partials.pagination', ['paginator' => $runs])
    </div>
@endsection
