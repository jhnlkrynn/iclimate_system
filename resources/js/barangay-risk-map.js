// Shared Lian, Batangas barangay choropleth risk map.
// Used by every dashboard role (Farmer, MAO, IT Expert/Admin) that renders
// an element with id="barangayRiskMap" — all three reuse this exact module
// so risk-map rendering logic exists in exactly one place, backed by the
// same /api/lian/risk-map + /api/boundaries/lian-barangays endpoints
// (BarangayRiskMapService / AgriculturalRiskScorer server-side).

const RISK_LEVEL_COLORS = {
    'Low': '#2e7d32',
    'Moderate': '#f9c74f',
    'High': '#e05a2b',
    'Severe': '#8b0000',
    'No Data': '#9e9e9e',
};

const ESC_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ESC_MAP[c]);

// Plain-language, one-line meaning for each risk level — shown directly
// under the risk badge so an elderly farmer doesn't have to interpret a
// score or color alone. Wording only; the levels themselves ('Low',
// 'Moderate', 'High', 'Severe', 'No Data') still come straight from
// AgriculturalRiskScorer/BarangayRiskMapService, unmodified.
const RISK_EXPLANATIONS = {
    'Low': 'Safe conditions for normal farm activity.',
    'Moderate': 'Be careful and monitor your farm.',
    'High': 'Take action to protect crops.',
    'Severe': 'Immediate attention needed.',
    'No Data': 'No current weather data available.',
};

// The risk-map API's weather block already carries `icon` (an
// /images/weather/*.svg path) and `condition` (a short label, e.g. "Light
// Rain", "Thunderstorm") — computed server-side by the existing, already
// used-elsewhere App\Services\Weather\WeatherIconMapper from Open-Meteo's
// own weather_code, in BarangayRiskMapService. simplifyConditionLabel() maps
// that (sometimes fairly technical, e.g. "Mainly Clear", "Rain Showers")
// text onto the small, plain-language set an elderly farmer can recognize at
// a glance. This is a presentation-only relabeling — it never changes which
// icon is shown (still WeatherIconMapper's own choice) and never touches
// risk scoring.
function simplifyConditionLabel(condition) {
    const value = String(condition || '');

    if (/thunderstorm/i.test(value)) return 'Thunderstorm';
    if (/partly cloudy/i.test(value)) return 'Partly Cloudy';
    if (/overcast/i.test(value)) return 'Cloudy';
    if (/clear night/i.test(value)) return 'Clear';
    if (/clear/i.test(value)) return 'Sunny';
    if (/foggy/i.test(value)) return 'Foggy';
    if (/rain|drizzle|showers/i.test(value)) return 'Rainy';

    return 'No Weather Data';
}

// "September 22, 2026 • 10:59 AM" — friendlier than the previous raw
// toLocaleString() timestamp for elderly readers.
function formatFriendlyDateTime(isoString) {
    if (!isoString) return null;
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return null;

    const datePart = date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    const timePart = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

    return `${datePart} • ${timePart}`;
}

function weatherIconMarkup(weather, size = 30) {
    if (!weather || weather.status !== 'fresh' || !weather.icon) {
        return '<span class="weather-icon-fallback">No Weather Data</span>';
    }

    return `<img class="weather-icon" src="${esc(weather.icon)}" alt="${esc(weather.condition || 'Weather icon')}" width="${size}" height="${size}" loading="lazy">`;
}

// Cheap lat/lng-space collision avoidance for the small per-barangay weather
// markers: if a new centroid lands too close to an already-placed one (e.g.
// the cluster of Poblacion barangays near the town center), nudge it a small
// fixed distance outward along a rotating angle until it clears, instead of
// letting icons stack directly on top of each other. Degree-based (not
// pixel-based) so it doesn't need recalculating on zoom/pan.
function findClearWeatherMarkerPosition(centroid, placedCentroids) {
    // Bumped alongside the larger, more-visible marker badge size (see
    // weatherMarkerIcon()) so bigger icons still get enough breathing room
    // in dense clusters like the Poblacion barangays.
    const MIN_SEPARATION_DEG = 0.005;
    const OFFSET_DEG = 0.003;
    let lat = centroid.lat;
    let lng = centroid.lng;
    let angle = 0;
    let attempts = 0;

    const tooClose = () => placedCentroids.some((p) => Math.hypot(lat - p.lat, lng - p.lng) < MIN_SEPARATION_DEG);

    while (tooClose() && attempts < 8) {
        angle += Math.PI / 4;
        lat = centroid.lat + Math.sin(angle) * OFFSET_DEG;
        lng = centroid.lng + Math.cos(angle) * OFFSET_DEG;
        attempts += 1;
    }

    return window.L.latLng(lat, lng);
}

