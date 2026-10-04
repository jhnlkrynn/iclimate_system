// Fresh Leaflet implementation for the shared Lian Municipality Risk Map.
// Data and risk calculations remain server-owned; this module only renders
// the existing API response and GeoJSON boundary data.

import { renderOverview, openRiskCategory } from './risk-overview';
import { open as openModal } from './info-modal';

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
    return `<div class="risk-map-detail-content"><div><span class="risk-map-detail-kicker">Selected barangay</span><h3>${escapeHtml(displayName(name))}</h3></div><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">FARM RISK: ${escapeHtml(level.toUpperCase())}</span>${facts.length ? `<p>${facts.join(' · ')}</p>` : '<p>Current weather information is temporarily unavailable.</p>'}<button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
}

const CURRENT_RISK_MEANING = {
    Low: 'Conditions are generally okay.',
    Moderate: 'Keep monitoring the weather and your rice field.',
    High: 'Weather may affect your rice field. Prepare to take action.',
    Severe: 'Conditions may seriously affect farming. Follow official advisories.',
    'No Data': 'The current farm risk is not available yet.',
};

const numberValue = (value) => (value == null || value === '' || Number.isNaN(Number(value)) ? null : Number(value));

function philippineTime(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    try {
        return new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', timeStyle: 'short' }).format(date);
    } catch (error) {
        return date.toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
    }
}

function philippineDateTime(value) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return new Intl.DateTimeFormat('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
}

function currentWeatherParts(entry) {
    const weather = entry?.weather || {};
    const temperature = numberValue(weather.temperature_c);
    const humidity = numberValue(weather.humidity_percent);
    const wind = numberValue(weather.wind_speed_kmh);
    const rainfall = numberValue(weather.rainfall_24h_mm);
    const rainfallStatus = String(weather.rainfall_status || '').trim();
    const fresh = weather.status === 'fresh';
    const stale = weather.status === 'stale' || entry?.status === 'stale';
    const rows = [];

    if (fresh) {
        if (rainfallStatus) rows.push(['Rainfall', rainfallStatus.replace(/\s+rainfall/i, '')]);
        else if (rainfall != null) rows.push(['Rainfall', `${rainfall} mm`]);
        if (temperature != null) rows.push(['Temperature', `${temperature}°C`]);
        if (humidity != null) rows.push(['Humidity', `${humidity}%`]);
    }

    let effect = 'No major weather problem is affecting the rice field right now.';
    let action = 'Continue normal farm work and monitor weather updates.';
    if (rainfallStatus.toLowerCase().includes('high') || (rainfall != null && rainfall >= 80)) {
        effect = 'Too much water may collect in rice fields.';
        action = 'Check drainage and monitor water levels.';
    } else if (rainfallStatus.toLowerCase().includes('low') || (rainfall != null && rainfall < 25)) {
        effect = 'Rice fields may need more water.';
        action = 'Check water levels and prepare irrigation if needed.';
    } else if (temperature != null && temperature >= 35) {
        effect = 'Hot weather may affect rice plants.';
        action = 'Keep enough water in the field.';
    } else if (wind != null && wind > 20) {
        effect = 'Strong winds may damage rice plants.';
        action = 'Check rice plants and secure loose farm materials.';
    }
    if (!rows.length && !stale) {
        // The weather line already says it is unavailable; this line says what that means, without repeating it.
        effect = 'The effect on your rice field will be checked again after the next update.';
        action = 'Check your field conditions and wait for the next update.';
    }

    const observedAt = philippineTime(weather.observed_at || (fresh ? entry?.updated_at : null));
    const notice = fresh ? (observedAt ? `Updated: ${observedAt}` : 'Weather information has not been updated yet.') : stale ? 'Weather update is temporarily delayed.' : 'Latest weather information is temporarily unavailable.';
    // One short line for the popup, e.g. "29°C · 78% humidity · Low rainfall".
    const line = fresh ? [
        temperature != null ? `${temperature}°C` : null,
        humidity != null ? `${humidity}% humidity` : null,
        rainfallStatus || (rainfall != null ? `${rainfall} mm rain` : null),
    ].filter(Boolean).join(' · ') : '';
    return {
        rows,
        effect,
        action,
        fresh,
        stale,
        observedAt,
        notice,
        line,
        lastAvailable: !fresh && observedAt ? `Last update: ${observedAt}` : null,
    };
}

// Quick information card: name + risk, current weather, possible effect, what to do, update time.
function farmerCurrentPopupMarkup(name, entry, fit) {
    const level = riskLevel(entry);
    const weather = currentWeatherParts(entry);
    const updated = weather.fresh ? (weather.observedAt ? `Updated: ${weather.observedAt}` : null) : weather.lastAvailable;
    return farmerPopupShell(name, level, ''
        + farmerBlock('Current Weather', `<p>${escapeHtml(weather.line || weather.notice)}</p>`)
        + farmerBlock('Possible Effect', `<p>${escapeHtml(weather.effect)}</p>`)
        + farmerBlock('What You Can Do', `<p>${escapeHtml(weather.action)}</p>`)
        + (updated ? `<p class="rf-updated">${escapeHtml(updated)}</p>` : ''), fit);
}

// The name and risk badge stay on top; only the part below scrolls if the visible map is too short.
function farmerPopupShell(name, level, body, fit) {
    const badge = level === 'No Data' ? 'NO DATA' : `${level.toUpperCase()} RISK`;
    return `<div class="risk-map-popup rf-popup"${fit?.bodyMax ? ` style="--rf-body-max:${Math.round(fit.bodyMax)}px"` : ''}><div class="rf-head"><strong>${escapeHtml(displayName(name))}</strong><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">${escapeHtml(badge)}</span></div><div class="rf-popup-body">${body}</div></div>`;
}

