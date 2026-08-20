<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Record Details</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }} Details</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Record #{{ $record->id }}</p>
            </div>
            <div class="d-flex gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-outline-secondary" href="{{ route($routeName.'.index') }}">Back</a>
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                @endif
            </div>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body">
            <dl class="row mb-0 details-list g-3">
                @foreach ($columns as $field => $label)
                    <dt class="col-sm-3">{{ $label }}</dt>
                    <dd class="col-sm-9">{{ data_get($record, $field) }}</dd>
                @endforeach
                <dt class="col-sm-3">Created</dt>
                <dd class="col-sm-9">{{ $record->created_at }}</dd>
                <dt class="col-sm-3">Updated</dt>
                <dd class="col-sm-9">{{ $record->updated_at }}</dd>
            </dl>
        </div>
    </div>

    @if ($routeName === 'farmer-profiles' && $record->farmBoundary)
        <div class="card no-lift mt-4">
            <div class="card-header border-0">
                <div class="fw-semibold">Mapped Farm Boundary</div>
                <div class="small text-muted">Exact coordinates are restricted to authorized staff.</div>
            </div>
            <div class="card-body">
                <div id="staffBoundaryMap" style="height: 430px; border-radius: 10px; overflow: hidden;"></div>
                <div class="row g-3 mt-1">
                    <div class="col-sm-6"><strong>{{ number_format((float) $record->farmBoundary->calculated_area_hectares, 4) }} ha</strong><div class="small text-muted">Calculated mapped area</div></div>
                    <div class="col-sm-6"><strong>{{ number_format((float) $record->farmBoundary->calculated_perimeter_meters, 2) }} m</strong><div class="small text-muted">Calculated perimeter</div></div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const Leaflet = window.L;
                if (!Leaflet) return;
                const points = @js($record->farmBoundary->boundary_coordinates);
                const map = Leaflet.map('staffBoundaryMap', { scrollWheelZoom: false, dragging: false }).setView([14.033, 120.650], 13);
                const satelliteLayer = Leaflet.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles &copy; Esri', maxZoom: 19 }).addTo(map);
                const streetLayer = Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 });
                Leaflet.control.layers({ 'Satellite imagery': satelliteLayer, 'Street map': streetLayer }, null, { position: 'topright', collapsed: false }).addTo(map);
                const polygon = Leaflet.polygon(points.map((point) => [point.lat, point.lng]), { color: '#1f5a46', fillColor: '#52b788', fillOpacity: .28, weight: 3 }).addTo(map);
                map.fitBounds(polygon.getBounds(), { padding: [24, 24] });
            });
        </script>
    @elseif ($routeName === 'farmer-profiles')
        <div class="alert alert-secondary mt-4">This farmer has not saved a farm boundary.</div>
    @endif
</x-app-layout>