// Small, non-interactive divIcon marker for a barangay's current weather —
// reuses the exact same server-computed weather.icon (/images/weather/*.svg,
// via the existing WeatherIconMapper) as the detail panel/tooltip. Returns
// null when there's no fresh weather to show, so a barangay is simply left
// without a marker rather than showing a misleading/default icon.
function weatherMarkerIcon(weather) {
    if (!weather || weather.status !== 'fresh' || !weather.icon) return null;

    return window.L.divIcon({
        className: 'weather-marker-icon',
        html: `<span class="weather-marker-badge" title="${esc(simplifyConditionLabel(weather.condition))}"><img src="${esc(weather.icon)}" alt="" width="22" height="22"></span>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
    });
}

function riskFillStyle(level, isHighlighted = false) {
    const color = RISK_LEVEL_COLORS[level] || RISK_LEVEL_COLORS['No Data'];
    return {
        fillColor: color,
        color: isHighlighted ? '#0d47a1' : (level === 'No Data' ? '#616161' : '#2b2b2b'),
        weight: isHighlighted ? 4 : (level === 'No Data' ? 1 : 1.2),
        dashArray: level === 'No Data' ? '3,3' : null,
        fillOpacity: level === 'No Data' ? 0.35 : 0.65,
    };
}

// Renders the elderly-friendly, card-based barangay detail panel. Same
// function/markup for every role (Farmer, MAO, IT Expert/Admin) — there is
// only ever one shared #riskMapDetailPanel per page. Layout: A) barangay +
// large risk badge + plain-language meaning, B) weather condition icon +
// current readings, C) short farming advice bullets, D) last updated, and a
// collapsed-by-default "More Information" section for the technical detail
// (risk score %, data source, monthly rainfall figure, farm type, computed
// timestamp) that used to be shown inline. No risk/weather value shown here
// is computed client-side — everything is read straight off `entry`/`meta`
// exactly as BarangayRiskMapService already returns it.
function renderRiskDetailPanel(entry, meta) {
    const panel = document.getElementById('riskMapDetailPanel');
    const content = document.getElementById('riskMapDetailContent');
    if (!panel || !content) return;

    const riskLevel = entry.risk_level || 'No Data';
    const riskLevelClass = riskLevel.toLowerCase().replace(/\s+/g, '-');
    const isNoData = riskLevel === 'No Data';
    const explanation = RISK_EXPLANATIONS[riskLevel] || RISK_EXPLANATIONS['No Data'];
    const w = entry.weather || {};
    const hasFreshWeather = w.status === 'fresh';
    const updatedFriendly = formatFriendlyDateTime(entry.updated_at);

    const sections = [];

    // A. Barangay & Risk — large, bold, color + text + icon together (never
    // color alone), so the risk is readable at a glance.
    sections.push(`
        <div class="risk-card risk-card-head" data-level="${esc(riskLevel)}">
            <h3 class="risk-card-barangay">${esc(entry.barangay)}${entry.is_farmer_barangay ? ' <span class="your-barangay-pill">Your Barangay</span>' : ''}</h3>
            <div class="risk-level-badge risk-level-badge-${esc(riskLevelClass)}">
                <span class="risk-level-badge-text">${isNoData ? 'NO DATA' : esc(riskLevel.toUpperCase())}</span>
            </div>
            <p class="risk-card-explain">${esc(explanation)}</p>
        </div>
    `);

    // B. Current Weather — icon + plain condition name up top, then a
    // compact 2-column grid of only the readings a farmer actually needs.
    if (hasFreshWeather) {
        const tiles = [];
        if (w.rainfall_24h_mm !== null && w.rainfall_24h_mm !== undefined) tiles.push(['Rain', `${esc(w.rainfall_24h_mm)} mm`]);
        if (w.temperature_c !== null && w.temperature_c !== undefined) tiles.push(['Temperature', `${esc(w.temperature_c)}&deg;C`]);
        if (w.wind_speed_kmh !== null && w.wind_speed_kmh !== undefined) tiles.push(['Wind', `${esc(w.wind_speed_kmh)} km/h`]);
        if (w.humidity_percent !== null && w.humidity_percent !== undefined) tiles.push(['Humidity', `${esc(w.humidity_percent)}%`]);

        sections.push(`
            <div class="risk-card">
                <h4 class="risk-card-heading">Current Weather</h4>
                <div class="risk-card-condition">${weatherIconMarkup(w, 40)} <span class="risk-card-condition-name">${esc(simplifyConditionLabel(w.condition))}</span></div>
                ${tiles.length ? `<div class="risk-weather-grid">${tiles.map(([label, value]) => `<div class="risk-weather-tile"><span class="risk-weather-label">${label}</span><span class="risk-weather-value">${value}</span></div>`).join('')}</div>` : ''}
            </div>
        `);
    } else {
        sections.push(`
            <div class="risk-card">
                <h4 class="risk-card-heading">Current Weather</h4>
                <div class="risk-card-condition"><span class="weather-icon-fallback">No Weather Data</span></div>
                <p class="risk-card-body-text">${esc(w.message || 'Weather data is currently unavailable for this barangay.')}</p>
            </div>
        `);
    }

    // C. Farming Advice — short bullets only, plain wording kept exactly as
    // DecisionSupportService/AgriculturalRiskScorer already produced it.
    if (entry.advisory) {
        const advice = [];
        if (entry.advisory.planting) advice.push(entry.advisory.planting);
        if (entry.advisory.irrigation) advice.push(entry.advisory.irrigation);
        if (Array.isArray(entry.advisory.warnings)) advice.push(...entry.advisory.warnings);
        if (entry.advisory.overall_recommendation) advice.push(entry.advisory.overall_recommendation);

        if (advice.length) {
            sections.push(`
                <div class="risk-card">
                    <h4 class="risk-card-heading">Farming Advice</h4>
                    <ul class="risk-advice-list">${advice.map((line) => `<li>${esc(line)}</li>`).join('')}</ul>
                </div>
            `);
        }
    }

    // D. Last Updated — plain date/time, no raw ISO timestamp.
    sections.push(`
        <div class="risk-card risk-card-updated">
            <h4 class="risk-card-heading">Last Updated</h4>
            <p class="risk-card-body-text">${updatedFriendly ? esc(updatedFriendly) : 'Not yet available'}</p>
        </div>
    `);

    // E. More Information — collapsed by default; everything technical
    // (risk score %, data source, monthly rainfall figure, farm type,
    // recorded yield, no-data reason) lives here instead of the main view.
    const moreInfo = [];
    moreInfo.push(`<p class="risk-more-line">Farm Risk: ${esc(entry.risk_level || 'No Data')}</p>`);
    if (entry.monthly_rainfall && entry.monthly_rainfall.available !== false && entry.monthly_rainfall.value_mm !== null && entry.monthly_rainfall.value_mm !== undefined) {
        moreInfo.push(`<p class="risk-more-line">Monthly Rainfall: ${esc(entry.monthly_rainfall.value_mm)} ${esc(entry.monthly_rainfall.unit || 'mm')} (${esc(entry.monthly_rainfall.scope || 'municipal')})</p>`);
    }
    if (entry.farm_type_context) {
        moreInfo.push(`<p class="risk-more-line">Farm Type: ${esc(entry.farm_type_context)}</p>`);
    }
    if (entry.last_recorded_yield) {
        moreInfo.push(`<p class="risk-more-line">Last Recorded Yield: ${esc(entry.last_recorded_yield.value)} t/ha (${esc(entry.last_recorded_yield.season || '')} ${esc(entry.last_recorded_yield.year || '')})</p>`);
    }
    if (meta && meta.source_note) {
        moreInfo.push(`<p class="risk-more-line">Data Source: ${esc(meta.source_note)}${meta.generated_at ? ' &middot; as of ' + esc(formatFriendlyDateTime(meta.generated_at) || '') : ''}</p>`);
    }
    if (isNoData && entry.no_data_reason) {
        moreInfo.push(`<p class="risk-more-line">${esc(entry.no_data_reason)}</p>`);
    }

    if (moreInfo.length) {
        sections.push(`
            <details class="risk-card risk-more-info">
                <summary>More Information</summary>
                <div class="risk-more-info-body">${moreInfo.join('')}</div>
            </details>
        `);
    }

    content.innerHTML = sections.join('');
    panel.style.display = '';

    // Restart the slide-in animation for every newly selected barangay.
    panel.style.animation = 'none';
    void panel.offsetWidth;
    panel.style.animation = '';

    window.iClimateUi?.init?.(content);

    // On phones the panel sits below a tall map; bring it into view.
    if (window.matchMedia('(max-width: 991.98px)').matches) {
        panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
}

// Rich hover tooltip: name, colored risk chip, and the key live readings.
function tooltipMarkup(name, entry, isHighlighted) {
    const level = entry ? entry.risk_level || 'No Data' : 'No Data';
    const color = RISK_LEVEL_COLORS[level] || RISK_LEVEL_COLORS['No Data'];
    const w = entry && entry.weather && entry.weather.status === 'fresh' ? entry.weather : null;
    const icon = w && w.icon ? `<img class="weather-icon weather-icon-tooltip" src="${esc(w.icon)}" alt="" width="22" height="22">` : '';
    const stats = [];
    if (w && w.temperature_c !== null && w.temperature_c !== undefined) stats.push(`<span><b>${esc(w.temperature_c)}&deg;C</b></span>`);
    if (w && w.rainfall_24h_mm !== null && w.rainfall_24h_mm !== undefined) stats.push(`<span>Rain <b>${esc(w.rainfall_24h_mm)} mm</b></span>`);
    if (w && w.humidity_percent !== null && w.humidity_percent !== undefined) stats.push(`<span>Hum. <b>${esc(w.humidity_percent)}%</b></span>`);

    return `
        <div class="rmt-name">${icon}${esc(name)}${isHighlighted ? ' <span class="your-barangay-pill">Yours</span>' : ''}</div>
        <span class="rmt-level" style="background:${color};${level === 'Moderate' ? 'color:#3a2a00;' : ''}">Farm Risk: ${esc(level)}</span>
        ${stats.length ? `<div class="rmt-stats">${stats.join('')}</div>` : ''}
        <div class="rmt-hint">${w ? `${esc(simplifyConditionLabel(w.condition))} &middot; ` : ''}Click for details</div>
    `;
}


const TILE_LAYERS = {
    street: {
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        options: { attribution: '&copy; OpenStreetMap contributors', maxZoom: 18 },
    },
    satellite: {
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        options: { attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics', maxZoom: 18 },
    },
};

export function initBarangayRiskMap() {
    const mapEl = document.getElementById('barangayRiskMap');
    if (!mapEl || !window.L) return;

    const L = window.L;
    const riskUrl = mapEl.dataset.riskMapUrl;
    const boundaryUrl = mapEl.dataset.boundaryUrl;
    // Canonicalized (server-side, via LianBarangays::canonicalName()) name of
    // the viewing farmer's own barangay, if any — used only to visually
    // highlight one polygon; every role still sees and can click all 19.
    const highlightBarangay = mapEl.dataset.highlightBarangay || null;

    const shell = mapEl.closest('[data-risk-map-shell]');
    const statusEl = shell?.querySelector('[data-risk-map-status]');
    const veil = shell?.querySelector('[data-risk-map-veil]');
    const veilText = shell?.querySelector('[data-risk-map-veil-text]');
    const scrollHint = shell?.querySelector('[data-risk-map-scroll-hint]');
    const legend = document.getElementById('riskMapLegend');
    const searchSelect = document.querySelector('[data-risk-map-search]');
    const refreshButton = document.querySelector('[data-risk-map-refresh]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const setStatus = (text, state = '') => {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('is-loading', state === 'loading');
        statusEl.classList.toggle('is-error', state === 'error');
    };

    // Fallback center/zoom, used only until the real barangay GeoJSON loads
    // (Leaflet requires some center/zoom to construct the map), and as a last
    // resort if that fetch fails entirely.
    const FALLBACK_CENTER = [14.02, 120.66];
    const FALLBACK_ZOOM = 12;
    const map = L.map(mapEl, { scrollWheelZoom: false, maxBoundsViscosity: 1.0, zoomSnap: 0.25 }).setView(FALLBACK_CENTER, FALLBACK_ZOOM);

    let baseMode = 'street';
    let baseLayer = L.tileLayer(TILE_LAYERS.street.url, TILE_LAYERS.street.options).addTo(map);

    let geoLayer = null;
    let weatherMarkerLayer = null;
    let homePinLayer = null;
    let showWeatherMarkers = true;
    let activeLevel = null;
    let selectedName = null;
    let byName = {};
    let riskMeta = {};
    let hasLoadedOnce = false;
    const layersByName = {};

    // Fits the map to, and locks panning/zoom-out to, the bounds of the
    // already-rendered 19-barangay choropleth layer. Lian is taller than the
    // map is wide, so locking maxBounds/minZoom to the fitted view keeps
    // neighboring municipalities and open sea out of frame.
    const fitAndLockToLianBounds = (animate = false) => {
        if (!geoLayer) return false;
        const bounds = geoLayer.getBounds();
        if (!bounds.isValid()) return false;
        try {
            map.setMinZoom(0);
            map.fitBounds(bounds, { padding: [8, 8], animate });
            map.setMaxBounds(bounds.pad(0.08));
            map.setMinZoom(map.getZoom());
        } catch (e) { /* ignore */ }
        return true;
    };

    // Purely a visual outline overlay (drawn on top of the choropleth) — not
    // used for bounds/zoom calculations.
    const loadMunicipalOutline = async () => {
        try {
            const res = await fetch('/geojson/lian-boundary.geojson');
            if (!res.ok) throw new Error('municipal boundary fetch failed: ' + res.status);
            const municipalGeoJSON = await res.json();
            L.geoJSON(municipalGeoJSON, {
                style: { color: '#1b5e20', weight: 2.5, fill: false },
                interactive: false,
            }).addTo(map);
        } catch (error) {
            console.error('iClimate could not load the municipal outline overlay.', error);
        }
    };

    // Style for one polygon given the current filter / selection state.
    const styleFor = (name) => {
        const entry = byName[name];
        const level = entry ? entry.risk_level : 'No Data';
        const isHighlighted = highlightBarangay !== null && name === highlightBarangay;
        const style = riskFillStyle(level, isHighlighted);

        if (activeLevel && level !== activeLevel) {
            style.fillOpacity = 0.12;
            style.opacity = 0.35;
        } else {
            style.opacity = 1;
        }
        if (name === selectedName) {
            style.weight = 4;
            style.color = '#ffffff';
            style.fillOpacity = Math.max(style.fillOpacity, 0.8);
            style.dashArray = null;
        }
        return style;
    };

    const restyleAll = () => {
        Object.entries(layersByName).forEach(([name, layer]) => layer.setStyle(styleFor(name)));
        if (selectedName && layersByName[selectedName]) layersByName[selectedName].bringToFront();
    };

    const detailEntryFor = (name) => {
        const entry = byName[name];
        const isHighlighted = highlightBarangay !== null && name === highlightBarangay;
        // Even a barangay with no matching entry (name mismatch or no
        // computed score) gets a clear "No Data" panel instead of the click
        // silently doing nothing.
        return Object.assign({}, entry || {
            barangay: name || 'Unknown barangay',
            risk_level: 'No Data',
            risk_score: null,
            updated_at: null,
            weather: { status: 'unavailable', message: 'No live weather data available for this barangay.' },
            last_recorded_yield: null,
            advisory: null,
            no_data_reason: 'This barangay has no matching risk entry in the current dataset.',
        }, { is_farmer_barangay: isHighlighted });
    };

    const selectBarangay = (name, { fly = false } = {}) => {
        const layer = layersByName[name];
        if (!layer) return;
        selectedName = name;
        restyleAll();
        renderRiskDetailPanel(detailEntryFor(name), riskMeta);
        if (searchSelect && searchSelect.value !== name) searchSelect.value = name;
        if (fly) {
            try {
                map.flyToBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 14, duration: reducedMotion.matches ? 0 : 0.8 });
            } catch (e) { /* ignore */ }
        }
    };

    const clearSelection = () => {
        selectedName = null;
        restyleAll();
        const panel = document.getElementById('riskMapDetailPanel');
        if (panel) panel.style.display = 'none';
        if (searchSelect) searchSelect.value = '';
    };

    // Legend doubles as a filter: click a level to spotlight it, click again to clear.
    const setActiveLevel = (level) => {
        activeLevel = level;
        legend?.classList.toggle('is-filtering', Boolean(level));
        legend?.querySelectorAll('button[data-risk-level]').forEach((btn) => {
            btn.setAttribute('aria-pressed', String(btn.dataset.riskLevel === level));
        });
        restyleAll();
    };

    const updateLegendCounts = () => {
        const counts = { Low: 0, Moderate: 0, High: 0, Severe: 0, 'No Data': 0 };
        Object.keys(layersByName).forEach((name) => {
            const level = byName[name] ? byName[name].risk_level : 'No Data';
            counts[level in counts ? level : 'No Data'] += 1;
        });
        document.querySelectorAll('[data-risk-count]').forEach((el) => {
            const value = String(counts[el.dataset.riskCount] ?? 0);
            if (window.iClimateMotion?.animateValueChange) {
                window.iClimateMotion.animateValueChange(el, value);
            } else {
                el.textContent = value;
            }
        });
    };

    const populateSearch = () => {
        if (!searchSelect) return;
        const names = Object.keys(layersByName).sort((a, b) => a.localeCompare(b));
        searchSelect.innerHTML = '<option value="">Find a barangay…</option>' + names.map((name) => {
            const level = byName[name] ? byName[name].risk_level : 'No Data';
            const mine = highlightBarangay !== null && name === highlightBarangay ? ' (yours)' : '';
            return `<option value="${esc(name)}">${esc(name)}${mine} — ${esc(level)}</option>`;
        }).join('');
        searchSelect.disabled = names.length === 0;
        if (selectedName) searchSelect.value = selectedName;
    };

    // Small per-barangay weather condition markers, rebuilt with the
    // choropleth on every load so they never go stale relative to it.
    const drawWeatherMarkers = () => {
        if (weatherMarkerLayer) weatherMarkerLayer.remove();
        weatherMarkerLayer = L.layerGroup();
        const placedCentroids = [];
        geoLayer.eachLayer((layer) => {
            const feature = layer.feature;
            const name = feature && feature.properties && feature.properties.brgy_name;
            const entry = name ? byName[name] : null;
            const icon = weatherMarkerIcon(entry ? entry.weather : null);
            if (!icon) return;

            let centroid;
            try { centroid = layer.getBounds().getCenter(); } catch (e) { return; }

            const position = findClearWeatherMarkerPosition(centroid, placedCentroids);
            placedCentroids.push(position);

            // interactive: false so the marker never intercepts clicks —
            // they pass straight through to the barangay polygon beneath.
            L.marker(position, { icon, interactive: false, keyboard: false }).addTo(weatherMarkerLayer);
        });
        if (showWeatherMarkers) weatherMarkerLayer.addTo(map);
    };

    // Pulsing pin on the viewing farmer's own barangay.
    const drawHomePin = () => {
        if (homePinLayer) homePinLayer.remove();
        homePinLayer = null;
        if (!highlightBarangay || !layersByName[highlightBarangay]) return;
        let center;
        try { center = layersByName[highlightBarangay].getBounds().getCenter(); } catch (e) { return; }
        homePinLayer = L.marker(center, {
            icon: L.divIcon({ className: 'risk-home-pin', html: '<span></span>', iconSize: [16, 16], iconAnchor: [8, 8] }),
            interactive: false,
            keyboard: false,
            zIndexOffset: 1000,
        }).addTo(map);
    };

    let load = null;

    const showVeil = (text, withRetry = false) => {
        if (!veil) return;
        veil.hidden = false;
        if (veilText) veilText.textContent = text;
        const card = veil.querySelector('.risk-map-veil-card');
        card?.querySelector('[data-risk-map-retry]')?.remove();
        card?.querySelector('.spinner-border')?.toggleAttribute('hidden', withRetry);
        if (withRetry && card) {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.dataset.riskMapRetry = '';
            retry.textContent = 'Try again';
            retry.addEventListener('click', () => load(true));
            card.appendChild(retry);
        }
    };

    // Single fetch of the boundary + risk endpoints per load. `forceRefresh`
    // appends ?refresh=1 to the *same* request instead of issuing a separate
    // priming fetch first, so a manual refresh never doubles the call to
    // /api/lian/risk-map. This is the one and only place any role's
    // dashboard requests risk-map data.
    load = async (forceRefresh = false) => {
        setStatus(forceRefresh ? 'Refreshing live data…' : 'Loading risk map…', 'loading');
        refreshButton?.classList.add('is-busy');
        if (refreshButton) refreshButton.disabled = true;
        if (!hasLoadedOnce) showVeil('Loading the 19 barangays…');

        try {
            const effectiveRiskUrl = forceRefresh
                ? riskUrl + (riskUrl.includes('?') ? '&' : '?') + 'refresh=1'
                : riskUrl;
            const [boundaryRes, riskRes] = await Promise.all([
                fetch(boundaryUrl, { headers: { Accept: 'application/json' } }),
                fetch(effectiveRiskUrl, { headers: { Accept: 'application/json' } }),
            ]);
            const boundary = await boundaryRes.json();
            const risk = await riskRes.json();

            if (!boundary || !Array.isArray(boundary.features) || risk.status === 'unavailable') {
                setStatus('Risk map temporarily unavailable', 'error');
                if (!hasLoadedOnce) showVeil('The risk map is temporarily unavailable.', true);
                return;
            }

            riskMeta = { source_note: risk.source_note, generated_at: risk.generated_at };

            byName = {};
            (risk.barangays || []).forEach((entry) => { byName[entry.barangay] = entry; });

            if (geoLayer) geoLayer.remove();
            Object.keys(layersByName).forEach((key) => delete layersByName[key]);

            geoLayer = L.geoJSON(boundary, {
                style: (feature) => styleFor(feature.properties && feature.properties.brgy_name),
                onEachFeature: (feature, layer) => {
                    const name = feature.properties && feature.properties.brgy_name;
                    const entry = byName[name];
                    const isHighlighted = highlightBarangay !== null && name === highlightBarangay;
                    if (name) layersByName[name] = layer;

                    layer.bindTooltip(tooltipMarkup(name, entry, isHighlighted), {
                        sticky: true,
                        direction: 'top',
                        offset: [0, -12],
                        className: `risk-map-tooltip${isHighlighted ? ' your-barangay-tooltip' : ''}`,
                    });
                    layer.on('mouseover', () => {
                        const hover = styleFor(name);
                        layer.setStyle({ weight: Math.max(hover.weight, 3), fillOpacity: Math.min(0.9, hover.fillOpacity + 0.15) });
                        layer.bringToFront();
                    });
                    layer.on('mouseout', () => {
                        layer.setStyle(styleFor(name));
                        if (selectedName && layersByName[selectedName]) layersByName[selectedName].bringToFront();
                    });
                    layer.on('click', () => selectBarangay(name));
                },
            }).addTo(map);

            drawWeatherMarkers();
            drawHomePin();
            updateLegendCounts();
            populateSearch();

            // Only fit on the first load, so a manual refresh keeps the
            // person's current pan/zoom.
            if (!hasLoadedOnce) fitAndLockToLianBounds();
            hasLoadedOnce = true;

            if (selectedName && layersByName[selectedName]) {
                renderRiskDetailPanel(detailEntryFor(selectedName), riskMeta);
            }

            const asOf = formatFriendlyDateTime(risk.generated_at);
            setStatus(asOf ? `Updated ${asOf}` : 'Live risk data');
            if (veil) veil.hidden = true;
        } catch (error) {
            console.error('iClimate could not load the barangay risk map.', error);
            setStatus('Could not load the risk map', 'error');
            if (!hasLoadedOnce) showVeil('We could not load the risk map. Check your connection.', true);
        } finally {
            refreshButton?.classList.remove('is-busy');
            if (refreshButton) refreshButton.disabled = false;
        }
    };

    // ---------------------------------------------------------------- tools
    shell?.querySelectorAll('[data-risk-map-tool]').forEach((button) => {
        button.addEventListener('click', () => {
            const tool = button.dataset.riskMapTool;

            if (tool === 'weather') {
                showWeatherMarkers = !showWeatherMarkers;
                button.setAttribute('aria-pressed', String(showWeatherMarkers));
                if (weatherMarkerLayer) {
                    if (showWeatherMarkers) weatherMarkerLayer.addTo(map);
                    else weatherMarkerLayer.remove();
                }
            } else if (tool === 'satellite') {
                baseMode = baseMode === 'street' ? 'satellite' : 'street';
                button.setAttribute('aria-pressed', String(baseMode === 'satellite'));
                baseLayer.remove();
                baseLayer = L.tileLayer(TILE_LAYERS[baseMode].url, TILE_LAYERS[baseMode].options).addTo(map);
                baseLayer.bringToBack();
            } else if (tool === 'reset') {
                clearSelection();
                setActiveLevel(null);
                fitAndLockToLianBounds(!reducedMotion.matches);
            } else if (tool === 'fullscreen' && shell) {
                if (document.fullscreenElement) {
                    document.exitFullscreen?.();
                } else {
                    shell.requestFullscreen?.().catch(() => {});
                }
            }
        });
    });

    // Browsers without the Fullscreen API (older iOS Safari) get no button.
    if (!document.fullscreenEnabled) {
        shell?.querySelector('[data-risk-map-tool="fullscreen"]')?.remove();
    }

    document.addEventListener('fullscreenchange', () => {
        const isFull = document.fullscreenElement === shell;
        shell?.querySelector('[data-risk-map-tool="fullscreen"]')?.setAttribute('aria-pressed', String(isFull));
        if (isFull) map.scrollWheelZoom.enable();
        else map.scrollWheelZoom.disable();
        setTimeout(() => {
            map.invalidateSize();
            fitAndLockToLianBounds();
        }, 120);
    });

    legend?.querySelectorAll('button[data-risk-level]').forEach((button) => {
        button.addEventListener('click', () => {
            const level = button.dataset.riskLevel;
            setActiveLevel(activeLevel === level ? null : level);
        });
    });

    searchSelect?.addEventListener('change', () => {
        if (searchSelect.value) selectBarangay(searchSelect.value, { fly: true });
        else clearSelection();
    });

    document.querySelector('[data-risk-map-detail-close]')?.addEventListener('click', clearSelection);

    // Scroll-wheel zoom only after the person clicks into the map, so page
    // scrolling is never hijacked; a short hint explains how.
    let hintTimer = null;
    map.on('click', () => map.scrollWheelZoom.enable());
    mapEl.addEventListener('mouseleave', () => {
        if (document.fullscreenElement !== shell) map.scrollWheelZoom.disable();
    });
    mapEl.addEventListener('wheel', () => {
        if (map.scrollWheelZoom.enabled() || !scrollHint) return;
        scrollHint.classList.add('show');
        clearTimeout(hintTimer);
        hintTimer = setTimeout(() => scrollHint.classList.remove('show'), 1400);
    }, { passive: true });

    loadMunicipalOutline();
    load();

    refreshButton?.addEventListener('click', () => {
        load(true);
    });

    // The risk map section may sit inside a collapsible <details> (Farmer's
    // "View Details", MAO's dashboard groups). If it's collapsed and
    // reopened, Leaflet needs invalidateSize() before fitBounds is re-applied.
    const detailsSection = mapEl.closest('details');
    if (detailsSection) {
        detailsSection.addEventListener('toggle', () => {
            if (detailsSection.open) {
                setTimeout(() => {
                    map.invalidateSize();
                    fitAndLockToLianBounds();
                }, 0);
            }
        });
    }

    // A map that starts inside a hidden tab panel (MAO's "Heat Map & Weather"
    // tab, Admin's "Risk Map" tab) computes its initial fit against a
    // zero-size container. Correct it once that specific tab is shown, so
    // switching between unrelated tabs never resets manual pan/zoom.
    const hostPanel = mapEl.closest('[data-panel]');
    const hostTabButtons = hostPanel && hostPanel.dataset.panel
        ? document.querySelectorAll(`.dashboard-tab[data-tab="${hostPanel.dataset.panel}"]`)
        : document.querySelectorAll('.dashboard-tab');
    hostTabButtons.forEach((tabButton) => {
        tabButton.addEventListener('click', () => {
            setTimeout(() => {
                map.invalidateSize();
                fitAndLockToLianBounds();
            }, 50);
        });
    });
}
