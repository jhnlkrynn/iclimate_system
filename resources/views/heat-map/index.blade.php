<x-app-layout>
    {{-- Same header and panel as the other farmer-style pages (.fad-* in farmer-advisories.css), for every role.
         The Municipal Risk Overview and the map below are filled by resources/js/barangay-risk-map.js. --}}
    <div class="fad heatmap-page">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Lian Municipality Heat Map</span>
                <h1>Barangay Heat Map</h1>
                <p>Farm risk for rice in each Lian barangay, today and by month.</p>
            </div>
        </header>

        <section class="fad-card heatmap-panel" aria-labelledby="sharedHeatMapTitle">
            <div class="fad-list-head">
                <h2 id="sharedHeatMapTitle">Lian Monthly Weather Forecast and Barangay Risk Assessment</h2>
            </div>
            <p class="fad-support" data-forecast-scope>The monthly forecast covers all of Lian. Colors show how the weather may affect farms in each barangay.</p>
            @include('partials.barangay-risk-map', [
                'heading' => 'Lian Municipality Heat Map',
                'subheading' => 'All 19 barangays at a glance. Click any barangay for details.',
                'showRefresh' => true,
                'highlightBarangay' => $highlightBarangay,
                'forecast' => true,
                'forecastAudience' => $forecastAudience ?? 'staff',
            ])
        </section>
    </div>
</x-app-layout>
