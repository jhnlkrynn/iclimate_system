<x-app-layout>
    <div class="container-fluid py-3">
        <div class="page-hero mb-4">
            <div>
                <div class="eyebrow">LIAN MUNICIPALITY RISK MAP</div>
                <h1>Barangay Climate Risk Map</h1>
                <p class="mb-0">Current and monthly rice-farming risk conditions across Lian, Batangas.</p>
            </div>
        </div>

        <section class="card border-0 shadow-sm p-3 p-lg-4" aria-labelledby="sharedHeatMapTitle">
            <h2 id="sharedHeatMapTitle" class="h4 mb-1">Lian Monthly Climate Forecast with Barangay Risk Assessment</h2>
            <p class="text-muted mb-4" data-forecast-scope>Monthly weather forecasts apply to Lian, Batangas as a whole. Barangay colors show how the forecast conditions may affect each area based on available local risk factors.</p>
            @include('partials.barangay-risk-map', [
                'heading' => 'Lian Municipality Risk Map',
                'subheading' => 'All 19 barangays at a glance. Click any barangay for details.',
                'showRefresh' => true,
                'highlightBarangay' => $highlightBarangay,
                'forecast' => true,
                'forecastAudience' => $forecastAudience ?? 'staff',
            ])
        </section>
    </div>
</x-app-layout>
