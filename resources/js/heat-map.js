// Heat Map page (resources/views/heat-map/index.blade.php): the map on the left and one panel on the right.
// Every rating and every sentence is already in the page for both ranges, so this module only colours the 19
// barangays, switches [This Week] / [2 Weeks], and shows the clicked barangay's panel. No request on click. When the
// page opened while new weather was on its way (data-heat-map-updating), it reads the page again a few times and
// swaps in the panel, legend and colours once a forecast is there.

import { canonicalName, displayName } from './barangay-risk-map';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

export function initHeatMap() {
    const root = document.querySelector('[data-heat-map]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';

    const canvas = root.querySelector('#heatMapCanvas');
    let views = [...root.querySelectorAll('[data-heat-map-view]')];
    const readLevels = (scope) => JSON.parse(scope.querySelector('[data-heat-map-levels]')?.textContent || '{}');
    let data = { levels: {}, colors: {}, labels: {}, yours: '' };
    try {
        data = readLevels(root);
    } catch (error) {
        console.error('iClimate could not read the heat map ratings.', error);
    }
    const own = canonicalName(canvas?.dataset.ownBarangay);
    const layers = new Map();
    // Manuscript – Naicel (2026-10-08): a range may also be a map layer (flood / drought / yield) listed in data.layers.
    const validRange = (key) => (key === '2weeks' || (data.layers || []).includes(key) ? key : 'week');
    let range = validRange(root.dataset.range);
    let selected = null;

    const levelOf = (name) => data.levels?.[range]?.[name] || 'nodata';
    const labelOf = (level) => data.labelsByRange?.[range]?.[level] || data.labels?.[level] || '';
    const styleFor = (name, hovered = false) => {
        const level = levelOf(name);
        const isSelected = name === selected;
        return {
            pane: 'hmxPolygons',
            color: isSelected ? '#111c17' : name === own ? '#0d47a1' : '#26352f',
            weight: isSelected ? 4 : name === own ? 3.5 : hovered ? 3 : 1.4,
            opacity: 1,
            fillColor: data.colors?.[level] || '#8a939b',
            fillOpacity: isSelected ? 0.78 : 0.56,
            dashArray: level === 'nodata' ? '4 4' : undefined,
        };
    };
    // Panel revision 2.1: name, risk level, the main weather factor and the possible crop impact.
    const tooltipFor = (name) => {
        const level = levelOf(name);
        const detail = data.details?.[range]?.[name] || {};
        const dot = `<span class="hmx-tip-dot" style="background:${escapeHtml(data.colors?.[level] || '#9CA3AF')}"></span>`;
        return `<strong>${escapeHtml(displayName(name))}</strong>${name === own ? ` <span class="your-barangay-pill">${escapeHtml(data.yours)}</span>` : ''}`
            + `<br>${dot}<b>${escapeHtml(labelOf(level))}</b>`
            + (detail.factor ? `<br><span class="hmx-tip-line"><em>${escapeHtml(data.detailLabels?.factor || '')}:</em> ${escapeHtml(detail.factor)}</span>` : '')
            + (detail.impact ? `<br><span class="hmx-tip-line"><em>${escapeHtml(data.detailLabels?.impact || '')}:</em> ${escapeHtml(detail.impact)}</span>` : '');
    };

    const show = () => {
        const key = `${range}:${selected || 'summary'}`;
        views.forEach((view) => { view.hidden = view.dataset.heatMapView !== key; });
        // Farmers: the own-barangay card above the map follows [This Week] / [2 Weeks] (a map layer keeps this week's).
        root.querySelectorAll('[data-heat-map-mine-range]').forEach((card) => { card.hidden = card.dataset.heatMapMineRange !== (range === '2weeks' ? '2weeks' : 'week'); });
        root.dataset.range = range;
        root.querySelectorAll('[data-heat-map-range]').forEach((button) => {
            const active = button.dataset.heatMapRange === range;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        // Manuscript – Naicel: the legend and the layer choice follow the shown range.
        const isLayer = (data.layers || []).includes(range);
        root.querySelectorAll('[data-heat-map-legend]').forEach((legend) => { legend.hidden = legend.dataset.heatMapLegend !== (isLayer ? range : 'risk'); });
        const layerSelect = root.querySelector('[data-heat-map-layer]');
        if (layerSelect) layerSelect.value = isLayer ? range : '';
        layers.forEach((layer, name) => layer.setStyle(styleFor(name)));
        if (selected) layers.get(selected)?.bringToFront();
    };
    const pick = (name, fromPanel = false) => {
        selected = name && data.levels?.[range]?.[name] !== undefined ? name : null;
        show();
        // On a phone the panel sits under the map: bring the answer into view after a map click.
        if (selected && !fromPanel && window.matchMedia('(max-width: 991.98px)').matches) {
            root.querySelector('[data-heat-map-panel]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const setRange = (key) => {
        range = validRange(key);
        if (selected && data.levels?.[range]?.[selected] === undefined) selected = null;
        const url = new URL(window.location.href);
        if (range === 'week') url.searchParams.delete('range'); else url.searchParams.set('range', range);
        window.history.replaceState(null, '', url);
        show();
    };
    // Manuscript – Naicel: choosing a map layer shows it; the empty choice goes back to this week's risk.
    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-heat-map-layer]')) setRange(event.target.value || 'week');
    });

    root.addEventListener('click', (event) => {
        const rangeButton = event.target.closest('[data-heat-map-range]');
        if (rangeButton) {
            event.preventDefault();
            setRange(rangeButton.dataset.heatMapRange);
            return;
        }
        const pickButton = event.target.closest('[data-heat-map-pick]');
        if (pickButton) pick(canonicalName(pickButton.dataset.heatMapPick), true);
    });

    // New weather is on its way: read this page again after 5, 15 and 30 seconds (the shared refresh runs once on the
    // server however often it is read), and stop as soon as no barangay is missing its forecast.
    const hasRating = (levels) => Object.values(levels || {}).some((range) => Object.values(range || {}).some((level) => level !== 'nodata'));
    const missing = (levels) => Object.values(levels || {}).some((range) => Object.values(range || {}).includes('nodata'));
    const update = (delays) => {
        if (!delays.length) return;
        window.setTimeout(() => {
            fetch(window.location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' })
                .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`Heat Map page answered ${response.status}`))))
                .then((html) => {
                    const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-heat-map]');
                    const next = fresh ? readLevels(fresh) : null;
                    if (!next || !hasRating(next.levels)) return update(delays.slice(1));
                    data = next;
                    ['[data-heat-map-panel]', '[data-heat-map-legend="risk"]', '[data-heat-map-mine]'].forEach((selector) => {   // Manuscript – Naicel: the risk legend (layer legends come first now)
                        const target = root.querySelector(selector);
                        const source = fresh.querySelector(selector);
                        if (target && source) target.innerHTML = source.innerHTML;
                    });
                    views = [...root.querySelectorAll('[data-heat-map-view]')];
                    if (selected && data.levels?.[range]?.[selected] === undefined) selected = null;
                    show();
                    if (missing(next.levels) && fresh.hasAttribute('data-heat-map-updating')) update(delays.slice(1));
                    return undefined;
                })
                .catch(() => update(delays.slice(1)));
        }, delays[0]);
    };
    if (root.hasAttribute('data-heat-map-updating')) update([5000, 10000, 15000]);

    if (!canvas || !window.L) return;
    const L = window.L;
    // Panel revision 2.1 / 4.3: pinch-zoom and tap work on phones (touchZoom, tap tolerance); the page still scrolls.
    const map = L.map(canvas, { zoomControl: true, scrollWheelZoom: true, touchZoom: true, tapTolerance: 20, zoomSnap: 0.25, minZoom: 11, maxZoom: 18 });
    // OSM base -> opaque mask outside Lian (so only Lian's own place names show) -> the 19 risk polygons.
    map.createPane('hmxBase').style.zIndex = 200;
    const maskPane = map.createPane('hmxOutside');
    maskPane.style.zIndex = 350;
    maskPane.style.pointerEvents = 'none';
    map.createPane('hmxPolygons').style.zIndex = 400;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { pane: 'hmxBase', attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    map.setView([14.02, 120.65], 12);
    // A click on the map outside every barangay returns to the municipal summary.
    map.on('click', () => pick(null));

    fetch(canvas.dataset.boundaryUrl, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error('Boundary request failed'))))
        .then((boundary) => {
            const seen = new Set();
            const geoLayer = L.geoJSON(boundary, {
                pane: 'hmxPolygons',
                filter: (feature) => {
                    const props = feature?.properties || {};
                    const name = canonicalName(props.brgy_name);
                    if (!name || seen.has(name) || (props.city_name && props.city_name !== 'Lian')) return false;
                    seen.add(name);
                    return true;
                },
                style: (feature) => styleFor(canonicalName(feature?.properties?.brgy_name)),
                onEachFeature: (feature, layer) => {
                    const name = canonicalName(feature?.properties?.brgy_name);
                    layers.set(name, layer);
                    layer.bindTooltip(() => tooltipFor(name), { sticky: true, direction: 'top', className: name === own ? 'your-barangay-tooltip' : '' });
                    layer.on({
                        mouseover: () => layer.setStyle(styleFor(name, true)),
                        mouseout: () => layer.setStyle(styleFor(name)),
                        click: (event) => { L.DomEvent.stopPropagation(event); pick(name); },
                    });
                },
            }).addTo(map);
            const holes = [];
            geoLayer.eachLayer((layer) => {
                const rings = layer.getLatLngs();
                (L.LineUtil.isFlat(rings[0]) ? [rings] : rings).forEach((polygon) => holes.push(polygon[0]));
            });
            L.polygon([[[15.5, 119], [15.5, 122.5], [12.5, 122.5], [12.5, 119]], ...holes], { pane: 'hmxOutside', interactive: false, stroke: false, fillColor: '#f4f7f5', fillOpacity: 1 }).addTo(map);
            const ownLayer = own ? layers.get(own) : null;
            if (ownLayer) {
                ownLayer.bringToFront();
                L.marker(ownLayer.getBounds().getCenter(), { icon: L.divIcon({ className: 'risk-home-pin', html: '<span></span>', iconSize: [16, 16], iconAnchor: [8, 8] }), interactive: false, keyboard: false }).addTo(map);
            }
            const bounds = geoLayer.getBounds();
            if (bounds.isValid()) map.fitBounds(bounds, { padding: [16, 16] });
            canvas.dataset.loaded = 'true';
            show();
        })
        .catch((error) => {
            console.error('iClimate could not load the Lian barangay boundaries.', error);
            canvas.dataset.loaded = 'failed';
        });
}

