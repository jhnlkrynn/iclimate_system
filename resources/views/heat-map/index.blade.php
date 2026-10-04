<x-app-layout>
    <div class="container-fluid py-3">
        <div class="page-hero mb-4">
            <div>
                <div class="eyebrow">LIAN MUNICIPALITY RISK MAP</div>
                <h1>Lian Municipality Risk Map</h1>
                <p class="mb-0">All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide.</p>
            </div>
        </div>

        <section class="card border-0 shadow-sm p-3 p-lg-4" aria-labelledby="sharedHeatMapTitle">
            <h2 id="sharedHeatMapTitle" class="h4 mb-1">Lian Municipality Risk Map</h2>
            <p class="text-muted mb-4">All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.</p>
            @include('partials.barangay-risk-map', [
                'heading' => 'Lian Municipality Risk Map',
                'subheading' => 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.',
                'showRefresh' => true,
                'highlightBarangay' => $highlightBarangay,
            ])
        </section>
    </div>
</x-app-layout>
