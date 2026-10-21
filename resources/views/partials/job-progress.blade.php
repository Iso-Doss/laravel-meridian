@use('IsoDoss\Meridian\Support\Format')
@use('IsoDoss\Meridian\Http\Presenters\Present')
@if ($job->status === 'processing' && $job->progress !== null)
    @php($remaining = Present::remaining($job))
    <div class="job-progress" title="{{ $job->progress_message }}">
        {{-- A <progress> element: no inline style, refused by a strict CSP. --}}
        <progress class="bar" max="100" value="{{ $job->progress }}">{{ $job->progress }}%</progress>
        <span>{{ $job->progress }}%@if ($remaining !== null) · {{ $t('about :time left', ['time' => Format::seconds($remaining)]) }}@endif</span>
        @if (! empty($detailed) && $job->progress_message)<span>{{ $job->progress_message }}</span>@endif
    </div>
@endif
