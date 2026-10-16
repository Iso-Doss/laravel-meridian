@if ($paginator->hasPages())
    <div class="pagination">
        <span>{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</span>
        <span class="filters">
            @if ($paginator->onFirstPage()) <span class="btn muted">← Previous</span> @else <a class="btn" href="{{ $paginator->previousPageUrl() }}">← Previous</a> @endif
            @if ($paginator->hasMorePages()) <a class="btn" href="{{ $paginator->nextPageUrl() }}">Next →</a> @else <span class="btn muted">Next →</span> @endif
        </span>
    </div>
@endif
