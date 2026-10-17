@use('IsoDoss\Meridian\Support\Format')
@if ($paginator->hasPages())
    <div class="pagination">
        <span>{{ $t(':from–:to of :total', ['from' => Format::integer($paginator->firstItem(), $locale), 'to' => Format::integer($paginator->lastItem(), $locale), 'total' => Format::integer($paginator->total(), $locale)]) }}</span>
        <span class="filters">
            @if ($paginator->onFirstPage()) <span class="btn muted">← {{ $t('Previous') }}</span> @else <a class="btn" href="{{ $paginator->previousPageUrl() }}">← {{ $t('Previous') }}</a> @endif
            @if ($paginator->hasMorePages()) <a class="btn" href="{{ $paginator->nextPageUrl() }}">{{ $t('Next') }} →</a> @else <span class="btn muted">{{ $t('Next') }} →</span> @endif
        </span>
    </div>
@endif
