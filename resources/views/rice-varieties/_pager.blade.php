{{-- Previous / Next with "1–25 of 526" (the catalog keeps its filters in the links). --}}
@if ($page->total() > 0)
    <nav class="rv-pager" aria-label="{{ __('rice_varieties.catalog.pages') }}" data-rv-pager>
        <span class="rv-pager-range" data-rv-range>{{ __('rice_varieties.catalog.range', ['from' => number_format($page->firstItem()), 'to' => number_format($page->lastItem()), 'total' => number_format($page->total())]) }}</span>
        <span class="rv-pager-links">
            @if ($page->onFirstPage())
                <span class="rv-btn is-quiet is-compact is-disabled" aria-disabled="true">‹ {{ __('rice_varieties.catalog.previous') }}</span>
            @else
                <a class="rv-btn is-quiet is-compact" href="{{ $page->previousPageUrl() }}" rel="prev">‹ {{ __('rice_varieties.catalog.previous') }}</a>
            @endif
            @if ($page->hasMorePages())
                <a class="rv-btn is-quiet is-compact" href="{{ $page->nextPageUrl() }}" rel="next">{{ __('rice_varieties.catalog.next') }} ›</a>
            @else
                <span class="rv-btn is-quiet is-compact is-disabled" aria-disabled="true">{{ __('rice_varieties.catalog.next') }} ›</span>
            @endif
        </span>
    </nav>
@endif
