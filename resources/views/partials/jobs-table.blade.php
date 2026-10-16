@use('Kakodjenou\Meridian\Support\Format')
<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Job</th>
            <th>Queue</th>
            <th>Status</th>
            <th class="num">Attempts</th>
            <th>Queued</th>
            <th class="num">Wait</th>
            <th class="num">Runtime</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($jobs as $job)
            <tr>
                <td class="wrap">
                    <a href="{{ route('meridian.jobs.show', $job->uuid) }}" title="{{ $job->name }}">{{ $job->shortName() }}</a>
                    <div>@foreach (array_slice($job->tags ?? [], 0, 4) as $tag)<span class="tag">{{ $tag }}</span>@endforeach</div>
                </td>
                <td><span class="muted">{{ $job->connection }}:</span>{{ $job->queue }}</td>
                <td>
                    <span class="badge {{ $job->status }}">{{ $job->isDelayed() ? 'delayed' : $job->status }}</span>
                    @if ($job->retries) <span class="muted" title="Manual retries">↻{{ $job->retries }}</span> @endif
                </td>
                <td class="num">{{ $job->attempts }}</td>
                <td title="{{ Format::date($job->queued_at) }}">{{ Format::ago($job->queued_at) }}</td>
                <td class="num">{{ Format::seconds($job->wait) }}</td>
                <td class="num">{{ Format::runtime($job->runtime) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No jobs to show.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('meridian::partials.pagination', ['paginator' => $jobs])
