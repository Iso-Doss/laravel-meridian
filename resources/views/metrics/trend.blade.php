@if ($current !== null && $previous !== null && ($points || $previous > 0))
    @php($delta = $points ? $current - $previous : ($current - $previous) / $previous * 100)
    @if (abs($delta) >= 0.5)
        <div @class(['trend', 'bad' => $worse && $delta > 0, 'good' => $worse && $delta < 0]) title="{{ $t('compared with the previous period') }}">{{ $delta > 0 ? '▲' : '▼' }} {{ \IsoDoss\Meridian\Support\Format::decimal(abs($delta), 1, $locale) }}{{ $points ? ' pt' : ' %' }}</div>
    @endif
@endif
