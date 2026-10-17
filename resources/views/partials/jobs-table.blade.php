@use('IsoDoss\Meridian\Support\Format')
@php($retryable = collect($jobs->items())->contains(fn ($job) => $job->status === 'failed'))
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>{{ $t('Job') }}</th>
            <th>{{ $t('Queue') }}</th>
            <th>{{ $t('Status') }}</th>
            <th class="num">{{ $t('Attempts') }}</th>
            <th>{{ $t('Queued') }}</th>
            <th class="num">{{ $t('Wait') }}</th>
            <th class="num">{{ $t('Runtime') }}</th>
            @if ($retryable)<th class="num sticky-actions">{{ $t('Actions') }}</th>@endif
        </tr>
        </thead>
        <tbody>
        @forelse ($jobs as $job)
            <tr>
                <td class="wrap">
                    <a href="{{ route('meridian.jobs.show', $job->uuid) }}" title="{{ $job->name }}">{{ $job->shortName() }}</a>
                    <div>@foreach (array_slice($job->tags ?? [], 0, 4) as $tag)<a class="tag" href="{{ route('meridian.jobs.index', ['tag' => $tag]) }}">{{ $tag }}</a>@endforeach</div>
                </td>
                <td><span class="muted">{{ $job->connection }}:</span>{{ $job->queue }}</td>
                <td>
                    <span class="badge {{ $job->status }}">{{ $t(Format::status($job->isDelayed() ? 'delayed' : $job->status)) }}</span>
                    @if ($job->retries) <span class="muted" title="{{ $t('Manual retries') }}">↻{{ $job->retries }}</span> @endif
                </td>
                <td class="num">{{ $job->attempts }}</td>
                <td title="{{ Format::date($job->queued_at, $locale) }}">{{ Format::ago($job->queued_at, $locale) }}</td>
                <td class="num">{{ Format::seconds($job->wait) }}</td>
                <td class="num">{{ Format::runtime($job->runtime, $locale) }}</td>
                @if ($retryable)
                    <td class="num sticky-actions">
                        @if ($job->status === 'failed')
                            <form class="inline" method="post" action="{{ route('meridian.jobs.retry', $job->uuid) }}" data-confirm="{{ $t('Retry this job?') }}">@csrf <button class="btn">↻ {{ $t('Retry') }}</button></form>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $retryable ? 8 : 7 }}" class="empty">{{ $t('No jobs to show.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('meridian::partials.pagination', ['paginator' => $jobs])
