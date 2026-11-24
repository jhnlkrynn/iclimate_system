@php
    $noteLocale = $locale ?? app()->getLocale();
    $noteUpdatedAt = $updatedAt ?? null;
@endphp
<p class="ic-forecast-note" data-forecast-prediction-note>
    <span class="ic-forecast-note-icon" aria-hidden="true">
        <svg width="15" height="15" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M9 8v4.5M9 5.5v.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
    </span>
    <span class="ic-forecast-note-body">
        <span>{{ __('farmer.predictions.prediction_note', [], $noteLocale) }}</span>
        <span class="ic-forecast-note-meta">
            {{ __('farmer.predictions.source_open_meteo', [], $noteLocale) }}@if ($noteUpdatedAt) · <x-updated-at :at="$noteUpdatedAt" :label="__('farmer.home.updated', [], $noteLocale)" />@endif
        </span>
    </span>
</p>
