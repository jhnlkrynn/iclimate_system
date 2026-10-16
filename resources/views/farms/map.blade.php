<x-app-layout>
    @vite('resources/css/leaflet.css')
    {{-- One farm's map (FarmController::map): the farmer marks the farm's outline; the server checks it is inside Lian,
         finds its barangay and measures its size (the same drawing as the earlier My Farm Map page). --}}
    <div class="fad frec ffm" data-farm-map-page>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farms.title') }}</span>
                <h1>{{ __('farms.map.title', ['name' => $farm->farm_name]) }}</h1>
                <p>{{ __('farms.map.intro') }}</p>
            </div>
            <div class="fad-hero-actions">
                <a class="fad-btn is-light" href="{{ route('farmer.my-farm') }}">{{ __('farms.map.back') }}</a>
            </div>
        </header>

        <section class="frec-panel">
            <p class="fad-m-small mb-0">{{ __('farms.map.private') }}</p>

            @if ($errors->has('boundary_coordinates') || $errors->has('boundary_coordinates.*'))
                <div class="frec-note is-danger" role="alert">{{ $errors->first('boundary_coordinates') ?: $errors->first('boundary_coordinates.*') }}</div>
            @endif
            @if ($boundary?->barangay)
                <p class="frec-takeaway mb-0">{{ __('farms.map.in_barangay', ['barangay' => \App\Services\Risk\HeatMapPanel::displayName($boundary->barangay)]) }}</p>
            @endif

            <div id="farmMap" class="ffm-map" role="application" aria-label="{{ __('farms.map.label', ['name' => $farm->farm_name]) }}"></div>

            <form method="POST" action="{{ route('farmer.farms.map.update', $farm) }}" id="farmMapForm" data-loading="true">
                @csrf
                @method('PUT')
                <input type="hidden" name="boundary_coordinates" id="farmMapCoordinates">

                <dl class="fmf-facts ffm-map-stats">
                    <div><dt>{{ __('farms.map.points') }}</dt><dd id="farmMapPoints">0</dd></div>
                    <div><dt>{{ __('farms.map.size_you_gave') }}</dt><dd>{{ $size['text'] }}</dd></div>
                    <div><dt>{{ __('farms.map.area') }}</dt><dd id="farmMapArea">{{ $boundary ? \App\Support\FarmSize::hectares((float) $boundary->calculated_area_hectares) : __('farms.map.not_calculated') }}</dd></div>
                    <div><dt>{{ __('farms.map.perimeter') }}</dt><dd id="farmMapPerimeter">{{ $boundary ? number_format((float) $boundary->calculated_perimeter_meters, 0).' m' : __('farms.map.not_calculated') }}</dd></div>
                </dl>

                <div class="ffm-actions">
                    <button type="submit" class="fad-btn" id="farmMapSave" disabled>{{ __('farms.map.save') }}</button>
                    <button type="button" class="fad-btn is-quiet" id="farmMapClear">{{ __('farms.map.clear') }}</button>
                    @if ($boundary)
                        <button type="submit" form="farmMapDelete" class="fad-btn is-quiet">{{ __('farms.map.delete') }}</button>
                    @endif
                </div>
            </form>

            @if ($boundary)
                <form method="POST" action="{{ route('farmer.farms.map.destroy', $farm) }}" id="farmMapDelete" class="d-none" data-ic-confirm="{{ __('farms.map.delete_confirm', ['name' => $farm->farm_name]) }}">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            // app.js loads Leaflet lazily; wait for it instead of racing it.
            try { await window.iClimateVisualsReady; } catch (e) { /* handled below */ }
            const L = window.L;
            const mapElement = document.getElementById('farmMap');
            if (!L) { mapElement.innerHTML = `<p class="frec-note m-3">${@js(__('farms.map.failed_load'))}</p>`; return; }

            const form = document.getElementById('farmMapForm');
            const input = document.getElementById('farmMapCoordinates');
            const save = document.getElementById('farmMapSave');
            const pointCount = document.getElementById('farmMapPoints');
            const areaOut = document.getElementById('farmMapArea');
            const perimeterOut = document.getElementById('farmMapPerimeter');
            const notCalculated = @js(__('farms.map.not_calculated'));
            const haLabel = @js(__('farms.size.hectares', ['value' => '__v__']));
            const initial = @js($boundary?->boundary_coordinates ?? []);
            // Start at the farm's barangay when there is no outline yet.
            const start = @js(\App\Support\LianBarangays::coordinateFor((string) $farm->barangay) ?? [14.033, 120.650]);
            const map = L.map(mapElement, { scrollWheelZoom: false }).setView(start, initial.length ? 13 : 15);
            const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles &copy; Esri', maxZoom: 19 }).addTo(map);
            const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 });
            L.control.layers({ [@js(__('farms.map.satellite'))]: satellite, [@js(__('farms.map.street'))]: street }, null, { position: 'topright', collapsed: true }).addTo(map);

            let markers = [];
            let polygon = null;
            const R = 6371008.8;
            const measure = (points) => {
                if (points.length < 3) return null;
                const mean = points.reduce((s, p) => s + p.lat, 0) / points.length * Math.PI / 180;
                const xy = points.map((p) => ({ x: p.lng * Math.PI / 180 * R * Math.cos(mean), y: p.lat * Math.PI / 180 * R }));
                let area = 0; let perimeter = 0;
                for (let i = 0; i < points.length; i++) {
                    const j = (i + 1) % points.length;
                    area += xy[i].x * xy[j].y - xy[j].x * xy[i].y;
                    const a1 = points[i].lat * Math.PI / 180; const a2 = points[j].lat * Math.PI / 180;
                    const h = Math.sin((a2 - a1) / 2) ** 2 + Math.cos(a1) * Math.cos(a2) * Math.sin((points[j].lng - points[i].lng) * Math.PI / 360) ** 2;
                    perimeter += 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
                }
                return { area: Math.abs(area) / 2 / 10000, perimeter };
            };
            const coords = () => markers.map((m) => ({ lat: Number(m.getLatLng().lat.toFixed(7)), lng: Number(m.getLatLng().lng.toFixed(7)) }));
            const redraw = () => {
                const points = coords();
                polygon?.remove();
                polygon = points.length >= 3 ? L.polygon(points.map((p) => [p.lat, p.lng]), { color: '#1f5a46', fillColor: '#52b788', fillOpacity: .28, weight: 3 }).addTo(map) : null;
                pointCount.textContent = points.length;
                save.disabled = points.length < 3;
                input.value = points.length >= 3 ? JSON.stringify(points) : '';
                const m = measure(points);
                areaOut.textContent = m ? haLabel.replace('__v__', m.area.toFixed(2)) : notCalculated;
                perimeterOut.textContent = m ? `${Math.round(m.perimeter)} m` : notCalculated;
            };
            const icon = L.divIcon({ className: 'ffm-point', iconSize: [18, 18], iconAnchor: [9, 9] });
            const addMarker = (p) => {
                const marker = L.marker([p.lat, p.lng], { draggable: true, icon }).addTo(map);
                marker.on('drag', redraw);
                marker.on('contextmenu', () => { marker.remove(); markers = markers.filter((m) => m !== marker); redraw(); });
                markers.push(marker);
            };
            map.on('click', (e) => { addMarker({ lat: e.latlng.lat, lng: e.latlng.lng }); redraw(); });
            initial.forEach(addMarker);
            if (initial.length >= 3) map.fitBounds(initial.map((p) => [p.lat, p.lng]), { padding: [24, 24] });
            redraw();
            document.getElementById('farmMapClear').addEventListener('click', () => { markers.forEach((m) => m.remove()); markers = []; redraw(); });
            form.addEventListener('submit', (e) => {
                if (e.submitter && e.submitter.getAttribute('form') === 'farmMapDelete') return;
                if (markers.length < 3) { e.preventDefault(); window.alert(@js(__('farms.map.need_points'))); }
            });
        });
    </script>
</x-app-layout>
