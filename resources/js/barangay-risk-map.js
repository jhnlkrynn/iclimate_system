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

const TREND_TEXT = { increasing: '↑ Increasing risk', decreasing: '↓ Decreasing risk', stable: '→ Stable' };
const LIAN_VALUES_NOTE = 'These forecast values represent Lian municipality and are used to estimate the risk for this barangay.';

const rowsMarkup = (rows) => `<dl class="risk-forecast-facts">${rows.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl>`;

// One barangay in one forecast month, in four parts so the Lian-wide forecast is never mistaken for a
// barangay's own weather: the barangay's risk, the Lian forecast, what is known about the barangay, and
// what to do. Missing values are left out (or said to be missing), never shown as 0 or guessed.
function forecastSections(entry, month) {
    const level = riskLevel(entry);
    const weather = month?.weather || {};
    const factors = entry?.factors || {};
    const index = (entry?.timeline || []).findIndex((point) => point.month === month?.short_label);
    const previous = index > 0 ? entry.timeline[index - 1] : null;
    return [
        { rows: [
            ['Barangay Risk Assessment', level === 'No Data' ? 'Insufficient Forecast Data' : `${level} Risk`],
            ['Forecast Period', month?.label],
            ['Risk Trend', entry?.trend && previous ? `${TREND_TEXT[entry.trend]} (${previous.month}: ${previous.risk_level})` : null],
        ] },
        { title: 'Lian Climate Forecast', note: LIAN_VALUES_NOTE, rows: [
            ['Lian Forecast Rainfall', weather.rainfall_mm != null ? `About ${weather.rainfall_mm} mm for the month` : null],
            ['Lian Forecast Temperature', weather.temperature_c != null ? `${weather.temperature_c}°C` : null],
            ['Lian Forecast Humidity', weather.humidity_percent != null ? `${weather.humidity_percent}%` : null],
            ['Forecast Confidence', month?.confidence_level],
        ] },
        { title: 'Barangay Factors', rows: [
            ['Farm Type', factors.farm_type ? (factors.farm_type_from_records ? factors.farm_type : `${factors.farm_type} (assumed, no farm records yet)`) : null],
            ['Rice Yield Record', factors.yield_record ? `${factors.yield_record.value} t/ha (${factors.yield_record.year})` : 'No barangay record available'],
            ['Local Exposure Data', 'Limited (not yet validated)'],
        ] },
        { title: 'Possible Effect on Rice', lead: entry?.main_concern !== 'Insufficient Forecast Data' ? entry?.main_concern : null, text: entry?.impact },
        { title: 'Recommended Action', text: entry?.action },
    ];
}

const sectionsMarkup = (sections) => sections.map((section) => {
    const rows = (section.rows || []).filter(([, value]) => value != null && value !== '');
    if (!rows.length && !section.text && !section.lead) return '';
    return `<section class="risk-forecast-section">${section.title ? `<h4>${escapeHtml(section.title)}</h4>` : ''}${rows.length ? rowsMarkup(rows) : ''}${section.lead ? `<p class="risk-forecast-lead">${escapeHtml(section.lead)}</p>` : ''}${section.text ? `<p>${escapeHtml(section.text)}</p>` : ''}${section.note ? `<p class="risk-forecast-section-note">${escapeHtml(section.note)}</p>` : ''}</section>`;
}).join('');

function forecastPopupMarkup(name, entry, month) {
    return `<div class="risk-map-popup risk-forecast-popup"><strong>${escapeHtml(displayName(name))}</strong>${sectionsMarkup(forecastSections(entry, month))}</div>`;
}

