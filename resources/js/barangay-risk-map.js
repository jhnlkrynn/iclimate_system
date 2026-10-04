// Fresh Leaflet implementation for the shared Lian Municipality Risk Map.
// Data and risk calculations remain server-owned; this module only renders
// the existing API response and GeoJSON boundary data.

const RISK_COLORS = { Low: '#2e7d32', Moderate: '#f2b134', High: '#e05a2b', Severe: '#8b0000', 'No Data': '#8a939b' };
const EXPECTED_BARANGAYS = 19;
const DISPLAY_NAMES = { 'Barangay 1 (Pob.)': 'Barangay 1 / Bonbon (Poblacion)', 'Barangay 5 (Pob.)': 'Barangay 5 / Tabok City (Poblacion)' };
const OFFICIAL_NAMES = ['Bagong Pook', 'Balibago', 'Barangay 1 (Pob.)', 'Barangay 2 (Pob.)', 'Barangay 3 (Pob.)', 'Barangay 4 (Pob.)', 'Barangay 5 (Pob.)', 'Binubusan', 'Bungahan', 'Cumba', 'Humayingan', 'Kapito', 'Lumaniag', 'Luyahan', 'Malaruhatan', 'Matabungkay', 'Prenza', 'Puting-Kahoy', 'San Diego'];
const nameKey = (name) => String(name || '').toLowerCase().replace(/[().,/-]/g, ' ').replace(/barangay|brgy|poblacion|\bpob\b/g, ' ').replace(/([a-z])(\d)/g, '$1 $2').replace(/\s+/g, ' ').trim();
const nameAliases = { '1 bonbon': 'Barangay 1 (Pob.)', 'bonbon': 'Barangay 1 (Pob.)', '5 tabok city': 'Barangay 5 (Pob.)', 'tabok city': 'Barangay 5 (Pob.)' };
const canonicalName = (name) => {
    const raw = String(name || '').trim();
    const key = nameKey(raw);
    return OFFICIAL_NAMES.find((official) => nameKey(official) === key) || nameAliases[key] || null;
};
const displayName = (name) => DISPLAY_NAMES[name] || name;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const riskLevel = (entry) => RISK_COLORS[entry?.risk_level] ? entry.risk_level : 'No Data';

function popupMarkup(name, entry) {
    const level = riskLevel(entry);
    return `<div class="risk-map-popup"><strong>${escapeHtml(displayName(name))}</strong><span class="risk-map-popup-risk" style="--risk-color:${RISK_COLORS[level]}">FARM RISK: ${escapeHtml(level.toUpperCase())}</span><span class="risk-map-popup-hint">Click for details</span></div>`;
}