function farmerCurrentDetailMarkup(name, entry) {
    const level = riskLevel(entry);
    const weather = currentWeatherParts(entry);
    const conditions = weather.rows.length
        ? `${rowsMarkup(weather.rows).replace('risk-forecast-facts', 'risk-forecast-facts rf-facts')}<p class="rf-small">${escapeHtml(weather.notice)}</p>`
        : `<p>${escapeHtml(weather.notice)}</p>${weather.lastAvailable ? `<p class="rf-small">${escapeHtml(weather.lastAvailable)}</p>` : ''}${!weather.stale ? `<p>The current farm risk is still: <strong>${escapeHtml(level.toUpperCase())}</strong></p>` : ''}`;
    const riskMeaning = CURRENT_RISK_MEANING[level] || CURRENT_RISK_MEANING['No Data'];
    return `<div class="rf-detail"><div class="rf-head"><h3>${escapeHtml(displayName(name))}</h3><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">${escapeHtml(`${level.toUpperCase()} RISK`)}</span></div><div class="rf-grid">${farmerBlock('Current Weather', conditions)}${farmerBlock('Risk Meaning', `<p>${escapeHtml(riskMeaning)}</p>`)}${farmerBlock('Possible Effect', `<p>${escapeHtml(weather.effect)}</p>`)}${farmerBlock('What You Can Do', `<p>${escapeHtml(weather.action)}</p>`)}</div><button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
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

// ---- Farmer view: the same forecast and advice, in short plain words. ----
// Translations of the risk engine's own sentences (DecisionSupportService / BarangayRiskForecastService).
// The advice is unchanged; a sentence not listed here is shown as the engine wrote it.
const PLAIN_EFFECT = {
    'Very little rain is expected': 'Low rainfall may cause a water shortage in rice fields.',
    'Less rain than rice usually needs': 'Rice fields may need more water because rainfall is low.',
    'Heavy rainfall may occur': 'Heavy rain may flood rice fields, especially in low areas.',
    'More rain than ideal': 'Rice fields may stay too wet.',
    'Strong winds are possible': 'Strong wind may knock down rice plants.',
    'Very humid weather': 'Humid weather may bring more pests and rice diseases.',
    'Dry season on rainfed farms': 'Farms without irrigation may run short of water.',
    'Low recorded harvest': 'Past harvests here were low.',
    'Average recorded harvest': 'Past harvests here were average.',
    'No major weather concern': 'No major weather problem is expected for rice.',
};
const PLAIN_ACTION = {
    'Increase irrigation or provide supplemental water support.': 'Give the rice field enough water.',
    'Monitor soil moisture closely.': 'Check often that the soil stays moist.',
    'Irrigation is not necessary right now.': 'No need to irrigate for now.',
    'Reduce irrigation to avoid waterlogging.': 'Use less irrigation water so the field does not flood.',
    'Maintain the irrigation schedule.': 'Keep your usual irrigation schedule.',
    'Maintain normal irrigation monitoring.': 'Keep checking your irrigation as usual.',
    'Ideal planting period.': 'This is a good time to plant.',
    'Plant with caution and monitor field drainage.': 'You can plant, but keep drainage canals clear.',
    'Delay planting due to flood risk.': 'Wait to plant because the field may flood.',
    'Wait for sufficient rainfall before planting.': 'If rainfall stays low, consider delaying planting.',
    'Delay planting during dry season until rainfall improves.': 'If rainfall stays low, consider delaying planting.',
    'Consider adjusting the planting schedule because expected yield is low.': 'Consider changing your planting schedule.',
    'Continue normal farm work and watch PAGASA/local weather advisories.': 'Continue normal farm work and watch for weather advisories.',
};
const CONFIDENCE_HINT = { Low: 'A rough guide only; it may change.', Moderate: 'Useful, but may still change.', High: 'Fairly reliable, but may still change.' };
const LEVEL_HINT = { Low: 'Conditions are generally okay.', Moderate: 'Keep monitoring the weather.', High: 'Weather may affect rice farming.', Severe: 'Take extra care and follow advisories.' };

const plainActions = (action) => [...new Set(String(action || '').split(/(?<=\.)\s+/).map((sentence) => sentence.trim()).filter(Boolean).map((sentence) => PLAIN_ACTION[sentence] || sentence))];

// "Same as January" / "Higher than January ↑" / "Lower than January ↓", against the previous forecast month.
function riskChange(entry, month, months) {
    const index = (months || []).findIndex((candidate) => candidate.month === month?.month);
    if (index < 1 || !entry?.trend) return null;
    const previous = months[index - 1].label.split(' ')[0];
    return { stable: `Same as ${previous}`, increasing: `Higher than ${previous} ↑`, decreasing: `Lower than ${previous} ↓` }[entry.trend] || null;
}

function farmerParts(entry, month, months) {
    const level = riskLevel(entry);
    const weather = month?.weather || {};
    const factors = entry?.factors || {};
    return {
        level,
        badge: level === 'No Data' ? 'NO FORECAST DATA' : `${level.toUpperCase()} RISK`,
        weather: [
            ['Rainfall', weather.rainfall_mm != null ? `About ${weather.rainfall_mm} mm` : null],
            ['Temperature', weather.temperature_c != null ? `${Math.round(weather.temperature_c)}°C` : null],
            ['Humidity', weather.humidity_percent != null ? `${weather.humidity_percent}%` : null],
        ].filter(([, value]) => value),
        effect: entry?.main_concern && entry.main_concern !== 'Insufficient Forecast Data' ? (PLAIN_EFFECT[entry.main_concern] || entry.impact) : null,
        actions: plainActions(entry?.action),
        change: riskChange(entry, month, months),
        confidence: month?.confidence_level ? `${month.confidence_level}${CONFIDENCE_HINT[month.confidence_level] ? ` · ${CONFIDENCE_HINT[month.confidence_level]}` : ''}` : null,
        farmType: factors.farm_type ? (factors.farm_type_from_records ? factors.farm_type : 'Farm type not yet confirmed.') : null,
        production: factors.yield_record ? `${factors.yield_record.value} t/ha (${factors.yield_record.year})` : null,
    };
}

const farmerBadge = (parts) => `<span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[parts.level]}" title="${escapeHtml(LEVEL_HINT[parts.level] || '')}">${escapeHtml(parts.badge)}</span>`;
const farmerWeather = (parts) => (parts.weather.length ? `<div class="rf-weather">${parts.weather.map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('')}</div>` : '');
// One short line for the popup: the label itself says the forecast is for Lian, so no extra note is needed there.
// One line, e.g. "About 74 mm rain · 27°C · 79% humidity"; the block title says it is the Lian forecast.
const farmerPopupWeather = (parts) => {
    const values = { Rainfall: (v) => `${v} rain`, Temperature: (v) => v, Humidity: (v) => `${v} humidity` };
    return parts.weather.map(([label, value]) => values[label](value)).join(' · ');
};
const farmerBlock = (title, body) => (body ? `<section class="rf-block"><h4>${escapeHtml(title)}</h4>${body}</section>` : '');
const farmerActions = (parts) => (parts.actions.length ? parts.actions.map((action) => `<p>${escapeHtml(action)}</p>`).join('') : '');

