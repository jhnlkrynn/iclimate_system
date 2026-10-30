{{-- Where the catalog comes from, as one small muted line; IT Personnel also see when it was last imported. --}}
@php
    $lastImport = ($showImport ?? false) ? \App\Support\RiceVarieties\SourceDocument::lastImport() : null;
@endphp
<p class="rv-source-line" data-rv-source>
    <span>{{ __('rice_varieties.source.label') }}</span>
    @if ($showImport ?? false)
        <span aria-hidden="true">·</span>
        <span data-rv-last-import>{{ $lastImport
            ? __('rice_varieties.source.last_import', ['date' => $lastImport['at']->locale(app()->getLocale())->translatedFormat('M j, Y'), 'count' => number_format($lastImport['rows'])])
            : __('rice_varieties.source.never') }}</span>
    @endif
</p>
