@php $missing = $f['unavailable'] ?? []; @endphp
@if (count($missing) > 0)
    <div class="mfd-soft" role="status" data-mfd-notice>
        <span>Some information isn't available right now: {{ implode(', ', $missing) }}. The rest of your dashboard is up to date.</span>
        <button type="button" class="mfd-btn is-quiet" data-mfd-retry>Try again</button>
    </div>
@endif
