// Fresh Leaflet implementation for the shared Lian Municipality Heat Map.
// Data and risk calculations remain server-owned; this module only renders
// the existing API response and GeoJSON boundary data.

import { renderOverview, openRiskCategory } from './risk-overview';
import { open as openModal } from './info-modal';
import { openBarangayInsight, refreshOpenInsight } from './barangay-insight';

const RISK_COLORS = { Low: '#2e7d32', Moderate: '#f2b134', High: '#e05a2b', Severe: '#8b0000', 'No Data': '#8a939b' };
const EXPECTED_BARANGAYS = 19;
// The page asks Laravel for the prepared map again this often (one request; Laravel refreshes the provider data).
const AUTO_REFRESH_MS = 5 * 60 * 1000;
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
    const weather = currentWeatherParts(entry);
    const yieldData = entry?.last_recorded_yield;
    const facts = weather.rows.map(([label, value]) => `${escapeHtml(label)}: ${escapeHtml(value)}`);
    if (yieldData?.value != null) facts.push(`Recorded yield: ${escapeHtml(yieldData.value)} t/ha`);
    return `<div class="risk-map-detail-content"><div><span class="risk-map-detail-kicker">Selected barangay</span><h3>${escapeHtml(displayName(name))}</h3></div><span class="risk-map-detail-risk" style="--risk-color:${RISK_COLORS[level]}">FARM RISK: ${escapeHtml(level.toUpperCase())}</span>${weather.stale || !weather.rows.length ? `<p>${escapeHtml(weather.notice)}</p>` : ''}${facts.length ? `<p>${facts.join(' · ')}</p>` : ''}${timestampsMarkup(entry, weather)}<button type="button" class="risk-map-detail-close" data-risk-map-detail-close>Close details</button></div>`;
}

// Weather and risk carry separate times: refreshed weather never makes an older rating look newer.
function timestampsMarkup(entry, weather) {
    const lines = [
        weather.updatedLong ? `Weather updated: ${weather.updatedLong}${weather.ago ? ` (${weather.ago})` : ''} · Source: ${weather.provider}` : null,
        `Risk updated: ${philippineDateTime(entry?.risk_calculated_at) || 'not available yet'}`,
        // A last verified rating kept while a newer one is made says so, next to its own (older) time.
        entry?.last_known_good && riskLevel(entry) !== 'No Data' ? 'Risk is being updated.' : null,
    ].filter(Boolean);
    return `<p class="rf-small">${lines.map(escapeHtml).join('<br>')}</p>`;
}

