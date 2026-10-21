@extends('meridian::layout', ['title' => $t('Schedule'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')
@use('Illuminate\Support\Carbon')

@section('content')
    <h1>{{ $t('Schedule') }}</h1>

    @unless ($ready)
        <div class="flash error">{{ $t('Run the migrations of Meridian to monitor the scheduled tasks (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).') }}</div>
    @endunless

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $t('Scheduled tasks') }}</h2>
                <span class="muted">{{ $t(':count task(s), :failing failing or missed', ['count' => count($tasks), 'failing' => collect($tasks)->whereIn('health', ['failed', 'missed'])->count()]) }}</span>
            </div>
            <code class="muted">php artisan schedule:run</code>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Task') }}</th><th>{{ $t('Status') }}</th><th>{{ $t('Last run') }}</th><th class="num">{{ $t('Duration') }}</th><th>{{ $t('Recent runs') }}</th><th>{{ $t('Next run') }}</th></tr></thead>
                <tbody>
                @forelse ($tasks as $task)
                    <tr>
                        <td class="wrap">
                            <a href="{{ $task['url'] }}"><strong>{{ $task['name'] }}</strong></a>
                            <div>@include('meridian::schedule.flags')</div>
                        </td>
                        <td>
                            <span class="badge {{ $task['health'] }}">{{ $t(Format::status($task['health'])) }}</span>
                            @if ($task['missed_at'])<div class="field-error">{{ $t('due :time', ['time' => Format::ago(Carbon::createFromTimestamp($task['missed_at']), $locale)]) }}</div>@endif
                        </td>
                        <td>{{ $task['last_run'] ? Format::ago(Carbon::createFromTimestamp($task['last_run']['started_at']), $locale) : '—' }}</td>
                        <td class="num">{{ Format::runtime($task['last_run']['runtime'] ?? null, $locale) }}</td>
                        <td><span class="runs">@foreach (array_reverse($task['recent']) as $run)<span class="run {{ $run['status'] }}" title="{{ Format::date(Carbon::createFromTimestamp($run['started_at']), $locale) }} — {{ $t(Format::status($run['status'])) }}"></span>@endforeach</span></td>
                        <td>{{ $task['next_run_at'] ? Format::ago(Carbon::createFromTimestamp($task['next_run_at']), $locale) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $t('No scheduled task known yet. They appear once schedule:run or meridian:snapshot has run.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