function detailMarkup(name, entry) {
    const level = riskLevel(entry);
    const weather = entry?.weather?.status === 'fresh' ? entry.weather : null;
    const yieldData = entry?.last_recorded_yield;
    const facts = [];
    if (weather?.temperature_c != null) facts.push(`Temperature: ${escapeHtml(weather.temperature_c)}°C`);
    if (weather?.rainfall_24h_mm != null) facts.push(`Rainfall: ${escapeHtml(weather.rainfall_24h_mm)} mm`);
    if (yieldData?.value != null) facts.push(`Recorded yield: ${escapeHtml(yieldData.value)} t/ha`);
    return `<div class="risk-map-detail-content"><div><span class="risk-map-detail-kicker">Selected barangay</span><h3>${escapeHtml(displayName(name))}</h3></div><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">FARM RISK: ${escapeHtml(level.toUpperCase())}</span>${facts.length ? `<p>${facts.join(' · ')}</p>` : '<p>Current supporting details are not available.</p>'}<button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
}

export function initBarangayRiskMap() {
    const mapElement = document.getElementById('barangayRiskMap');
    if (!mapElement || !window.L || mapElement.dataset.initialized === 'true') return;
    mapElement.dataset.initialized = 'true';
    const L = window.L;
    const shell = mapElement.closest('[data-risk-map-shell]');
    const card = mapElement.closest('[data-risk-map-card]');
    const loading = shell?.querySelector('[data-risk-map-loading]');
    const error = shell?.querySelector('[data-risk-map-error]');
    const details = card?.querySelector('[data-risk-map-details]');
    const refresh = card?.querySelector('[data-risk-map-refresh]');
    const entries = new Map();
    const layers = new Map();
    // Farmer's own barangay: highlighted with a blue border and pin; every role still sees and can click all 19.
    const highlightBarangay = canonicalName(mapElement.dataset.highlightBarangay);
    let homePin = null;
    let selectedName = null;
    const map = L.map(mapElement, { zoomControl: true, scrollWheelZoom: true, zoomSnap: 0.25, minZoom: 11, maxZoom: 18 });

    // OSM base -> transparent risk polygons -> Leaflet popups/tooltips/controls.
    // OSM's native labels remain readable through the semi-transparent fills.
    const basePane = map.createPane('osmBase');
    basePane.style.zIndex = 200;
    // Covers everything outside Lian. OSM raster tiles bake place names into the image, so a
    // neighbouring town's label (e.g. Balibago, Calatagan) cannot be hidden individually; the
    // opaque mask outside the 19 boundaries leaves only Lian's own names readable.
    const maskPane = map.createPane('outsideLian');
    maskPane.style.zIndex = 350;
    maskPane.style.pointerEvents = 'none';
    let outsideMask = null;
    const polygonPane = map.createPane('riskPolygons');
    polygonPane.style.zIndex = 400;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { pane: 'osmBase', attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);

    const styleFor = (name, selected = false, hovered = false) => {
        const level = riskLevel(entries.get(name));
        const own = name === highlightBarangay;
        return { pane: 'riskPolygons', color: selected ? '#ffffff' : own ? '#0d47a1' : '#26352f', weight: selected || own ? 4 : hovered ? 3 : 1.4, opacity: 1, fillColor: RISK_COLORS[level], fillOpacity: selected ? 0.68 : 0.48, dashArray: level === 'No Data' ? '4 4' : undefined };
    };
    const showDetails = (name) => {
        if (!details) return;
        details.innerHTML = detailMarkup(name, entries.get(name));
        details.hidden = false;
        details.querySelector('[data-risk-map-detail-close]')?.addEventListener('click', () => { details.hidden = true; });
    };
    const selectLayer = (name, layer) => {
        selectedName = name;
        layers.forEach((other, otherName) => other.setStyle(styleFor(otherName, otherName === name)));
        layer.setStyle(styleFor(name, true));
        layer.bringToFront();
        showDetails(name);
    };
    const updateCounts = () => {
        const counts = { Low: 0, Moderate: 0, High: 0, Severe: 0, 'No Data': 0 };
        layers.forEach((_, name) => { counts[riskLevel(entries.get(name))] += 1; });
        Object.entries(counts).forEach(([level, count]) => { const target = card?.querySelector(`[data-risk-count="${level}"]`); if (target) target.textContent = count; });
    };
    const render = async (forceRefresh = false) => {
        loading?.removeAttribute('hidden');
        if (error) error.hidden = true;
        if (refresh) refresh.disabled = true;
        try {
            const riskUrl = forceRefresh ? `${mapElement.dataset.riskMapUrl}${mapElement.dataset.riskMapUrl.includes('?') ? '&' : '?'}refresh=1` : mapElement.dataset.riskMapUrl;
            const [boundaryResponse, riskResponse] = await Promise.all([
                fetch(mapElement.dataset.boundaryUrl, { headers: { Accept: 'application/json' } }),
                fetch(riskUrl, { headers: { Accept: 'application/json' } }),
            ]);
            if (!boundaryResponse.ok || !riskResponse.ok) throw new Error('Risk map data request failed');
            const boundary = await boundaryResponse.json();
            const risk = await riskResponse.json();
            if (risk.status !== 'ok' || !Array.isArray(boundary.features)) throw new Error('Risk map data is unavailable');
            entries.clear();
            // Coverage problems are reported, never silently dropped: an unmatched name would otherwise
            // vanish from the map (boundary) or show as No Data (risk record).
            const problems = [];
            (risk.barangays || []).forEach((entry) => {
                const canonical = canonicalName(entry.barangay);
                if (!canonical) problems.push(`risk record "${entry.barangay}" matches no official barangay`);
                else if (entries.has(canonical)) problems.push(`duplicate risk record for ${canonical}`);
                else entries.set(canonical, entry);
            });
            const featureNames = new Set();
            const acceptFeature = (feature) => {
                const props = feature?.properties || {};
                const canonical = canonicalName(props.brgy_name);
                if (props.city_name && props.city_name !== 'Lian') problems.push(`boundary "${props.brgy_name}" belongs to ${props.city_name}, not Lian`);
                else if (!canonical) problems.push(`boundary "${props.brgy_name}" matches no official barangay`);
                else if (featureNames.has(canonical)) problems.push(`duplicate boundary for ${canonical}`);
                else { featureNames.add(canonical); return true; }
                return false;
            };
            layers.forEach((layer) => layer.remove());
            layers.clear();
            const geoLayer = L.geoJSON(boundary, {
                pane: 'riskPolygons',
                filter: acceptFeature,
                style: (feature) => { const name = canonicalName(feature?.properties?.brgy_name); return styleFor(name, name === selectedName); },
                onEachFeature: (feature, layer) => {
                    const name = canonicalName(feature?.properties?.brgy_name);
                    if (!name) return;
                    layers.set(name, layer);
                    const own = name === highlightBarangay;
                    layer.bindTooltip(`<strong>${escapeHtml(displayName(name))}</strong>${own ? ' <span class="your-barangay-pill">Yours</span>' : ''}<br>Farm risk: ${escapeHtml(riskLevel(entries.get(name)))}`, { sticky: true, direction: 'top', className: own ? 'your-barangay-tooltip' : '' });
                    layer.bindPopup(popupMarkup(name, entries.get(name)), { closeButton: true, maxWidth: 240 });
                    layer.on({ mouseover: () => layer.setStyle(styleFor(name, name === selectedName, true)), mouseout: () => layer.setStyle(styleFor(name, name === selectedName)), click: () => { selectLayer(name, layer); layer.openPopup(); } });
                },
            }).addTo(map);
            updateCounts();
            outsideMask?.remove();
            // One frame around the region with every accepted barangay's outer ring cut out as a hole.
            const holes = [];
            geoLayer.eachLayer((layer) => {
                const rings = layer.getLatLngs();
                (L.LineUtil.isFlat(rings[0]) ? [rings] : rings).forEach((polygon) => holes.push(polygon[0]));
            });
            outsideMask = L.polygon([[[15.5, 119], [15.5, 122.5], [12.5, 122.5], [12.5, 119]], ...holes], {
                pane: 'outsideLian', interactive: false, stroke: false, fillColor: '#f4f7f5', fillOpacity: 1,
            }).addTo(map);
            homePin?.remove();
            homePin = null;
            const ownLayer = highlightBarangay ? layers.get(highlightBarangay) : null;
            if (ownLayer) {
                ownLayer.bringToFront();
                homePin = L.marker(ownLayer.getBounds().getCenter(), {
                    icon: L.divIcon({ className: 'risk-home-pin', html: '<span></span>', iconSize: [16, 16], iconAnchor: [8, 8] }),
                    interactive: false,
                    keyboard: false,
                }).addTo(map);
            }
            OFFICIAL_NAMES.forEach((name) => {
                if (!layers.has(name)) problems.push(`${name} has no boundary polygon`);
                if (!entries.has(name)) problems.push(`${name} has no risk record (shown as No Data)`);
            });
            if (layers.size !== EXPECTED_BARANGAYS || problems.length) console.warn(`Lian risk map: ${layers.size} of ${EXPECTED_BARANGAYS} barangays rendered.`, problems);
            const bounds = geoLayer.getBounds();
            if (bounds.isValid() && !mapElement.dataset.fitted) { map.fitBounds(bounds, { padding: [20, 20] }); mapElement.dataset.fitted = 'true'; }
        } catch (requestError) {
            console.error('iClimate could not load the Lian risk map.', requestError);
            if (error) { error.textContent = 'The risk map could not be loaded. Please try again.'; error.hidden = false; }
        } finally {
            loading?.setAttribute('hidden', 'hidden');
            if (refresh) refresh.disabled = false;
            window.setTimeout(() => map.invalidateSize(), 0);
        }
    };
    refresh?.addEventListener('click', () => render(true));
    render();
}
