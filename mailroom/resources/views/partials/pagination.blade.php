@if ($paginator->hasPages())
    <nav class="pager">
        @if ($paginator->onFirstPage()) <span class="muted">← Previous</span> @else <a href="{{ $paginator->previousPageUrl() }}">← Previous</a> @endif
        <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}">Next →</a> @else <span class="muted">Next →</span> @endif
    </nav>
@endif