// The MAO dashboard's small Heat Map preview: the same 19 barangays in this week's colours, with no controls. The
// whole frame is a link to the Heat Map page, so the map itself takes no clicks, drags or zooms.
export function initHeatMapPreview() {
    const frame = document.querySelector('[data-heat-map-preview]');
    const canvas = frame?.querySelector('#heatMapPreviewCanvas');
    if (!canvas || canvas.dataset.ready || !window.L) return;
    canvas.dataset.ready = 'true';

    let data = { levels: {}, colors: {} };
    try {
        data = JSON.parse(frame.querySelector('[data-heat-map-preview-levels]')?.textContent || '{}');
    } catch (error) {
        console.error('iClimate could not read the heat map preview ratings.', error);
    }
    const L = window.L;
    const map = L.map(canvas, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false, zoomSnap: 0.25 });
    map.setView([14.02, 120.65], 11);

    fetch(canvas.dataset.boundaryUrl, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error('Boundary request failed'))))
        .then((boundary) => {
            const seen = new Set();
            const layer = L.geoJSON(boundary, {
                interactive: false,
                filter: (feature) => {
                    const props = feature?.properties || {};
                    const name = canonicalName(props.brgy_name);
                    if (!name || seen.has(name) || (props.city_name && props.city_name !== 'Lian')) return false;
                    seen.add(name);
                    return true;
                },
                style: (feature) => {
                    const level = data.levels?.[canonicalName(feature?.properties?.brgy_name)] || 'nodata';
                    return { color: '#26352f', weight: 1, opacity: 1, fillColor: data.colors?.[level] || '#8a939b', fillOpacity: 0.8, dashArray: level === 'nodata' ? '4 4' : undefined };
                },
            }).addTo(map);
            const bounds = layer.getBounds();
            // The frame can get its final size after the map is made; fit the 19 barangays again whenever it changes.
            const fit = () => { if (bounds.isValid() && canvas.clientWidth > 0) { map.invalidateSize({ pan: false }); map.fitBounds(bounds, { padding: [8, 8], animate: false }); } };
            fit();
            if ('ResizeObserver' in window) new ResizeObserver(fit).observe(canvas);
            canvas.dataset.loaded = 'true';
        })
        .catch((error) => {
            console.error('iClimate could not load the Lian barangay boundaries.', error);
            canvas.dataset.loaded = 'failed';
        });
}
