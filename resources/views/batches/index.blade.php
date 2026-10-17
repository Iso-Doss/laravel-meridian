@extends('meridian::layout', ['title' => $t('Batches'), 'poll' => true])
@use('IsoDoss\Meridian\Support\Format')

@section('content')
    <h1>{{ $t('Batches') }}</h1>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ $t('Batch') }}</th><th>{{ $t('Progress') }}</th><th class="num">{{ $t('Jobs') }}</th><th class="num">{{ $t('Pending') }}</th><th class="num">{{ $t('Failed') }}</th><th>{{ $t('Status') }}</th><th>{{ $t('Created') }}</th></tr></thead>
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
                        <td class="num">{{ Format::integer($batch->totalJobs, $locale) }}</td>
                        <td class="num">{{ Format::integer($batch->pendingJobs, $locale) }}</td>
                        <td class="num">{{ Format::integer($batch->failedJobs, $locale) }}</td>
                        <td>
                            @if ($batch->cancelled()) <span class="badge cancelled">{{ $t('Cancelled') }}</span>
                            @elseif ($batch->finished()) <span class="badge {{ $batch->failedJobs ? 'failed' : 'finished' }}">{{ $batch->failedJobs ? $t('Finished with failures') : $t('Finished') }}</span>
                            @else <span class="badge pending">{{ $t('Running') }}</span>
                            @endif
                        </td>
                        <td title="{{ Format::date($batch->createdAt, $locale) }}">{{ Format::ago($batch->createdAt, $locale) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">{{ $t('No batches (or no job_batches table).') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