function forecastDetailMarkup(name, entry, month) {
    const level = riskLevel(entry);
    const strip = (entry?.timeline || []).map((point) => `<span class="${point.month === month?.short_label ? 'is-active' : ''}" style="--risk-color:${RISK_COLORS[riskLevel(point)]}">${escapeHtml(point.month)} <b>${escapeHtml(point.risk_level)}</b></span>`).join('');
    return `<div class="risk-map-detail-content"><div><span class="risk-map-detail-kicker">Selected barangay · monthly forecast</span><h3>${escapeHtml(displayName(name))}</h3></div><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">BARANGAY RISK: ${escapeHtml(level === 'No Data' ? 'INSUFFICIENT DATA' : level.toUpperCase())}</span><div class="risk-forecast-sections">${sectionsMarkup(forecastSections(entry, month))}</div>${strip ? `<div class="risk-forecast-strip" aria-label="Barangay risk by forecast month">${strip}</div>` : ''}<button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
}

function overviewMarkup(month) {
    const summary = month.summary || {};
    const weather = month.weather || {};
    const rows = [
        ['Overall Climate Condition', summary.overall ? `${summary.overall} Agricultural Risk` : 'Insufficient Forecast Data'],
        ['Lian Forecast Rainfall', weather.rainfall_mm != null ? `About ${weather.rainfall_mm} mm` : null],
        ['Main Concern', summary.main_concern],
        ['Barangays Requiring Attention', `${summary.attention ?? 0} of ${summary.monitored ?? 19}`],
        ['Forecast Confidence', month.confidence_level],
        ['Trend', summary.trend && summary.previous_label ? `${TREND_TEXT[summary.trend]} (compared with ${summary.previous_label})` : null],
    ].filter(([, value]) => value != null && value !== '');
    return `<div class="risk-forecast-overview-head"><span>Lian Climate Risk Forecast</span><h3>${escapeHtml(month.label)}</h3></div>${rowsMarkup(rows)}<p class="risk-forecast-note">Risk differences between barangays are based on available local farming and exposure information, while weather forecast values apply to Lian as a whole.</p>`;
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
    // Forecast mode (Heat Map page only): activeMonth null shows current conditions, otherwise a 'YYYY-MM' forecast month.
    const forecastBox = card?.querySelector('[data-risk-forecast]');
    const spinner = shell?.querySelector('[data-risk-map-spinner]');
    const forecastEntries = new Map();
    let forecastData = null;
    let forecastRequest = null;
    let activeMonth = null;
    const activeEntries = () => (activeMonth ? forecastEntries : entries);
    const activeForecast = () => (activeMonth ? forecastData?.months?.find((month) => month.month === activeMonth) || null : null);
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
        const level = riskLevel(activeEntries().get(name));
        const own = name === highlightBarangay;
        return { pane: 'riskPolygons', color: selected ? '#ffffff' : own ? '#0d47a1' : '#26352f', weight: selected || own ? 4 : hovered ? 3 : 1.4, opacity: 1, fillColor: RISK_COLORS[level], fillOpacity: selected ? 0.68 : 0.48, dashArray: level === 'No Data' ? '4 4' : undefined };
    };
    const showDetails = (name) => {
        if (!details) return;
        details.innerHTML = activeMonth ? forecastDetailMarkup(name, forecastEntries.get(name), activeForecast()) : detailMarkup(name, entries.get(name));
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
        layers.forEach((_, name) => { counts[riskLevel(activeEntries().get(name))] += 1; });
        Object.entries(counts).forEach(([level, count]) => { const target = card?.querySelector(`[data-risk-count="${level}"]`); if (target) target.textContent = count; });
        updateForecastPanel(counts);
    };
    const tooltipMarkup = (name) => {
        const month = activeForecast();
        const level = riskLevel(activeEntries().get(name));
        const label = month && level === 'No Data' ? 'Insufficient Forecast Data' : level;
        return `<strong>${escapeHtml(displayName(name))}</strong>${name === highlightBarangay ? ' <span class="your-barangay-pill">Yours</span>' : ''}<br>${month ? 'Forecast risk' : 'Farm risk'}: ${escapeHtml(label)}${month ? `<br>${escapeHtml(month.label)}` : ''}`;
    };

    // Month controls live in the toolbar above the map, outside the forecast summary block.
    const monthSelect = forecastBox && card?.querySelector('[data-risk-forecast-month]');
    const timeline = forecastBox && card?.querySelector('[data-risk-forecast-timeline]');
    const emptyState = forecastBox && card?.querySelector('[data-risk-forecast-empty]');
    const overview = forecastBox?.querySelector('[data-risk-forecast-overview]');
    const forLabel = forecastBox && card?.querySelector('[data-risk-forecast-for]');
    const legendTitle = card?.querySelector('.risk-map-legend-title');
    const aboutConfidence = forecastBox && card?.querySelector('[data-forecast-about-confidence]');
    // Summary cards, "Forecast for" line, overview and legend title all follow the active month.
    function updateForecastPanel(counts) {
        if (!forecastBox) return;
        const stat = (key, value) => { const target = forecastBox.querySelector(`[data-forecast-stat="${key}"]`); if (target) target.textContent = value; };
        stat('monitored', layers.size);
        stat('low', counts.Low);
        stat('moderate', counts.Moderate);
        stat('attention', counts.High + counts.Severe);
        const month = activeForecast();
        if (forLabel) forLabel.innerHTML = month
            ? `<span class="risk-mode-badge is-forecast">Monthly Forecast</span> Forecast for: <strong>${escapeHtml(month.label)}</strong>`
            : '<span class="risk-mode-badge">Current Conditions</span> Today\'s risk (live weather)';
        if (aboutConfidence) aboutConfidence.textContent = month?.confidence_level ? `${month.confidence_level} (${month.label})` : 'Shown when a forecast month is selected';
        if (legendTitle) legendTitle.textContent = month ? 'Forecast risk' : 'Farm risk';
        if (overview) { overview.hidden = !month; overview.innerHTML = month ? overviewMarkup(month) : ''; }
        if (monthSelect) monthSelect.value = activeMonth || '';
        timeline?.querySelectorAll('[data-month]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.month === (activeMonth || ''))));
    }
    const buildMonthControls = (data) => {
        const months = data?.status === 'ok' ? data.months || [] : [];
        if (!months.length) {
            if (emptyState) emptyState.hidden = false;
            return;
        }
        monthSelect?.insertAdjacentHTML('beforeend', months.map((month) => `<option value="${escapeHtml(month.month)}">${escapeHtml(month.label)}</option>`).join(''));
        if (monthSelect) monthSelect.disabled = false;
        if (timeline) {
            timeline.innerHTML = [`<button type="button" data-month="" aria-pressed="true" aria-label="Current conditions">Now</button>`, ...months.map((month) => `<button type="button" data-month="${escapeHtml(month.month)}" aria-pressed="false" aria-label="${escapeHtml(month.label)}" title="${escapeHtml(month.label)}">${escapeHtml(month.short_label)}</button>`)].join('');
            timeline.hidden = false;
        }
    };
    const loadForecast = () => {
        if (!forecastBox) return Promise.resolve(null);
        // loader: false keeps the full-page iClimate overlay away; the map shows its own spinner.
        forecastRequest ??= fetch(forecastBox.dataset.forecastUrl, { headers: { Accept: 'application/json' }, loader: false })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`Forecast request failed (${response.status})`))))
            .then((data) => { forecastData = data; buildMonthControls(data); return data; })
            .catch((requestError) => {
                console.error('iClimate could not load the barangay risk forecast.', requestError);
                if (emptyState) emptyState.hidden = false;
                forecastRequest = null;
                return null;
            });
        return forecastRequest;
    };
    const setMonth = async (month) => {
        if (spinner) spinner.hidden = false;
        try {
            if (month) await loadForecast();
            const data = month ? forecastData?.months?.find((candidate) => candidate.month === month) : null;
            // A month without a forecast never gets colours: stay on (or return to) current conditions and say so.
            if (emptyState) emptyState.hidden = !month || Boolean(data);
            activeMonth = data ? month : null;
            forecastEntries.clear();
            (data?.barangays || []).forEach((entry) => { const canonical = canonicalName(entry.barangay); if (canonical) forecastEntries.set(canonical, entry); });
            map.closePopup();
            layers.forEach((layer, name) => layer.setStyle(styleFor(name, name === selectedName)));
            updateCounts();
            if (selectedName && details && !details.hidden) showDetails(selectedName);
        } finally {
            if (spinner) spinner.hidden = true;
        }
    };
    monthSelect?.addEventListener('change', () => setMonth(monthSelect.value));
    timeline?.addEventListener('click', (event) => { const button = event.target.closest('[data-month]'); if (button) setMonth(button.dataset.month); });
    loadForecast();
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
                    // Content is built when shown, so it always matches the selected month.
                    layer.bindTooltip(() => tooltipMarkup(name), { sticky: true, direction: 'top', className: own ? 'your-barangay-tooltip' : '' });
                    layer.bindPopup(() => (activeMonth ? forecastPopupMarkup(name, forecastEntries.get(name), activeForecast()) : popupMarkup(name, entries.get(name))), { closeButton: true, maxWidth: 260, maxHeight: 320 });
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
