@extends('meridian::layout', ['title' => $t('Errors'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>
        {{ $t('Errors') }}
        @if ($groups !== [])
            <span class="filters actions">
                <a class="btn" href="{{ route('meridian.errors.export', array_filter(['queue' => $queue, 'format' => 'csv'])) }}" download>⤓ CSV</a>
                <a class="btn" href="{{ route('meridian.errors.export', array_filter(['queue' => $queue, 'format' => 'json'])) }}" download>⤓ JSON</a>
            </span>
        @endif
    </h1>

    @unless ($ready)
        <div class="flash error">{{ $t('Run the migrations of Meridian to group the failed jobs by error (php artisan vendor:publish --tag=meridian-migrations, then php artisan migrate).') }}</div>
    @endunless

    @php($retryable = $canManage && $groups !== [])
    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>{{ $t('Failed jobs grouped by error') }}</h2>
                <span class="muted">{{ $t(':count failed job(s), :groups distinct error(s)', ['count' => Format::integer(array_sum(array_column($groups, 'count')), $locale), 'groups' => Format::integer(count($groups), $locale)]) }}</span>
            </div>
            <form method="get" class="filters">
                <select name="queue" data-autosubmit aria-label="{{ $t('Filter by queue') }}">
                    <option value="">{{ $t('All queues') }}</option>
                    @foreach ($queues as $name)
                        <option value="{{ $name }}" @selected($queue === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Error') }}</th><th>{{ $t('Jobs') }}</th><th class="num">{{ $t('Occurrences') }}</th><th>{{ $t('First seen') }}</th><th>{{ $t('Last seen') }}</th>@if ($retryable)<th class="num sticky-actions">{{ $t('Actions') }}</th>@endif</tr></thead>
                <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td class="wrap">
                            <a href="{{ $group['url'] }}" title="{{ $group['class'] }}"><strong>{{ class_basename($group['class']) }}</strong></a>
                            <div class="error-message">{{ \Illuminate\Support\Str::limit($group['message'], 300) ?: '—' }}</div>
                            @if ($group['file'])<div class="error-file mono">{{ $group['file'] }}:{{ $group['line'] }}</div>@endif
                        </td>
                        <td class="wrap">
                            @foreach (array_slice($group['jobs'], 0, 3) as $job)<span class="tag" title="{{ $job }}">{{ class_basename($job) }}</span>@endforeach
                            @if (count($group['jobs']) > 3)<span class="muted">+{{ count($group['jobs']) - 3 }}</span>@endif
                            <div class="muted">{{ implode(', ', $group['queues']) }}</div>
                        </td>
                        <td class="num"><a href="{{ $group['url'] }}">{{ Format::integer($group['count'], $locale) }}</a></td>
                        <td title="{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($group['first_seen']), $locale) }}">{{ Format::ago(\Illuminate\Support\Carbon::createFromTimestamp($group['first_seen']), $locale) }}</td>
                        <td title="{{ Format::date(\Illuminate\Support\Carbon::createFromTimestamp($group['last_seen']), $locale) }}"><a href="{{ $group['sample_url'] }}">{{ Format::ago(\Illuminate\Support\Carbon::createFromTimestamp($group['last_seen']), $locale) }}</a></td>
                        @if ($retryable)
                            <td class="num sticky-actions">
                                <form class="inline" method="post" action="{{ route('meridian.jobs.retry-all') }}" data-confirm="{{ $t('Retry :count failed job(s)?', ['count' => Format::integer($group['count'], $locale)]) }}">
                                    @csrf
                                    <input type="hidden" name="error" value="{{ $group['fingerprint'] }}">
                                    @if ($queue)<input type="hidden" name="queue" value="{{ $queue }}">@endif
                                    <button class="btn">↻ {{ $t('Retry all') }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">{{ $t('No failed job. Well done!') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