const CURRENT_RISK_MEANING = {
    Low: 'Conditions are generally good.',
    Moderate: 'Conditions need some attention.',
    High: 'Conditions may affect farm work.',
    Severe: 'Conditions need close attention.',
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

// Freshness rules (the server's, in its weather summary): fresh ≤ 10 min "Current", ≤ 30 min "Recent", older
// "delayed" (last available values + their time), no reading "unavailable". Worked out again here from the
// reading's own fetch time, so "Updated X minutes ago" stays true between updates.
let freshMinutes = 10;
let recentMinutes = 30;

function minutesSince(value) {
    const time = value ? new Date(value).getTime() : NaN;
    return Number.isNaN(time) ? null : Math.max(0, Math.floor((Date.now() - time) / 60000));
}

function agoText(minutes) {
    if (minutes == null) return null;
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
    const hours = Math.floor(minutes / 60);
    return hours < 24 ? `${hours} hour${hours === 1 ? '' : 's'} ago` : `${Math.floor(hours / 24)} day${hours < 48 ? '' : 's'} ago`;
}

const sentenceCase = (text) => (text ? text.charAt(0).toUpperCase() + text.slice(1).toLowerCase() : text);

function currentWeatherParts(entry) {
    const weather = entry?.weather || {};
    const temperature = numberValue(weather.temperature_c);
    const humidity = numberValue(weather.humidity_percent);
    const wind = numberValue(weather.wind_speed_kmh);
    const rainfall = numberValue(weather.rainfall_24h_mm);
    const chance = numberValue(weather.rain_probability_percent);
    const rainfallStatus = String(weather.rainfall_status || '').trim();
    const condition = weather.condition ? sentenceCase(String(weather.condition)) : null;
    const minutes = minutesSince(weather.fetched_at);
    const hasValues = weather.status !== 'unavailable' && temperature != null;
    const status = !hasValues ? 'unavailable' : minutes == null ? weather.status : minutes <= freshMinutes ? 'fresh' : minutes <= recentMinutes ? 'recent' : 'stale';
    const current = status === 'fresh' || status === 'recent';
    const stale = status === 'stale';
    const rows = [];

    if (hasValues) {
        if (condition) rows.push(['Condition', condition]);
        rows.push(['Temperature', `${temperature}°C`]);
        if (humidity != null) rows.push(['Humidity', `${humidity}%`]);
        if (wind != null) rows.push(['Wind', `${wind} km/h`]);
        if (chance != null) rows.push(['Rain chance (24 h)', `${chance}%`]);
        if (rainfallStatus) rows.push(['Rainfall (24 h)', `${rainfallStatus.replace(/\s+rainfall/i, '')}${rainfall != null ? ` · ${rainfall} mm` : ''}`]);
        else if (rainfall != null) rows.push(['Rainfall (24 h)', `${rainfall} mm`]);
    }

    let effect = 'No major weather problem is affecting the rice field right now.';
    let action = 'Continue normal farm work. Watch for weather updates.';
    if (rainfallStatus.toLowerCase().includes('high') || (rainfall != null && rainfall >= 80)) {
        effect = 'Too much water may collect in rice fields.';
        action = 'Check drainage and watch the water level.';
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
    if (!current) {
        // Only a current reading describes today's effect; an older one is never presented as today's conditions.
        effect = 'This will be checked again after the next weather update.';
        action = 'Check your field conditions and wait for the next update.';
    }

    const ago = agoText(minutes);
    const updatedTime = philippineTime(weather.fetched_at);
    // An older reading is shown with its time; with no reading at all the card says so calmly.
    const notice = current ? (ago ? `Updated ${ago}` : 'Latest weather') : stale ? (updatedTime ? `Updated: ${updatedTime}` : 'Last saved weather') : 'Walang datos pa';
    // One short line for the popup and the barangay list, e.g. "29.4°C · Light rain".
    const line = hasValues ? [`${temperature}°C`, condition].filter(Boolean).join(' · ') : '';
    return {
        rows,
        effect,
        action,
        status,
        current,
        fresh: current,
        stale,
        notice,
        line,
        ago,
        provider: weather.provider || 'Open-Meteo',
        updatedTime,
        updatedLong: philippineDateTime(weather.fetched_at),
        observedAt: updatedTime,
        values: { temperature_c: temperature, humidity_percent: humidity, rainfall_24h_mm: rainfall, rainfall_status: rainfallStatus || null },
        lastAvailable: stale && ago ? `Last available ${ago}${updatedTime ? ` (${updatedTime})` : ''}` : null,
    };
}

// Quick information card: name + risk, current weather, possible effect, what to do, update time.
function farmerCurrentPopupMarkup(name, entry, fit) {
    const level = riskLevel(entry);
    const weather = currentWeatherParts(entry);
    const updated = weather.current ? `${weather.notice} · ${weather.provider}` : weather.lastAvailable;
    const extra = weather.current ? weather.rows.filter(([label]) => label === 'Humidity' || label === 'Wind').map(([label, value]) => `${label} ${value}`).join(' · ') : '';
    return farmerPopupShell(name, level, ''
        + farmerBlock('Current Weather', `${weather.stale ? `<p>${escapeHtml(weather.notice)}</p>` : ''}<p>${escapeHtml(weather.line || weather.notice)}</p>${extra ? `<p class="rf-small">${escapeHtml(extra)}</p>` : ''}`)
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
        ? `${weather.stale ? `<p>${escapeHtml(weather.notice)}</p><p class="rf-small">${escapeHtml(weather.lastAvailable || '')}</p>` : ''}${rowsMarkup(weather.rows).replace('risk-forecast-facts', 'risk-forecast-facts rf-facts')}${timestampsMarkup(entry, weather)}`
        : `<p>${escapeHtml(weather.notice)}</p>${timestampsMarkup(entry, weather)}`;
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
    'Monitor soil moisture closely.': 'Check the soil often. Keep it moist.',
    'Irrigation is not necessary right now.': 'No need to irrigate for now.',
    'Reduce irrigation to avoid waterlogging.': 'Use less water so the field does not flood.',
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
const LEVEL_HINT = { Low: 'Conditions are generally good.', Moderate: 'Conditions need some attention.', High: 'Conditions may affect farm work.', Severe: 'Conditions need close attention.' };

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
        + farmerBlock(`Lian forecast · ${month?.label || ''}`, `<p>${escapeHtml(weather || 'No forecast for this month yet.')}</p>`)
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
    // The first ratings and forecast arrive inside the page (App\Support\RiskMapInitialData); each is used once.
    const initial = (() => {
        const node = shell?.querySelector('[data-risk-map-initial]');
        try { return node ? JSON.parse(node.textContent) || {} : {}; } catch (parseError) { return {}; }
    })();
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
    let weatherSummary = null;
    let riskRefreshing = false;
    const weatherHealth = card?.querySelector('[data-risk-weather-health]');
    const renderedNames = () => OFFICIAL_NAMES.filter((name) => layers.has(name));
    // Current Conditions health: how many barangays have a current reading, which are on their last available
    // one, when the provider was last checked, and the source. Counted from the same entries the map shows.
    const weatherHealthText = () => {
        const names = renderedNames();
        if (!names.length) return '';
        const states = names.map((name) => [displayName(name), currentWeatherParts(entries.get(name)).status]);
        const current = states.filter(([, status]) => status === 'fresh' || status === 'recent').length;
        const delayed = states.filter(([, status]) => status === 'stale').map(([name]) => name);
        const missing = states.filter(([, status]) => status === 'unavailable').map(([name]) => name);
        const checked = philippineTime(weatherSummary?.last_checked_at);
        const provider = weatherSummary?.provider || 'Open-Meteo';
        // "Unavailable" only when no barangay has any reading; older readings are "delayed" and still shown.
        const parts = [current
            ? `Latest weather is available for ${current} of ${names.length} barangays.`
            : delayed.length ? 'Showing the last saved weather.' : 'Walang datos pa (no weather saved yet).'];
        if (delayed.length) parts.push(current
            ? `${delayed.length} ${delayed.length === 1 ? 'barangay is' : 'barangays are'} temporarily using their last available update (${delayed.join(', ')}).`
            : `Showing the last available update for ${delayed.length} of ${names.length} barangays.`);
        if (missing.length && missing.length < names.length) parts.push(`No weather reading yet for ${missing.join(', ')}.`);
        parts.push(`Last checked: ${checked || 'not yet'} · Weather source: ${provider}`);
        return parts.join(' ');
    };
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
                : `Current conditions · risk updated ${generatedText || 'not yet'}${refreshQueued || riskRefreshing ? ' · risk is being updated' : ''}`,
        });
        // Monthly forecast values are predictions, so the current-weather health line only shows for "Now".
        if (weatherHealth) { weatherHealth.textContent = month ? '' : weatherHealthText(); weatherHealth.hidden = Boolean(month) || !weatherHealth.textContent; }
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
        updatedFor: (entry) => { const weather = currentWeatherParts(entry); return weather.updatedTime ? `${weather.updatedTime}${weather.stale && weather.ago ? ` · ${weather.ago}` : ''}` : null; },
        month: activeForecast(),
        generatedAt: generatedText,
        weatherSummary: activeForecast() ? null : weatherHealthText(),
        weatherCheckedAt: philippineDateTime(weatherSummary?.last_checked_at),
        riskTimeFor: (entry) => philippineTime(entry?.risk_calculated_at),
        sourceNote,
        mapAnchor: true,
        openInsight: insightUrl ? openInsight : null,
    });
    // Barangay AI Insight (barangay-insight.js): the same entry this map shows, explained in two short parts.
    const insightUrl = mapElement.dataset.insightUrl;
    let insightLanguage = mapElement.dataset.insightLanguage || 'en';
    // Highlights and zooms to the existing polygon (no new layer), then opens its usual popup.
    const viewOnMap = (name) => {
        const layer = layers.get(name);
        if (!layer) return;
        mapElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 15 });
        selectLayer(name, layer);
        window.setTimeout(() => {
            fitPopup(layer);
            layer.openPopup();
            if (farmerView) layer.getPopup().update();
        }, 450);
    };
    function openInsight(name, trigger, category = null) {
        openBarangayInsight(name, trigger, {
            url: insightUrl,
            entry: entries.get(name),
            weatherParts: currentWeatherParts(entries.get(name)),
            displayName,
            own: name === highlightBarangay,
            language: insightLanguage,
            onLanguage: (code) => { insightLanguage = code; },
            timeFor: philippineTime,
            onViewMap: viewOnMap,
            onBack: category ? () => openCategory(category, trigger) : null,
        });
    }
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
            : '<span class="risk-mode-badge">Current Conditions</span> Today\'s risk · latest weather';
        if (aboutConfidence) aboutConfidence.textContent = month?.confidence_level ? `${month.confidence_level} (${month.label})` : 'Shown when you pick a forecast month';
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
        if (forecastRequest) return forecastRequest;
        const embedded = initial.forecast;
        initial.forecast = null;
        // loader: false keeps the full-page iClimate overlay away; the map shows its own placeholder.
        forecastRequest = (embedded ? Promise.resolve(embedded) : fetch(forecastBox.dataset.forecastUrl, { headers: { Accept: 'application/json' }, loader: false })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`Forecast request failed (${response.status})`)))))
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
    // The risk URL: ?refresh=1 for Refresh Weather; ?priority= asks for the farmer's own barangay first.
    const riskUrl = (forceRefresh) => {
        const url = new URL(mapElement.dataset.riskMapUrl, window.location.href);
        if (forceRefresh) url.searchParams.set('refresh', '1');
        if (highlightBarangay) url.searchParams.set('priority', highlightBarangay);
        return url.toString();
    };
    let followUp = null;
    // Takes in one /api/lian/risk-map response; returns any coverage problems found in it.
    const applyRisk = (risk) => {
        // "Risk calculated" is the newest rating's own time (a kept older rating never borrows the weather's time).
        const ratedTimes = (risk.barangays || []).map((entry) => entry.risk_calculated_at).filter(Boolean).sort();
        generatedText = philippineDateTime(ratedTimes[ratedTimes.length - 1] || null) || philippineDateTime(risk.generated_at);
        riskRefreshing = (risk.barangays || []).some((entry) => entry.last_known_good && riskLevel(entry) !== 'No Data');
        sourceNote = risk.source_note || '';
        refreshQueued = Boolean(risk.refresh_queued);
        weatherSummary = risk.weather_summary || null;
        if (weatherSummary?.fresh_minutes) freshMinutes = Number(weatherSummary.fresh_minutes);
        if (weatherSummary?.recent_minutes) recentMinutes = Number(weatherSummary.recent_minutes);
        // A weather refresh was due and is running on the server now: ask again shortly for its result.
        window.clearTimeout(followUp);
        // The same after a Refresh weather & risk: the ratings are made right after that response.
        if (risk.weather_refresh_scheduled || risk.risk_refresh_scheduled) followUp = window.setTimeout(() => updateRisk(false), 15000);
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
        return problems;
    };
    const fetchRisk = async (forceRefresh) => {
        if (!forceRefresh && initial.risk?.status === 'ok') {
            const risk = initial.risk;
            initial.risk = null;
            return risk;
        }
        // loader: false keeps the full-page iClimate overlay away; the button shows its own small loader.
        const response = await fetch(riskUrl(forceRefresh), { headers: { Accept: 'application/json' }, loader: false });
        if (!response.ok) throw new Error(`Heat map data request failed (${response.status})`);
        const risk = await response.json();
        if (risk.status !== 'ok') throw new Error('Heat map data is unavailable');
        return risk;
    };
    const render = async (forceRefresh = false) => {
        let ok = false;
        loading?.removeAttribute('hidden');
        if (error) error.hidden = true;
        if (refresh) refresh.disabled = true;
        try {
            const [boundaryResponse, risk] = await Promise.all([
                fetch(mapElement.dataset.boundaryUrl, { headers: { Accept: 'application/json' } }),
                fetchRisk(forceRefresh),
            ]);
            if (!boundaryResponse.ok) throw new Error('Heat map data request failed');
            const boundary = await boundaryResponse.json();
            if (!Array.isArray(boundary.features)) throw new Error('Heat map data is unavailable');
            const problems = applyRisk(risk);
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
            if (layers.size !== EXPECTED_BARANGAYS || problems.length) console.warn(`Lian heat map: ${layers.size} of ${EXPECTED_BARANGAYS} barangays rendered.`, problems);
            const bounds = geoLayer.getBounds();
            if (bounds.isValid() && !mapElement.dataset.fitted) { map.fitBounds(bounds, { padding: [20, 20] }); mapElement.dataset.fitted = 'true'; }
            ok = true;
        } catch (requestError) {
            console.error('iClimate could not load the Lian heat map.', requestError);
            if (error) { error.textContent = 'The heat map could not be loaded. Please try again.'; error.hidden = false; }
            const forLine = overviewRoot?.querySelector('[data-risk-overview-for]');
            if (forLine) forLine.textContent = 'The latest ratings could not load. Showing the last ones, if any.';
        } finally {
            document.dispatchEvent(new CustomEvent('iclimate:risk-map-rendered', { detail: { ok, refreshQueued } }));
            loading?.setAttribute('hidden', 'hidden');
            if (refresh) refresh.disabled = false;
            window.setTimeout(() => map.invalidateSize(), 0);
        }
    };
    // After the first load only the ratings and weather change: they are fetched again (one request) and the
    // existing polygons are recoloured, so the boundaries, mask and map position stay exactly as they are.
    let riskRequest = null;
    const refreshLabel = refresh?.textContent;
    const updateRisk = (forceRefresh = false) => {
        if (!layers.size) return render(forceRefresh);
        if (riskRequest) return riskRequest;
        if (forceRefresh && refresh) { refresh.disabled = true; refresh.setAttribute('aria-busy', 'true'); refresh.textContent = 'Refreshing…'; }
        let ok = false;
        riskRequest = fetchRisk(forceRefresh)
            .then((risk) => {
                const problems = applyRisk(risk);
                OFFICIAL_NAMES.forEach((name) => { if (layers.has(name) && !entries.has(name)) problems.push(`${name} has no risk record (shown as No Data)`); });
                if (problems.length) console.warn('Lian heat map: coverage problems after update.', problems);
                layers.forEach((layer, name) => {
                    layer.setStyle(styleFor(name, name === selectedName));
                    if (layer.isPopupOpen()) layer.getPopup().update();
                });
                updateCounts();
                if (selectedName && details && !details.hidden) showDetails(selectedName);
                // An open AI Insight is explained again only if its barangay's weather or risk time changed.
                refreshOpenInsight((name) => entries.get(name), currentWeatherParts);
                if (error) error.hidden = true;
                ok = true;
            })
            .catch((requestError) => {
                console.error('iClimate could not update the Lian heat map.', requestError);
                // The last loaded ratings and weather stay on the map; only an explicit refresh reports the failure.
                if (forceRefresh && error) { error.textContent = 'The latest weather could not load. Showing the last readings.'; error.hidden = false; }
            })
            .finally(() => {
                riskRequest = null;
                if (refresh) { refresh.disabled = false; refresh.removeAttribute('aria-busy'); refresh.textContent = refreshLabel; }
                document.dispatchEvent(new CustomEvent('iclimate:risk-map-rendered', { detail: { ok, refreshQueued } }));
            });
        return riskRequest;
    };
    refresh?.addEventListener('click', () => updateRisk(true));
    // The page's own Refresh Data button (MAO dashboard) refreshes the map, overview and counts together.
    document.addEventListener('iclimate:risk-map-refresh', () => updateRisk(true));
    // Keep "Now" current without a page reload: one request to Laravel every few minutes while the page is visible.
    window.setInterval(() => { if (document.visibilityState === 'visible') updateRisk(false); }, AUTO_REFRESH_MS);
    render();
}
