<x-app-layout>
    <style>
        .boundary-map { min-height: 520px; border-radius: 12px; overflow: hidden; border: 1px solid var(--ic-border); }
        .boundary-help { color: var(--ic-ink-mid); }
        .boundary-stat { background: var(--ic-green-50); border: 1px solid var(--ic-border); border-radius: 8px; padding: .85rem 1rem; }
        .boundary-stat strong { display: block; color: var(--ic-ink); font-size: 1.15rem; }
        .boundary-stat span { color: var(--ic-ink-mid); font-size: .85rem; }
        @media (max-width: 767.98px) { .boundary-map { min-height: 420px; } }
    </style>

    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Farm Mapping</div>
                <h1 class="h2 fw-bold mb-2">My Farm Boundary</h1>
                <p class="mb-0">Mark the complete perimeter of your planting area on the map.</p>
            </div>
            <a class="btn btn-light align-self-start align-self-lg-end" href="{{ route('farmer.dashboard') }}">Back to Dashboard</a>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body p-3 p-md-4">
            <div class="alert alert-info mb-4">
                Click around the edge of your field to create the boundary. Drag any marker to adjust it. Your exact farm location is private and visible only to you, MAO Personnel, and IT Expert users.
            </div>

            <div class="soft-section p-3 mb-4">
                <label class="form-label fw-semibold" for="farmArea">Your Land Size (hectares)</label>
                <input class="form-control @error('farm_area') is-invalid @enderror" id="farmArea" name="farm_area" type="number" min="0.01" max="100000" step="0.01" value="{{ old('farm_area', $profile->farm_area) }}" form="boundaryForm" placeholder="Example: 2.50">
                <div class="form-text">Enter the official or estimated size of your land. The system will also calculate an area from the boundary you draw.</div>
                @error('farm_area')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div id="boundaryMap" class="boundary-map mb-3" aria-label="Farm boundary map"></div>

            <form method="POST" action="{{ route('farmer.boundary.update') }}" id="boundaryForm" data-loading="true">
                @csrf
                @method('PUT')
                <input type="hidden" name="boundary_coordinates" id="boundaryCoordinates">

                @error('boundary_coordinates')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                @if ($errors->has('boundary_coordinates.*'))
                    <div class="alert alert-danger">Please check the boundary points and try again.</div>
                @endif

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3"><div class="boundary-stat"><strong id="pointCount">0</strong><span>Boundary points</span></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="boundary-stat"><strong>{{ $profile->farm_area ? number_format((float) $profile->farm_area, 2).' ha' : 'Not set' }}</strong><span>Declared land size</span></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="boundary-stat"><strong id="calculatedArea">{{ $boundary ? number_format((float) $boundary->calculated_area_hectares, 4).' ha' : 'Not calculated' }}</strong><span>Calculated area</span></div></div>
                    <div class="col-sm-6 col-lg-3"><div class="boundary-stat"><strong id="calculatedPerimeter">{{ $boundary ? number_format((float) $boundary->calculated_perimeter_meters, 2).' m' : 'Not calculated' }}</strong><span>Calculated perimeter</span></div></div>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button type="submit" class="btn btn-primary" id="saveBoundary" disabled>Save Boundary</button>
                    <button type="button" class="btn btn-outline-secondary" id="clearBoundary">Clear Map</button>
                    @if ($boundary)
                        <button type="submit" form="deleteBoundaryForm" class="btn btn-outline-danger">Delete Saved Boundary</button>
                    @endif
                </div>
            </form>

            @if ($boundary)
                <form method="POST" action="{{ route('farmer.boundary.destroy') }}" id="deleteBoundaryForm" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const Leaflet = window.L;
            if (!Leaflet) {
                document.getElementById('boundaryMap').innerHTML = '<div class="alert alert-warning m-3">The map could not load. Refresh the page or restart the frontend with <code>npm run dev</code>.</div>';
                return;
            }

            const mapElement = document.getElementById('boundaryMap');
            const form = document.getElementById('boundaryForm');
            const coordinatesInput = document.getElementById('boundaryCoordinates');
            const saveButton = document.getElementById('saveBoundary');
            const pointCount = document.getElementById('pointCount');
            const calculatedArea = document.getElementById('calculatedArea');
            const calculatedPerimeter = document.getElementById('calculatedPerimeter');
            const clearButton = document.getElementById('clearBoundary');
            const initialCoordinates = @js($boundary?->boundary_coordinates ?? []);
            const map = Leaflet.map(mapElement, { scrollWheelZoom: false }).setView([14.033, 120.650], 13);
            const satelliteLayer = Leaflet.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri',
                maxZoom: 19,
            }).addTo(map);
            const streetLayer = Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            });
            Leaflet.control.layers({ 'Satellite imagery': satelliteLayer, 'Street map': streetLayer }, null, { position: 'topright', collapsed: false }).addTo(map);

            let markers = [];
            let polygon = null;

            const earthRadius = 6371008.8;
            const measure = (points) => {
                if (points.length < 3) return null;
                const meanLatitude = points.reduce((sum, point) => sum + point.lat, 0) / points.length;
                const meanLatitudeRadians = meanLatitude * Math.PI / 180;
                const projected = points.map((point) => ({
                    x: point.lng * Math.PI / 180 * earthRadius * Math.cos(meanLatitudeRadians),
                    y: point.lat * Math.PI / 180 * earthRadius,
                }));
                let area = 0;
                let perimeter = 0;
                for (let index = 0; index < points.length; index++) {
                    const next = (index + 1) % points.length;
                    area += projected[index].x * projected[next].y - projected[next].x * projected[index].y;
                    const lat1 = points[index].lat * Math.PI / 180;
                    const lat2 = points[next].lat * Math.PI / 180;
                    const dLat = lat2 - lat1;
                    const dLng = (points[next].lng - points[index].lng) * Math.PI / 180;
                    const haversine = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
                    perimeter += 2 * earthRadius * Math.asin(Math.min(1, Math.sqrt(haversine)));
                }
                return { area: Math.abs(area) / 2 / 10000, perimeter };
            };

            const coordinates = () => markers.map((marker) => {
                const position = marker.getLatLng();
                return { lat: Number(position.lat.toFixed(7)), lng: Number(position.lng.toFixed(7)) };
            });

            const redraw = () => {
                const points = coordinates();
                polygon?.remove();
                polygon = points.length >= 3
                    ? Leaflet.polygon(points.map((point) => [point.lat, point.lng]), { color: '#1f5a46', fillColor: '#52b788', fillOpacity: .28, weight: 3 }).addTo(map)
                    : null;
                pointCount.textContent = points.length;
                saveButton.disabled = points.length < 3;
                coordinatesInput.value = points.length >= 3 ? JSON.stringify(points) : '';
                const measurements = measure(points);
                calculatedArea.textContent = measurements ? `${measurements.area.toFixed(4)} ha` : 'Not calculated';
                calculatedPerimeter.textContent = measurements ? `${measurements.perimeter.toFixed(2)} m` : 'Not calculated';
            };

            const addMarker = (point) => {
                const marker = Leaflet.marker([point.lat, point.lng], { draggable: true }).addTo(map);
                marker.on('drag', redraw);
                marker.on('contextmenu', () => {
                    marker.remove();
                    markers = markers.filter((item) => item !== marker);
                    redraw();
                });
                markers.push(marker);
            };

            map.on('click', (event) => {
                addMarker({ lat: event.latlng.lat, lng: event.latlng.lng });
                redraw();
            });

            initialCoordinates.forEach(addMarker);
            if (initialCoordinates.length >= 3) {
                map.fitBounds(initialCoordinates.map((point) => [point.lat, point.lng]), { padding: [24, 24] });
            }
            redraw();

            clearButton.addEventListener('click', () => {
                markers.forEach((marker) => marker.remove());
                markers = [];
                redraw();
            });

            form.addEventListener('submit', (event) => {
                if (markers.length < 3) {
                    event.preventDefault();
                    window.alert('Add at least three points around your field before saving.');
                }
            });

            document.getElementById('deleteBoundaryForm')?.addEventListener('submit', (event) => {
                if (!window.confirm('Delete the saved farm boundary?')) event.preventDefault();
            });
        });
    </script>
</x-app-layout>
