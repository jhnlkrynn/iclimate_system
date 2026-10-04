<x-app-layout>
    {{-- Same header and panel as the other farmer-style pages (.fad-* in farmer-advisories.css), for every role.
         The Municipal Risk Overview and the map below are filled by resources/js/barangay-risk-map.js. --}}
    <div class="fad heatmap-page">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Lian Municipality Risk Map</span>
                <h1>Barangay Climate Risk Map</h1>
                <p>Current and monthly rice-farming risk conditions across Lian, Batangas.</p>
            </div>
        </header>

        <section class="fad-card heatmap-panel" aria-labelledby="sharedHeatMapTitle">
            <div class="fad-list-head">
                <h2 id="sharedHeatMapTitle">Lian Monthly Climate Forecast with Barangay Risk Assessment</h2>
            </div>
            <p class="fad-support" data-forecast-scope>Monthly weather forecasts apply to Lian, Batangas as a whole. Barangay colors show how the forecast conditions may affect each area based on available local risk factors.</p>
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