function farmerPopupMarkup(name, entry, month, months, fit) {
    const parts = farmerParts(entry, month, months);
    const weather = farmerPopupWeather(parts);
    return farmerPopupShell(name, parts.level, ''
        + farmerBlock(`Lian forecast · ${month?.label || ''}`, `<p>${escapeHtml(weather || 'Forecast weather is not available for this month.')}</p>`)
        + farmerBlock('Possible Effect', parts.effect ? `<p>${escapeHtml(parts.effect)}</p>` : '')
        + farmerBlock('What You Can Do', parts.actions.length ? `<p>${escapeHtml(parts.actions.join(' '))}</p>` : ''), fit);
}

function farmerDetailMarkup(name, entry, month, months) {
    const parts = farmerParts(entry, month, months);
    const facts = [['Forecast Month', month?.label], ['Risk Change', parts.change]].filter(([, value]) => value);
    const farm = [['Farm Type', parts.farmType], ['Rice Production Record', parts.production]].filter(([, value]) => value);
    const strip = (entry?.timeline || []).map((point) => `<span class="${point.month === month?.short_label ? 'is-active' : ''}" style="--risk-color:${RISK_COLORS[riskLevel(point)]}"${point.month === month?.short_label ? ' aria-current="true"' : ''}>${escapeHtml(point.month)} <b>${escapeHtml(point.risk_level)}</b></span>`).join('');
    return `<div class="rf-detail">`
        + `<div class="rf-head"><h3>${escapeHtml(displayName(name))}</h3>${farmerBadge(parts)}</div>`
        + `${facts.length ? rowsMarkup(facts).replace('risk-forecast-facts', 'risk-forecast-facts rf-facts') : ''}`
        + `<div class="rf-grid">`
        + farmerBlock('Weather Forecast for Lian', `${farmerWeather(parts)}${parts.confidence ? `<p class="rf-small">Forecast confidence: ${escapeHtml(parts.confidence)}</p>` : ''}<p class="rf-note">Weather forecast applies to Lian, Batangas.</p>`)
        + farmerBlock('Possible Effect', parts.effect ? `<p>${escapeHtml(parts.effect)}</p>` : '')
        + farmerBlock('What You Can Do', farmerActions(parts))
        + `</div>`
        + (farm.length ? farmerBlock('Farm Information', rowsMarkup(farm)) : '')
        + (strip ? farmerBlock('Next Months', `<div class="risk-forecast-strip">${strip}</div>`) : '')
        + `<button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
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
    if (!mapElement) return;
    if (!window.L || mapElement.dataset.initialized === 'true') return;
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
    const farmerView = forecastBox?.dataset.forecastAudience === 'farmer';
    // Where a farmer popup can sit fully visible: inside the map, below the sticky Forecast View toolbar and the
    // app's top bar, and inside the window. Measured at click time because it depends on the scroll position.
    const forecastToolbar = card?.querySelector('[data-risk-map-toolbar]');
    let popupFit = null;
    const measurePopupFit = () => {
        const box = mapElement.getBoundingClientRect();
        const covers = [forecastToolbar, document.querySelector('.topbar')].map((element) => element?.getBoundingClientRect().bottom ?? 0);
        const visibleTop = Math.max(box.top, 0, ...covers);
        const visibleBottom = Math.min(box.bottom, window.innerHeight);
        // Leaflet's maxWidth is the content width; the card adds ~30px of margin and border around it.
        // Content ≤250px keeps the card near 280px, and on phones at least 20px clear of each map edge.
        const width = Math.max(180, Math.min(250, box.width - 72));
        let top = Math.max(0, visibleTop - box.top) + 12;
        let left = 16;
        // Leaflet draws its zoom buttons above popups: keep the card beside them where the map is wide enough,
        // otherwise (phones) below them.
        const zoom = mapElement.querySelector('.leaflet-control-zoom')?.getBoundingClientRect();
        if (zoom && zoom.height) {
            const besideZoom = zoom.right - box.left + 8;
            if (box.width - besideZoom - 16 >= width + 32) left = besideZoom;
            else top = Math.max(top, zoom.bottom - box.top + 8);
        }
        return {
            top,
            left,
            bottom: Math.max(0, box.bottom - visibleBottom) + 12,
            width,
            // Room for the scrolling part under the popup's name/risk row (~48px) and above its pointer (~20px).
            bodyMax: Math.max(110, box.top + box.height - Math.max(visibleTop, box.top + top - 12) - (Math.max(0, box.bottom - visibleBottom)) - 24 - 48 - 20),
        };
    };
    const fitPopup = (layer) => {
        if (!farmerView || !layer.getPopup()) return;
        popupFit = measurePopupFit();
        Object.assign(layer.getPopup().options, {
            autoPanPaddingTopLeft: [popupFit.left, popupFit.top],
            autoPanPaddingBottomRight: [16, popupFit.bottom],
            maxWidth: popupFit.width,
            minWidth: Math.min(220, popupFit.width),
        });
    };
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
        details.innerHTML = !activeMonth ? (farmerView ? farmerCurrentDetailMarkup(name, entries.get(name)) : detailMarkup(name, entries.get(name)))
            : farmerView ? farmerDetailMarkup(name, forecastEntries.get(name), activeForecast(), forecastData?.months)
            : forecastDetailMarkup(name, forecastEntries.get(name), activeForecast());
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
    // Municipal Risk Overview (donut + categories) and its pop-ups use exactly these counts: one tally for the
    // legend, the overview and every pop-up, recounted whenever the data or the forecast month changes.
    const overviewRoot = card?.querySelector('[data-risk-overview]');
    const audience = overviewRoot?.dataset.audience === 'farmer' || farmerView ? 'farmer' : 'staff';
    let generatedText = null;
    let sourceNote = '';
    let refreshQueued = false;
    const renderedNames = () => OFFICIAL_NAMES.filter((name) => layers.has(name));
    const updateCounts = () => {
        const counts = { Low: 0, Moderate: 0, High: 0, Severe: 0, 'No Data': 0 };
        layers.forEach((_, name) => { counts[riskLevel(activeEntries().get(name))] += 1; });
        Object.entries(counts).forEach(([level, count]) => { const target = card?.querySelector(`[data-risk-count="${level}"]`); if (target) target.textContent = count; });
        const month = activeForecast();
        renderOverview(overviewRoot, {
            counts,
            monitored: layers.size,
            forLine: month
                ? `Monthly forecast · ${month.label}`
                : `Current conditions · ratings updated ${generatedText || 'recently'}${refreshQueued ? ' · a refresh is running in the background' : ''}`,
        });
        document.dispatchEvent(new CustomEvent('iclimate:risk-counts', { detail: { counts, monitored: layers.size, month: month?.month || null, generatedAt: generatedText } }));
        updateForecastPanel(counts);
    };
    const categoryContext = () => ({
        audience,
        names: renderedNames(),
        entryFor: (name) => activeEntries().get(name),
        levelOf: riskLevel,
        displayName,
        weatherFor: currentWeatherParts,
        updatedFor: (entry) => philippineDateTime(entry?.updated_at),
        month: activeForecast(),
        generatedAt: generatedText,
        sourceNote,
        mapAnchor: true,
    });
    const openCategory = (category, trigger) => {
        if (!layers.size) {
            openModal({ title: 'Risk data is loading', icon: 'clock', trigger, size: 'compact', body: '<p class="fad-m-body">The barangay ratings are still loading. Please try again in a moment.</p>' });
            return;
        }
        openRiskCategory(category, trigger, categoryContext());
    };
    card?.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-risk-category]');
        if (!trigger) return;
        event.preventDefault();
        // A donut arc is not focusable; focus returns to its matching row when the pop-up closes.
        const focusBack = trigger instanceof SVGElement ? card.querySelector(`.risk-overview-item[data-risk-category="${trigger.dataset.riskCategory}"]`) : trigger;
        openCategory(trigger.dataset.riskCategory, focusBack);
    });
    // Other parts of the page (the MAO "Barangays Monitored" card) open the same pop-ups.
    document.addEventListener('iclimate:open-risk-category', (event) => openCategory(event.detail?.category || 'monitored', event.detail?.trigger));
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-risk-goto-map]')) return;
        window.setTimeout(() => { mapElement.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 50);
    });
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
        let ok = false;
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
            generatedText = philippineDateTime(risk.generated_at);
            sourceNote = risk.source_note || '';
            refreshQueued = Boolean(risk.refresh_queued);
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
                    layer.bindPopup(() => (!activeMonth ? (farmerView ? farmerCurrentPopupMarkup(name, entries.get(name), popupFit) : popupMarkup(name, entries.get(name)))
                        : farmerView ? farmerPopupMarkup(name, forecastEntries.get(name), activeForecast(), forecastData?.months, popupFit)
                        : forecastPopupMarkup(name, forecastEntries.get(name), activeForecast())), farmerView
                        // Farmer popup: pans fully into the visible part of the map and stays there; its body scrolls instead of the whole card.
                        ? { closeButton: true, maxWidth: 250, autoPan: true, keepInView: true, className: 'rf-leaflet-popup' }
                        : { closeButton: true, maxWidth: 260, maxHeight: 320 });
                    layer.on({ mouseover: () => layer.setStyle(styleFor(name, name === selectedName, true)), mouseout: () => layer.setStyle(styleFor(name, name === selectedName)), mousedown: () => fitPopup(layer), click: () => { fitPopup(layer); selectLayer(name, layer); layer.openPopup(); if (farmerView) layer.getPopup().update(); } });
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
            ok = true;
        } catch (requestError) {
            console.error('iClimate could not load the Lian risk map.', requestError);
            if (error) { error.textContent = 'The risk map could not be loaded. Please try again.'; error.hidden = false; }
            const forLine = overviewRoot?.querySelector('[data-risk-overview-for]');
            if (forLine) forLine.textContent = 'The latest ratings could not be loaded. Showing the last loaded values, if any.';
        } finally {
            document.dispatchEvent(new CustomEvent('iclimate:risk-map-rendered', { detail: { ok, refreshQueued } }));
            loading?.setAttribute('hidden', 'hidden');
            if (refresh) refresh.disabled = false;
            window.setTimeout(() => map.invalidateSize(), 0);
        }
    };
    refresh?.addEventListener('click', () => render(true));
    // The page's own Refresh Data button (MAO dashboard) refreshes the map, overview and counts together.
    document.addEventListener('iclimate:risk-map-refresh', () => render(true));
    render();
}
