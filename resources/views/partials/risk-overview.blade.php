{{-- Municipal Risk Overview: a donut and four category rows above the Lian Municipality Risk Map. Every number is
     filled by resources/js/barangay-risk-map.js from the same ratings that colour the map (one count, never typed in),
     and follows the selected forecast month. Each row, the donut centre and each donut segment opens the shared
     information pop-up with the category's meaning and barangays. Without JavaScript the rows simply show "–". --}}
@php
    $overviewAudience = $overviewAudience ?? 'staff';
    $categories = [
        'low' => ['label' => 'Low Risk', 'color' => '#2e7d32', 'glyph' => 'check'],
        'moderate' => ['label' => 'Moderate Risk', 'color' => '#c98a12', 'glyph' => 'alert'],
        'high' => ['label' => 'High / Severe Risk', 'color' => '#c4471f', 'glyph' => 'danger'],
    ];
@endphp
<section class="risk-overview" data-risk-overview data-audience="{{ $overviewAudience }}" aria-labelledby="riskOverviewTitle">
    <div class="risk-overview-head">
        <div>
            <p class="risk-overview-kicker">Municipal Risk Overview</p>
            <h3 id="riskOverviewTitle" class="risk-overview-title">How Lian's barangays are rated</h3>
            <p class="risk-overview-for" data-risk-overview-for>Current conditions · loading the latest ratings…</p>
        </div>
    </div>
    <div class="risk-overview-body" aria-busy="true" data-risk-overview-body>
        <figure class="risk-donut">
            <svg viewBox="0 0 120 120" class="risk-donut-svg" data-risk-donut role="img" aria-labelledby="riskDonutText">
                <title id="riskDonutText">Barangays by farm risk level</title>
                <circle cx="60" cy="60" r="46" class="risk-donut-track"/>
            </svg>
            <button type="button" class="risk-donut-center" data-risk-category="monitored" aria-label="Barangays monitored: open the list">
                <strong data-risk-overview-total>–</strong>
                <span>Barangays</span>
            </button>
        </figure>
        <ul class="risk-overview-list">
            <li>
                <button type="button" class="risk-overview-item is-total" data-risk-category="monitored" aria-haspopup="dialog">
                    <span class="risk-overview-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'map'])</span>
                    <span class="risk-overview-name">Barangays Monitored<small>View the list and current status</small></span>
                    <strong class="risk-overview-value" data-risk-overview-count="monitored">–</strong>
                </button>
            </li>
            @foreach ($categories as $key => $c)
                <li>
                    <button type="button" class="risk-overview-item" style="--risk-color: {{ $c['color'] }}" data-risk-category="{{ $key }}" aria-haspopup="dialog">
                        <span class="risk-overview-icon is-level" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $c['glyph']])</span>
                        <span class="risk-overview-name">{{ $c['label'] }}<small>@include('dashboards.mao.icon', ['name' => 'info'])View meaning and barangays</small></span>
                        <strong class="risk-overview-value" data-risk-overview-count="{{ $key }}">–</strong>
                        <span class="risk-overview-bar" aria-hidden="true"><span data-risk-overview-bar="{{ $key }}"></span></span>
                    </button>
                </li>
            @endforeach
            <li data-risk-overview-nodata hidden>
                <button type="button" class="risk-overview-item" style="--risk-color: #8a939b" data-risk-category="nodata" aria-haspopup="dialog">
                    <span class="risk-overview-icon is-level" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'clock'])</span>
                    <span class="risk-overview-name">No Data Yet<small>Awaiting a rating</small></span>
                    <strong class="risk-overview-value" data-risk-overview-count="nodata">–</strong>
                    <span class="risk-overview-bar" aria-hidden="true"><span data-risk-overview-bar="nodata"></span></span>
                </button>
            </li>
        </ul>
    </div>
    <p class="risk-overview-check" data-risk-overview-check role="status" aria-live="polite"></p>
    {{-- Current Conditions weather health (filled by barangay-risk-map.js): how many barangays have a current
         reading, which use their last available one, when the weather was last checked, and its source. --}}
    <p class="risk-overview-check risk-overview-weather" data-risk-weather-health role="status" aria-live="polite" hidden></p>
    <p class="risk-overview-check">Farm risk is a planning guide based on available weather and agricultural data. A barangay without a rating is counted as No Data, not Low risk.</p>
</section>
