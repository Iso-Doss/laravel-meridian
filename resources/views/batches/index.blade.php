@extends('meridian::layout', ['title' => 'Batches', 'poll' => true])
@use('Kakodjenou\Meridian\Support\Format')

@section('content')
    <h1>Batches</h1>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Batch</th><th>Progress</th><th class="num">Jobs</th><th class="num">Pending</th><th class="num">Failed</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                @forelse ($batches as $batch)
                    @php($done = $batch->totalJobs - $batch->pendingJobs)
                    <tr>
                        <td><a href="{{ route('meridian.batches.show', $batch->id) }}">{{ $batch->name ?: $batch->id }}</a></td>
                        <td>
                            <div class="progress">
                                <span class="ok" style="width: {{ $batch->totalJobs ? ($done - $batch->failedJobs) / $batch->totalJobs * 100 : 0 }}%"></span>
                                <span class="bad" style="width: {{ $batch->totalJobs ? $batch->failedJobs / $batch->totalJobs * 100 : 0 }}%"></span>
                            </div>
                        </td>
                        <td class="num">{{ number_format($batch->totalJobs) }}</td>
                        <td class="num">{{ number_format($batch->pendingJobs) }}</td>
                        <td class="num">{{ number_format($batch->failedJobs) }}</td>
                        <td>
                            @if ($batch->cancelled()) <span class="badge cancelled">cancelled</span>
                            @elseif ($batch->finished()) <span class="badge {{ $batch->failedJobs ? 'failed' : 'finished' }}">{{ $batch->failedJobs ? 'finished with failures' : 'finished' }}</span>
                            @else <span class="badge pending">running</span>
                            @endif
                        </td>
                        <td>{{ Format::ago($batch->createdAt) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No batches (or no <code>job_batches</code> table).</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
