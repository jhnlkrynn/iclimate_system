{{-- Municipal Risk: the Municipal Risk Overview (donut + clickable categories) and the Lian Municipality Risk Map,
     in one card, both drawn by resources/js/barangay-risk-map.js from the same ratings (/api/lian/risk-map, the source
     MaoDashboardService::risk() also reads). Not a live slot: re-rendering it would rebuild the map; Refresh Data asks
     the map to reload instead. --}}
@php $r = $mao['risk']; @endphp
@if (($r['state'] ?? null) === 'ok')
    <section class="mtd-card" id="mtdRiskMap" aria-label="Municipal risk overview and risk map" data-mao-risk>
        @include('partials.barangay-risk-map', [
            'heading' => 'Lian Municipality Risk Map',
            'subheading' => 'All 19 barangays at a glance. Click a barangay for its weather, farm risk and advice.',
            'showRefresh' => true,
            'overview' => true,
        ])
    </section>
@else
    <section class="mtd-card" aria-labelledby="mtdRiskTitle" data-mao-risk-empty>
        <div class="mtd-card-head"><h2 id="mtdRiskTitle">Municipal Risk Overview</h2></div>
        <div class="mtd-empty" role="status">
            @if (($r['state'] ?? null) === 'error')<strong>Farm risk ratings are temporarily unavailable.</strong><span>Please try again shortly.</span>
            @else<strong>No data available.</strong><span>No barangay has a calculated farm-risk rating yet.</span>@endif
        </div>
    </section>
@endif
