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
    const detailContainer = root.querySelector('[data-heat-map-detail-container]');
    const readLevels = (scope) => JSON.parse(scope.querySelector('[data-heat-map-levels]')?.textContent || '{}');
    let data = { levels: {}, colors: {}, labels: {}, yours: '' };
    try {
        data = readLevels(root);
    } catch (error) {
        console.error('iClimate could not read the heat map ratings.', error);
    }
    const own = canonicalName(canvas?.dataset.ownBarangay);
    const apiStatus = root.querySelector('[data-heat-map-api-status]');
    const layers = new Map();
    // Manuscript – Naicel (2026-10-08): a range may also be a map layer (flood / drought / yield) listed in data.layers.
    const validRange = (key) => (key === '2weeks' || (data.layers || []).includes(key) ? key : 'week');
    let range = validRange(root.dataset.range);
    let period = new URL(window.location.href).searchParams.get('range') === '2weeks' ? '2weeks' : 'week';
    const initialLayer = new URL(window.location.href).searchParams.get('layer');
    if (initialLayer && (data.layers || []).includes(initialLayer)) range = initialLayer;
    let selected = null;
    const detailCache = new Map();
    let detailController = null;
    let detailSequence = 0;

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
        if (detailContainer) detailContainer.hidden = !selected;
        // Farmers: the own-barangay card above the map follows [This Week] / [2 Weeks] (a map layer keeps this week's).
        root.querySelectorAll('[data-heat-map-mine-range]').forEach((card) => { card.hidden = card.dataset.heatMapMineRange !== (range === '2weeks' ? '2weeks' : 'week'); });
        root.dataset.range = range;
        root.querySelectorAll('[data-heat-map-range]').forEach((button) => {
            const active = button.dataset.heatMapRange === period;
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
    let apiController = null;
    let apiSequence = 0;
    const updatedText = (iso) => {
        const date = iso ? new Date(iso) : null;
        return date && !Number.isNaN(date.getTime())
            ? date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' })
            : '';
    };
    // Expected yield for [This Week] / [2 Weeks]: every barangay by its t/ha, highest first (no estimate last), its
    // class beside the number (they are often all Fair), your barangay against the season, and where it comes from.
    const yieldClass = { good: 'Good', fair: 'Fair', low: 'Low' };
    const tHa = (value) => `${Number(value).toFixed(2)} t/ha`;
    const renderYieldPanel = (view, payload) => {
        const rows = (Array.isArray(payload.barangays) ? payload.barangays.slice() : [])
            .sort((a, b) => (b.yield?.t_ha ?? -1) - (a.yield?.t_ha ?? -1) || String(a.name).localeCompare(String(b.name)));
        const title = view.querySelector('.hmx-title')?.textContent || 'Expected rice yield this season (t/ha)';
        const counts = payload.summary?.levels || {};
        const countLines = ['low', 'moderate', 'high', 'unavailable'].filter((level) => Number(counts[level]) > 0).map((level) => `<li class="is-${escapeHtml(level)}"><span class="hmx-dot" style="--hmx-color:${escapeHtml(data.colors?.[level] || '#9CA3AF')}"></span>${escapeHtml(String(counts[level]))}: ${escapeHtml(rows.find((row) => row.level === level)?.label || level)}</li>`).join('');
        const season = rows.find((row) => row.yield?.season)?.yield.season;
        const valueText = (row) => (row.yield?.t_ha != null ? `${tHa(row.yield.t_ha)} · ${yieldClass[row.yield.class] || row.label}` : (row.label || 'No estimate yet'));
        const list = rows.map((row) => `<li><button type="button" class="hmx-row" data-heat-map-pick="${escapeHtml(row.name)}"><span class="hmx-dot" style="--hmx-color:${escapeHtml(data.colors?.[row.level] || '#9CA3AF')}"></span><span class="hmx-row-name">${escapeHtml(row.name)}</span><span class="hmx-row-level">${escapeHtml(valueText(row))}</span></button></li>`).join('');
        const anyEstimate = rows.some((row) => row.yield?.t_ha != null);
        const updated = updatedText(payload.updated_at);
        const mine = own ? rows.find((row) => canonicalName(row.name) === own) : null;
        const change = mine?.yield?.vs_season;
        const changeText = change != null ? `, ${change > 0 ? '+' : change < 0 ? '\u2212' : '\u00b1'}${Math.abs(change).toFixed(2)} vs season` : '';
        const mineCard = mine ? `<button type="button" class="hmx-own" data-heat-map-pick="${escapeHtml(mine.name)}" data-heat-map-yield-mine><span class="hmx-own-label">${escapeHtml(data.yours || 'Your barangay')}</span><strong>${escapeHtml(mine.name)}</strong><span class="hmx-badge is-${escapeHtml(mine.level)}">${escapeHtml(mine.yield?.t_ha != null ? `${tHa(mine.yield.t_ha)}${changeText}` : (mine.label || 'No estimate yet'))}</span></button>` : '';
        view.innerHTML = `<h2 class="hmx-title">${escapeHtml(title)}</h2>${season ? `<p class="hmx-hint">${escapeHtml(season)}</p>` : ''}${anyEstimate ? '' : '<p class="hmx-text">No estimate yet</p>'}<div class="hmx-summary"><ul class="hmx-counts">${countLines}</ul>${mineCard}<p class="hmx-hint">${rows.length} barangays</p></div><ul class="hmx-list">${list}</ul><p class="hmx-source" data-heat-map-yield-source>${escapeHtml(payload.source || 'Based on trained model')}${updated ? ` · updated ${escapeHtml(updated)}` : ''}</p>`;
        views = [...root.querySelectorAll('[data-heat-map-view]')];
    };
    const renderApiPanel = (payload) => {
        const view = root.querySelector(`[data-heat-map-view="${range}:summary"]`);
        if (!view) return;
        if (range === 'yield') return renderYieldPanel(view, payload);
        const rows = Array.isArray(payload.barangays) ? payload.barangays.slice() : [];
        const order = { flood_prone: 0, severe: 0, high: 0, moderate: 1, low: 2, unavailable: 3 };
        rows.sort((a, b) => (order[a.level] ?? 9) - (order[b.level] ?? 9) || String(a.name).localeCompare(String(b.name)));
        const title = view.querySelector('.hmx-title')?.textContent || (range === 'flood' ? 'Flood-prone barangays' : 'Heat Map');
        const counts = payload.summary?.levels || {};
        const total = rows.length;
        const available = rows.filter((row) => row.level !== 'unavailable').length;
        const countLines = Object.entries(counts).filter(([, count]) => Number(count) > 0).map(([level, count]) => `<li class="is-${escapeHtml(level)}"><span class="hmx-dot" style="--hmx-color:${escapeHtml(data.colors?.[level] || (level === 'flood_prone' ? '#EF6C00' : '#9CA3AF'))}"></span>${escapeHtml(String(count))}: ${escapeHtml(rows.find((row) => row.level === level)?.label || level.replace('_', ' '))}</li>`).join('');
        const list = rows.map((row) => `<li><button type="button" class="hmx-row" data-heat-map-pick="${escapeHtml(row.name)}"><span class="hmx-dot" style="--hmx-color:${escapeHtml(data.colors?.[row.level] || (row.level === 'flood_prone' ? '#EF6C00' : '#9CA3AF'))}"></span><span class="hmx-row-name">${escapeHtml(row.name)}</span><span class="hmx-row-level">${escapeHtml(row.label || row.level)}</span></button></li>`).join('');
        const updated = payload.updated_at ? `<p class="hmx-source">Updated: ${escapeHtml(payload.updated_at)}</p>` : '';
        const source = payload.source ? `<p class="hmx-source">${escapeHtml(payload.source)}</p>` : '';
        const noData = available === 0 ? '<p class="hmx-text">Flood-prone barangays — No data yet</p>' : '';
        view.innerHTML = `<h2 class="hmx-title">${escapeHtml(title)}</h2>${noData}<div class="hmx-summary"><ul class="hmx-counts">${countLines}</ul><p class="hmx-hint">${total} barangays</p></div><ul class="hmx-list">${list}</ul>${updated}${source}`;
        views = [...root.querySelectorAll('[data-heat-map-view]')];
    };
    const fetchLayer = async (key, selectedPeriod = period) => {
        if (!canvas?.dataset.layerApiUrl) return;
        apiController?.abort();
        apiController = new AbortController();
        const sequence = ++apiSequence;
        if (apiStatus) { apiStatus.hidden = false; apiStatus.textContent = 'Loading map data…'; }
        try {
            const url = new URL(canvas.dataset.layerApiUrl, window.location.origin);
            url.searchParams.set('layer', key === 'week' || key === '2weeks' ? 'weather_risk' : key);
            url.searchParams.set('period', selectedPeriod);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: apiController.signal });
            if (!response.ok) throw new Error(`Map data request failed (${response.status})`);
            const payload = await response.json();
            if (sequence !== apiSequence) return; // a newer layer or period was chosen meanwhile
            if (!Array.isArray(payload.barangays)) throw new Error('Incomplete map data response');
            data.levels[key] = {};
            data.details[key] = {};
            data.labelsByRange[key] = data.labelsByRange[key] || {};
            payload.barangays.forEach((entry) => { const name = canonicalName(entry.name); if (name) { data.levels[key][name] = entry.level === 'unavailable' ? 'nodata' : entry.level; data.labelsByRange[key][entry.level] = entry.label || entry.level; data.details[key][name] = { factor: (entry.drivers || []).join(', '), impact: entry.details?.reason || '' }; } });
            renderApiPanel(payload);
            if (apiStatus) { apiStatus.hidden = true; apiStatus.textContent = ''; }
            show();
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('iClimate heat-map API request failed.', error);
            if (apiStatus) { apiStatus.hidden = false; apiStatus.textContent = 'Map data could not be loaded. Please try again.'; }
        }
    };
    // The Expected yield detail follows [This Week] / [2 Weeks] (?period=); the other ranges ignore it.
    const detailUrl = (key, name) => (canvas?.dataset.detailUrlTemplate || '')
        .replace('__RANGE__', encodeURIComponent(key))
        .replace('__BARANGAY__', encodeURIComponent(name)) + (key === 'yield' ? `?period=${encodeURIComponent(period)}` : '');
    const detailLoading = () => {
        if (!detailContainer) return;
        detailContainer.innerHTML = '<section class="hmx-view"><p class="hmx-text" role="status">Loading details…</p></section>';
        detailContainer.hidden = false;
    };
    const loadDetail = async (name) => {
        if (!detailContainer || !name) return;
        const version = data.versions?.[range] || '';
        const cacheKey = `${range}:${name}:${version}:${range === 'yield' ? period : ''}`;
        const cached = detailCache.get(cacheKey);
        if (cached) {
            detailContainer.innerHTML = cached;
            detailContainer.hidden = false;
            return;
        }

        detailController?.abort();
        const controller = new AbortController();
        detailController = controller;
        const sequence = ++detailSequence;
        detailLoading();
        try {
            const response = await fetch(detailUrl(range, name), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Heat Map detail answered ${response.status}`);
            const payload = await response.json();
            if (controller.signal.aborted || sequence !== detailSequence || selected !== name) return;
            if (payload.range !== range || payload.barangay !== name || typeof payload.html !== 'string') throw new Error('Incomplete Heat Map detail');
            detailCache.set(cacheKey, payload.html);
            detailContainer.innerHTML = payload.html;
            detailContainer.hidden = false;
        } catch (error) {
            if (!controller.signal.aborted && sequence === detailSequence && selected === name) {
                detailContainer.innerHTML = '<section class="hmx-view"><p class="hmx-text" role="status">Unable to load this barangay’s details.</p></section>';
            }
        } finally {
            if (detailController === controller) detailController = null;
        }
    };
    const pick = (name, fromPanel = false) => {
        selected = name && data.levels?.[range]?.[name] !== undefined ? name : null;
        detailController?.abort();
        detailSequence += 1;
        show();
        void fetchLayer(range, period);
        if (selected) void loadDetail(selected);
        // On a phone the panel sits under the map: bring the answer into view after a map click.
        if (selected && !fromPanel && window.matchMedia('(max-width: 991.98px)').matches) {
            root.querySelector('[data-heat-map-panel]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const setPeriod = (key) => {
        period = key === '2weeks' ? '2weeks' : 'week';
        if (!(data.layers || []).includes(range)) range = period;
        if (selected && data.levels?.[range]?.[selected] === undefined) selected = null;
        detailController?.abort();
        detailSequence += 1;
        const url = new URL(window.location.href);
        url.searchParams.set('range', period);
        if ((data.layers || []).includes(range)) url.searchParams.set('layer', range); else url.searchParams.delete('layer');
        window.history.replaceState(null, '', url);
        show();
        void fetchLayer(range, period);
        if (selected) void loadDetail(selected);
    };
    const setLayer = (key) => {
        range = (data.layers || []).includes(key) ? key : period;
        selected = null;
        const url = new URL(window.location.href);
        url.searchParams.set('range', period);
        if ((data.layers || []).includes(range)) url.searchParams.set('layer', range); else url.searchParams.delete('layer');
        window.history.replaceState(null, '', url);
        show();
        void fetchLayer(range, period);
    };
    // Manuscript – Naicel: choosing a map layer shows it; the empty choice goes back to this week's risk.
    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-heat-map-layer]')) setLayer(event.target.value || '');
    });

    root.addEventListener('click', (event) => {
        const rangeButton = event.target.closest('[data-heat-map-range]');
        if (rangeButton) {
            event.preventDefault();
            setPeriod(rangeButton.dataset.heatMapRange);
            return;
        }
        const pickButton = event.target.closest('[data-heat-map-pick]');
        if (pickButton) pick(canonicalName(pickButton.dataset.heatMapPick), true);
    });

    // New weather is on its way: read this page again after 5, 15 and 30 seconds (the shared refresh runs once on the
    // server however often it is read), and stop as soon as no barangay is missing its forecast. Keep the request and
    // timer cancellable so leaving the page or hiding the tab does not leave work running in the background.
    const hasRating = (levels) => Object.values(levels || {}).some((range) => Object.values(range || {}).some((level) => level !== 'nodata'));
    const missing = (levels) => Object.values(levels || {}).some((range) => Object.values(range || {}).includes('nodata'));
    let refreshTimer = null;
    let refreshController = null;
    let refreshGeneration = 0;
    let pendingRefreshDelays = [];
    const cancelRefresh = () => {
        if (refreshTimer) window.clearTimeout(refreshTimer);
        refreshTimer = null;
        refreshController?.abort();
        refreshController = null;
        refreshGeneration += 1;
    };
    const update = (delays) => {
        pendingRefreshDelays = delays;
        if (!delays.length || document.hidden || refreshTimer) return;
        const [delay, ...remaining] = delays;
        pendingRefreshDelays = remaining;
        const generation = refreshGeneration;
        refreshTimer = window.setTimeout(() => {
            refreshTimer = null;
            if (document.hidden || generation !== refreshGeneration) return;
            const controller = new AbortController();
            refreshController = controller;
            fetch(window.location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: controller.signal })
                .then((response) => (response.ok ? response.text() : Promise.reject(new Error(`Heat Map page answered ${response.status}`))))
                .then((html) => {
                    if (controller.signal.aborted || generation !== refreshGeneration) return;
                    const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-heat-map]');
                    const next = fresh ? readLevels(fresh) : null;
                    if (!next || !hasRating(next.levels)) return update(remaining);
                    data = next;
                    detailCache.clear();
                    ['[data-heat-map-panel]', '[data-heat-map-legend="risk"]', '[data-heat-map-mine]'].forEach((selector) => {   // Manuscript – Naicel: the risk legend (layer legends come first now)
                        const target = root.querySelector(selector);
                        const source = fresh.querySelector(selector);
                        if (target && source) target.innerHTML = source.innerHTML;
                    });
                    views = [...root.querySelectorAll('[data-heat-map-view]')];
                    if (selected && data.levels?.[range]?.[selected] === undefined) selected = null;
                    show();
                    if (missing(next.levels) && fresh.hasAttribute('data-heat-map-updating')) update(remaining);
                    return undefined;
                })
                .catch((error) => {
                    if (!controller.signal.aborted && generation === refreshGeneration) update(remaining);
                })
                .finally(() => {
                    if (refreshController === controller) refreshController = null;
                });
        }, delay);
    };
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cancelRefresh();
            return;
        }
        if (pendingRefreshDelays.length) update([0, ...pendingRefreshDelays]);
    });
    window.addEventListener('pagehide', cancelRefresh, { once: true });
    if (root.hasAttribute('data-heat-map-updating')) update([5000, 10000, 15000]);

    if (!canvas || !window.L) return;
    void fetchLayer(range, period);
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

    const boundaryController = new AbortController();
    window.addEventListener('pagehide', () => boundaryController.abort(), { once: true });
    fetch(canvas.dataset.boundaryUrl, { headers: { Accept: 'application/json' }, signal: boundaryController.signal })
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
                    layer.bindTooltip(() => tooltipFor(name), {
                        sticky: true,
                        // Let Leaflet flip the card when the hovered polygon is close to a map edge.
                        direction: 'auto',
                        offset: [0, -8],
                        className: `hmx-map-tooltip${name === own ? ' your-barangay-tooltip' : ''}`,
                    });
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
            if (boundaryController.signal.aborted) return;
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

    const boundaryController = new AbortController();
    window.addEventListener('pagehide', () => boundaryController.abort(), { once: true });
    fetch(canvas.dataset.boundaryUrl, { headers: { Accept: 'application/json' }, signal: boundaryController.signal })
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
            if (boundaryController.signal.aborted) return;
            console.error('iClimate could not load the Lian barangay boundaries.', error);
            canvas.dataset.loaded = 'failed';
        });
}
