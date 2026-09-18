@php
    $key = $key ?? '';
@endphp
<div class="chart-interpretation" data-interpretation="{{ $key }}" aria-live="polite">
    <div class="chart-interpretation-head">
        <span class="chart-interpretation-label">Interpretation</span>
        <span class="status-pill risk-badge-lg" data-role="badge">Loading&hellip;</span>
    </div>
    <div class="chart-interpretation-row" data-role="text">Waiting for enough weather data to explain this chart.</div>
    <div class="chart-interpretation-row chart-interpretation-action">
        <strong>Recommended Action:</strong> <span data-role="action">&mdash;</span>
    </div>
</div>
