/*
 * iClimate chart explainer.
 *
 * A Chart.js plugin registered globally, so EVERY chart (existing and future) gets:
 *   - an "ⓘ Learn More" button beside its title,
 *   - click / tap / Enter on the chart to open a plain-language explanation modal,
 *   - tooltips that read "Category / Date" + "Series: value unit".
 *
 * Explanations are written per role (Farmer, MAO Personnel, IT Expert) and are always
 * combined with facts read from the chart's real data (period, units, record counts and
 * the "What should I notice?" sentence). A statement is only produced when the data
 * supports it; otherwise the modal says there is not enough data.
 *
 * Extra metadata can be supplied per chart, without touching this file, with either:
 *   options.plugins.icInfo = { title, unit, source, dataType, period, shows, read, meaning, note, method }
 *   or data-chart-* attributes on the <canvas> (data-chart-source, data-chart-type, ...).
 */

const ROLE_BY_LABEL = { 'Farmer': 'farmer', 'MAO Personnel': 'mao', 'IT Expert': 'it' };

const DATA_TYPES = {
    historical: 'Historical (recorded in the past)',
    observed: 'Observed (measured / actually recorded)',
    calculated: 'Calculated (worked out from stored records)',
    predicted: 'Predicted (a forecast, not a measurement)',
    mixed: 'Historical records plus predicted values',
    count: 'Count of stored records',
};

const pick = (value, role) => (value && typeof value === 'object' ? (value[role] ?? value.default ?? '') : (value ?? ''));

/* ------------------------------------------------------------------ registry */
// Keys are the chart id (data-chart-card / canvas id). Aliases at the bottom.
const REGISTRY = {
    'yield-by-year': {
        title: 'Average Yield by Year', unit: 't/ha', type: 'historical',
        source: 'iClimate rice production records (municipal)',
        method: 'Each point is the mean of that year\'s monthly average yields.',
        shows: { farmer: 'How much rice was harvested per hectare, on average, in each year.', default: 'The average rice yield for each year on record.' },
        read: { farmer: 'A higher point means a better harvest that year.', default: 'Each point is one year. Higher points mean higher average rice yield (t/ha).' },
        meaning: {
            farmer: 'Years with lower yield often had hard weather or pests. Use it to see how harvests have changed over time.',
            mao: 'Compare years to spot improving or declining productivity across the municipality. One low year may reflect weather rather than a lasting problem.',
            it: 'Values come from stored rice production rows aggregated per year; gaps mean no rows exist for that year.',
        },
    },
    'yield-by-month': {
        title: 'Average Yield by Month', unit: 't/ha', type: 'historical',
        source: 'iClimate rice production records (municipal monthly averages)',
        method: 'Monthly average yield across the municipality for the selected year.',
        shows: { farmer: 'How much rice was harvested per hectare, on average, in each month.', default: 'The average rice yield recorded for each month of the selected year.' },
        read: 'Higher bars mean higher average rice yield for that month. Months without a bar have no record.',
        meaning: {
            farmer: 'Months with taller bars are when harvests were best. Think about this when choosing your planting time.',
            mao: 'Shows the seasonal pattern of harvests. Compare the strongest and weakest months to plan support and monitoring.',
            it: 'One value per calendar month from the modeling dataset. Missing months are shown blank, never as zero.',
        },
    },
    'yield-trend': {
        title: 'Rice Yield Trend', unit: 't/ha', type: 'mixed',
        source: 'iClimate rice production records; a predicted value is shown when available',
        method: 'Yearly mean of monthly average yields. A separate marker, when shown, is a predicted value.',
        shows: { farmer: 'How rice harvests have changed from year to year, and what is expected next.', default: 'Yearly average rice yield over time, with the model\'s prediction shown separately.' },
        read: 'The line joins recorded yearly yields. Dashed lines or different markers are predictions, not recorded harvests.',
        meaning: {
            farmer: 'A line going up means harvests are getting better. A prediction is only a guide, not a promise.',
            mao: 'Use the direction of the line to judge productivity trends. Treat predicted points as estimates.',
            it: 'Recorded and predicted values are separate datasets. Predictions come from the Python analytics service.',
        },
    },
    'production-trend': {
        title: 'Rice Production Trend', unit: 'tons', type: 'historical',
        source: 'iClimate rice production records (barangay production reports)',
        method: 'Total tons reported per year. Barangay coverage varies by year.',
        shows: 'The total rice production reported for each year.',
        read: 'Each point is one year. Higher points mean more tons of rice reported.',
        meaning: {
            farmer: 'This shows how much rice the whole municipality produced each year.',
            mao: 'Totals depend on how many barangays reported that year, so a drop can be a reporting gap, not a real fall in production.',
            it: 'SUM(production) grouped by year. Years with fewer reporting barangays are under-counted.',
        },
    },
    'production-by-barangay': {
        title: 'Production by Barangay', unit: 'tons', type: 'historical',
        source: 'iClimate rice production records (barangay production reports)',
        method: 'Total tons reported per barangay for the selected year.',
        shows: 'How much rice each barangay produced in the selected year.',
        read: 'Taller bars mean more tons of rice from that barangay.',
        meaning: {
            farmer: 'Shows which barangays produce the most rice.',
            mao: 'Larger barangays naturally produce more. Use it for coverage and to see where production is concentrated.',
            it: 'Grouped by barangay for the selected year; only barangays with records appear.',
        },
    },
    'monthly-rainfall': {
        title: 'Monthly Rainfall', unit: 'mm', type: 'mixed',
        source: 'Ambulong weather records; lighter bars are forecasts',
        method: 'Monthly rainfall totals. Recorded and forecast months are separate series.',
        shows: { farmer: 'How much rain fell each month, and how much is expected.', default: 'Recorded monthly rainfall, with model forecasts shown as lighter bars.' },
        read: 'Taller bars mean more rain (millimetres). Lighter bars are forecasts, not measurements.',
        meaning: {
            farmer: 'Heavy-rain months can flood fields; very dry months may need more irrigation.',
            mao: 'Use it to anticipate flood-prone and dry periods and to time advisories.',
            it: 'Actual series from the Ambulong monthly dataset; forecast series from stored monthly weather predictions.',
        },
    },
    'weather-trend': {
        title: 'Weather Trend', unit: '', type: 'mixed',
        source: 'Ambulong monthly weather records; dashed lines are forecasts',
        method: 'Monthly values per measure. Each measure keeps its own scale; forecasts are drawn dashed.',
        shows: 'How the weather measures change through the months.',
        read: 'Solid lines are recorded values. Dashed lines with diamond markers are forecasts. Check each axis for its unit.',
        meaning: {
            farmer: 'Warm, humid months raise the chance of crop disease. Cooler dry months are usually easier for field work.',
            mao: 'Compare measures together to see periods of heat, humidity or heavy rain that affect crops.',
            it: 'Two measures on dual axes; series with all-empty data are hidden from the legend.',
        },
    },
    'rain-forecast': {
        title: 'Rain Chance This Week', unit: '%', type: 'predicted',
        source: 'Online weather forecast service',
        method: 'Forecast chance of rain for each day of the coming week.',
        shows: 'How likely it is to rain on each day this week.',
        read: 'Taller bars mean a higher chance of rain. 100% means rain is very likely.',
        meaning: {
            farmer: 'Avoid spraying or fertilizing on days with a high chance of rain because it will wash away.',
            mao: 'Use for short-term advisories on field activities.',
            it: 'Daily precipitation probability from the live forecast; forecasts change with each refresh.',
        },
    },
    'temperature-forecast': {
        title: 'Temperature This Week', unit: '°C', type: 'predicted',
        source: 'Online weather forecast service',
        method: 'Forecast daily high and low temperature.',
        shows: 'The expected highest and lowest temperature for each day this week.',
        read: 'The upper line is the daytime high, the lower line the night-time low.',
        meaning: {
            farmer: 'Very hot days stress the crop. Irrigate early in the morning or late afternoon.',
            mao: 'Use for heat advisories when highs stay elevated.',
            it: 'Daily temperature_max and temperature_min from the live forecast.',
        },
    },
    'advisory-status': {
        title: 'Active Advisories by Severity', unit: 'advisories', type: 'count',
        source: 'iClimate advisories (PAGASA and MAO advisories stored in the system)',
        method: 'Number of currently active advisories in each severity level.',
        shows: 'How many advisories are active and how serious they are.',
        read: 'A bigger slice means more advisories of that severity.',
        meaning: {
            farmer: 'More serious advisories (orange/red) need your attention first.',
            mao: 'A large share of high-severity advisories signals widespread risk to communicate to barangays.',
            it: 'COUNT of active rows grouped by severity.',
        },
    },
    'advisories-by-severity': {
        title: 'Advisories by Severity', unit: 'advisories', type: 'count',
        source: 'iClimate advisories table', method: 'Count of advisories per severity level.',
        shows: 'How many advisories exist at each severity level.',
        read: 'A bigger slice means more advisories of that severity.',
        meaning: { farmer: 'Shows how serious recent advisories have been.', mao: 'Indicates how often high-severity warnings have been issued.', it: 'COUNT grouped by severity across all stored advisories.' },
    },
    'risk-levels': {
        title: 'Barangays by Farm Risk Level', unit: 'barangays', type: 'calculated',
        source: 'iClimate barangay risk ratings (heat-map)',
        method: 'Latest calculated rating for each barangay, counted per risk level.',
        shows: 'How many barangays fall into each farm-risk level right now.',
        read: 'Each slice is a risk level. A bigger slice means more barangays at that level.',
        meaning: {
            farmer: 'If your barangay is in a higher-risk group, follow advisories closely.',
            mao: 'Prioritise monitoring and support for barangays in high or severe levels.',
            it: 'One rating per barangay from the Heat Map; barangays without a rating are excluded.',
        },
    },
    'users-by-role': {
        title: 'Users by Role', unit: 'accounts', type: 'count',
        source: 'iClimate user accounts', method: 'Number of registered accounts per role.',
        shows: 'How many accounts belong to each role.',
        read: 'A bigger slice means more accounts with that role.',
        meaning: { farmer: 'Shows who uses iClimate.', mao: 'Shows the size of the farmer base compared with staff accounts.', it: 'COUNT(users) grouped by role.' },
    },
    'records-by-type': {
        title: 'Records by Data Type', unit: 'records', type: 'count',
        source: 'iClimate database tables', method: 'Number of stored rows in each data table.',
        shows: 'How much data is stored for each type of record.',
        read: 'Longer bars mean more stored records.',
        meaning: { farmer: 'Shows how much information the system holds.', mao: 'Indicates coverage of each dataset.', it: 'Row counts per table; a short bar can mean data still needs importing.' },
    },
    'system-activity': {
        title: 'System Activity', unit: 'events', type: 'count',
        source: 'iClimate audit log and analytics run records',
        method: 'Events counted per day and per type. Each line counts one kind of event only; the lines are not added together, and a type with no events in the period is not drawn.',
        shows: 'How many events of each kind happened per day: logins, account changes, administrative actions, data processing and system jobs.',
        read: 'Each line is one type of event. Higher points mean more of that event on that day. Click a name in the legend to hide or show a line.',
        meaning: {
            farmer: 'Shows how active the system is.',
            mao: 'Shows when accounts, data processing and other system work were most active.',
            it: 'Logins, account changes and administrative actions come from the audit log; data processing and system jobs come from analytics run records.',
        },
    },
    'predictions-by-month': {
        title: 'Predictions by Month', unit: 'records', type: 'count',
        source: 'iClimate prediction runs and weather forecasts stored in the system',
        method: 'Number of prediction runs and forecasts generated per month, last 12 months.',
        shows: 'How many predictions the system produced each month.',
        read: 'Taller bars mean more predictions were generated that month. This counts predictions, not their values.',
        meaning: { farmer: 'Shows that forecasts and predictions are being kept up to date.', mao: 'Confirms the prediction tools are running regularly.', it: 'Counts of analytics runs and stored forecasts by created month; gaps can point to a stopped scheduler or queue worker.' },
    },
    'records-by-month': {
        title: 'Records Added by Month', unit: 'records', type: 'count',
        source: 'iClimate database (created dates of stored rows)', method: 'Rows created per month, last 12 months.',
        shows: 'How many records were added to the system each month.',
        read: 'Taller bars mean more records were added that month.',
        meaning: { farmer: 'Shows how often new information is added.', mao: 'Shows how consistently data collection is happening.', it: 'COUNT grouped by created_at month per table.' },
    },
    'notifications-by-type': {
        title: 'Notifications by Type', unit: 'notifications', type: 'count',
        source: 'iClimate notifications', method: 'Notifications sent, counted per type.',
        shows: 'What kinds of notifications the system has sent.',
        read: 'A bigger slice means more notifications of that type.',
        meaning: { farmer: 'Shows which kinds of alerts are sent most.', mao: 'Shows the mix of alerts reaching farmers.', it: 'COUNT grouped by notification type.' },
    },
    // Inline (page-specific) charts
    'organizations': {
        title: 'Farmers by Organization', unit: 'farmers', type: 'count',
        source: 'iClimate farmer profiles', method: 'Number of registered farmers per farmer organization.',
        shows: 'How many farmers belong to each organization.',
        read: 'Longer bars mean more farmers in that organization.',
        meaning: { farmer: 'Shows the size of farmer groups you can join or work with.', mao: 'Shows organization coverage and which groups are largest.', it: 'COUNT of farmer profiles grouped by organization; farmers without one are not shown.' },
    },
    'farmer-yield-trend': {
        title: 'Harvest Yield Trend', unit: 't/ha', type: 'historical',
        source: 'iClimate rice production records', method: 'Recorded yield per period.',
        shows: 'How the harvest yield has changed over time.',
        read: 'Higher points mean a better harvest.',
        meaning: { farmer: 'A rising line means harvests are improving.', mao: 'Shows the yield direction over the periods available.', it: 'Series taken directly from the stored yield records.' },
    },
    'farmer-weather-trend': {
        title: 'Rainfall & Temperature Trend', unit: '', type: 'historical',
        source: 'iClimate climate records', method: 'Rainfall (mm) on the left axis and temperature (°C) on the right axis.',
        shows: 'How rainfall and temperature changed over the period.',
        read: 'Blue is rainfall (left scale). Red is temperature (right scale).',
        meaning: { farmer: 'Lots of rain with warm weather can raise disease risk; dry, hot spells mean you may need to irrigate.', mao: 'Compare rain and heat together to judge stress on crops.', it: 'Two datasets on separate y-axes.' },
    },
    'weather-daily': {
        title: 'Weather Forecast Trend', unit: '', type: 'predicted',
        source: 'Online weather forecast service (stored climate records if the forecast is unavailable)',
        method: 'Daily forecast values for the coming days.',
        shows: 'How this weather measure is expected to change over the coming days.',
        read: 'Each point is one day. Higher points mean a higher value of the measure.',
        meaning: {
            farmer: 'Use it to pick the best days for spraying, harvesting or irrigating.',
            mao: 'Use it to time weather advisories for farmers.',
            it: 'Read from the daily series of the live forecast; falls back to stored climate records when the forecast is unavailable.',
        },
    },
};

const ALIASES = {
    farmersByOrganizationChart: 'organizations', farmersByOrganizationChartCompact: 'organizations', adminFarmersByOrganizationChart: 'organizations',
    itUsersByRoleChart: 'users-by-role', itRecordsByTypeChart: 'records-by-type', itSystemActivityTrendChart: 'system-activity',
    riceYieldTrendChart: 'yield-trend', farmerYieldTrendChart: 'farmer-yield-trend', farmerWeatherTrendChart: 'farmer-weather-trend',
    maoRiskDonutChart: 'risk-levels', maoRainTempChart: 'weather-trend',
    rainfallChart: 'weather-daily', temperatureChart: 'weather-daily', humidityChart: 'weather-daily', windSpeedChart: 'weather-daily',
};

/* -------------------------------------------------------------------- helpers */
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c]));
const num = (v) => (v === null || v === undefined || v === '' ? null : (Number.isFinite(Number(v)) ? Number(v) : null));
const fmt = (v) => Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 });
const withUnit = (v, unit) => `${fmt(v)}${unit ? ` ${unit}` : ''}`;
const plainLabel = (l) => (Array.isArray(l) ? l.join(' ') : String(l ?? ''));
const isForecastSeries = (ds) => ds.kind === 'predicted' || /forecast|predict/i.test(String(ds.label ?? ''));

export function currentRole() {
    const raw = document.body?.dataset.userRole || '';
    return ROLE_BY_LABEL[raw] || (['farmer', 'mao', 'it'].includes(raw) ? raw : 'farmer');
}

function chartKey(chart) {
    const canvas = chart.canvas;
    const card = canvas.closest('[data-chart-card]');
    const id = canvas.dataset.chartId || card?.dataset.chartCard || canvas.id || '';
    return ALIASES[id] || id;
}

function entryFor(chart) {
    const info = chart.options?.plugins?.icInfo || {};
    const reg = REGISTRY[chartKey(chart)] || {};
    const ds = chart.canvas.dataset;
    const merged = { ...reg, ...info };
    if (ds.chartSource) merged.source = ds.chartSource;
    if (ds.chartType) merged.type = ds.chartType;
    if (ds.chartUnit) merged.unit = ds.chartUnit;
    if (ds.chartPeriod) merged.period = ds.chartPeriod;
    if (ds.chartMethod) merged.method = ds.chartMethod;
    if (ds.chartNote) merged.note = ds.chartNote;
    if (ds.chartTitle) merged.title = ds.chartTitle;
    return merged;
}

function findHeading(canvas) {
    let node = canvas.parentElement;
    for (let depth = 0; node && depth < 5; depth += 1, node = node.parentElement) {
        const h = node.querySelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6, :scope > * > h1, :scope > * > h2, :scope > * > h3, :scope > * > h4, :scope > * > h5, :scope > * > h6, :scope > .chart-title, :scope > * > .chart-title');
        if (h && !h.contains(canvas)) return h;
        if (node.matches('.card, article, section, .chart-box, [data-chart-card]')) break;
    }
    return null;
}

function chartTitle(chart) {
    const entry = entryFor(chart);
    const heading = findHeading(chart.canvas);
    const headingText = heading?.childNodes.length
        ? [...heading.childNodes].filter((n) => !(n.nodeType === 1 && n.classList?.contains('ic-info-btn'))).map((n) => n.textContent).join('').trim()
        : '';
    const explicit = chart.options?.plugins?.icInfo?.title || chart.canvas.dataset.chartTitle;
    // Prefer the card's own heading (it can differ per role/page) over the registry default.
    return explicit || headingText || entry.title || chart.canvas.getAttribute('aria-label') || 'Chart';
}

// Which unit applies to a dataset: explicit > registry > "Label (unit)" > axis title.
function unitFor(chart, dataset) {
    const entry = entryFor(chart);
    const m = /\(([^)]+)\)\s*$/.exec(String(dataset?.label ?? ''));
    if (m) return m[1];
    if (chart.options?.plugins?.icInfo?.unit) return chart.options.plugins.icInfo.unit;
    if (entry.unit) return entry.unit;
    const scaleId = dataset?.yAxisID || 'y';
    const titleText = chart.options?.scales?.[scaleId]?.title?.text ?? chart.options?.scales?.x?.title?.text;
    return titleText && titleText.length <= 12 ? titleText : '';
}

/* ------------------------------------------------- data-driven facts (no invention) */
function seriesPoints(chart, datasetIndex) {
    const ds = chart.data.datasets[datasetIndex];
    const labels = chart.data.labels || [];
    return (ds.data || []).map((raw, i) => {
        const value = num(raw && typeof raw === 'object' ? (raw.y ?? raw.x) : raw);
        return { label: plainLabel(labels[i] ?? (raw && raw.x) ?? i + 1), value };
    }).filter((p) => p.value !== null);
}

function describeSeries(chart, datasetIndex, isCategoryShare) {
    const ds = chart.data.datasets[datasetIndex];
    const unit = unitFor(chart, ds);
    const points = seriesPoints(chart, datasetIndex);
    const name = chart.config.type === 'doughnut' || chart.config.type === 'pie' ? '' : String(ds.label ?? '').replace(/\s*\([^)]*\)\s*$/, '');
    if (points.length < 2) return null;

    const max = points.reduce((a, b) => (b.value > a.value ? b : a));
    const min = points.reduce((a, b) => (b.value < a.value ? b : a));

    if (isCategoryShare) {
        const total = points.reduce((s, p) => s + p.value, 0);
        if (total <= 0) return null;
        const pct = Math.round((max.value / total) * 100);
        return `${max.label} is the largest group with ${fmt(max.value)}${unit ? ` ${unit}` : ''} (${pct}% of ${fmt(total)}).`;
    }
    if (max.value === min.value) return `${name || 'The values'} stayed the same (${withUnit(max.value, unit)}) across all ${points.length} points.`;

    const prefix = name ? `${name}: ` : '';
    let text = `${prefix}highest at ${max.label} (${withUnit(max.value, unit)}) and lowest at ${min.label} (${withUnit(min.value, unit)}).`;

    // Direction only for ordered (line) data with enough points, and only if the change is meaningful.
    if (chart.config.type === 'line' && points.length >= 3) {
        const first = points[0]; const last = points[points.length - 1];
        const range = max.value - min.value;
        const change = last.value - first.value;
        if (Math.abs(change) >= range * 0.15 && Math.abs(change) > 0) {
            text += ` From ${first.label} to ${last.label} the values went ${change > 0 ? 'up' : 'down'} by ${withUnit(Math.abs(change), unit)}.`;
        }
    }
    return text;
}

function noticeFor(chart) {
    const type = chart.config.type;
    const share = type === 'doughnut' || type === 'pie';
    const notes = [];
    chart.data.datasets.forEach((ds, i) => {
        if (chart.getDatasetMeta(i)?.hidden) return;
        const s = describeSeries(chart, i, share);
        if (s) notes.push(isForecastSeries(ds) ? `${s.replace(/^([^:]+): /, '$1 (forecast): ')} (this part is a forecast, not a record)` : s);
    });
    return notes;
}

function periodFor(chart) {
    const entry = entryFor(chart);
    if (entry.period) return entry.period;
    const cfgSub = chart.canvas.closest('[data-chart-card]')?.querySelector('.dash-chart-sub')?.textContent?.trim();
    const labels = (chart.data.labels || []).map(plainLabel).filter(Boolean);
    const year = cfgSub && /\b(19|20)\d{2}\b/.exec(cfgSub)?.[0];
    const span = labels.length > 1 ? `${labels[0]} to ${labels[labels.length - 1]}` : labels[0] || '';
    if (year && labels.length && /^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/.test(labels[0])) return `${year} (${span})`;
    if (cfgSub && /last \d+ (days|months)|this week|current/i.test(cfgSub)) return `${cfgSub.match(/last \d+ (days|months)|this week|current/i)[0]} (${span})`;
    return span ? `${span} (as shown on the chart)` : 'Not stated for this chart';
}

function dataTypeFor(chart) {
    const entry = entryFor(chart);
    const hasForecast = chart.data.datasets.some(isForecastSeries);
    const hasActual = chart.data.datasets.some((d) => !isForecastSeries(d));
    let key = entry.type || (hasForecast ? (hasActual ? 'mixed' : 'predicted') : 'historical');
    if (key === 'mixed' && !hasForecast) key = 'historical'; // no forecast series actually present
    return { key, text: DATA_TYPES[key] || key };
}

function recordCount(chart) {
    return chart.data.datasets.reduce((n, ds) => n + (ds.data || []).filter((v) => num(v && typeof v === 'object' ? (v.y ?? v.x) : v) !== null).length, 0);
}

/* ------------------------------------------------------------------ the modal */
let modal; let lastFocus; let liveChart;

function buildModal() {
    modal = document.createElement('div');
    modal.className = 'ic-chartinfo';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="ic-chartinfo__backdrop" data-close></div>
        <div class="ic-chartinfo__panel" role="dialog" aria-modal="true" aria-labelledby="icChartInfoTitle" tabindex="-1">
            <header class="ic-chartinfo__head">
                <div>
                    <span class="ic-chartinfo__badge" id="icChartInfoBadge"></span>
                    <h2 id="icChartInfoTitle"></h2>
                </div>
                <button type="button" class="ic-chartinfo__x" data-close aria-label="Close explanation">&times;</button>
            </header>
            <div class="ic-chartinfo__body" id="icChartInfoBody"></div>
            <footer class="ic-chartinfo__foot"><button type="button" class="ic-chartinfo__close" data-close>Close</button></footer>
        </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) closeInfo(); });
    document.addEventListener('keydown', (e) => {
        if (modal.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); closeInfo(); return; }
        if (e.key === 'Tab') { // simple focus trap
            const f = [...modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])')].filter((n) => !n.disabled && n.offsetParent !== null);
            if (!f.length) return;
            const first = f[0]; const last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
}

const row = (label, html) => `<div class="ic-chartinfo__row"><dt>${escapeHtml(label)}</dt><dd>${html}</dd></div>`;
const section = (heading, html, extra = '') => (html ? `<section class="ic-chartinfo__sec${extra}"><h3>${escapeHtml(heading)}</h3>${html}</section>` : '');

function contentFor(chart, picked) {
    const role = currentRole();
    const entry = entryFor(chart);
    const dt = dataTypeFor(chart);
    const points = recordCount(chart);
    const notes = noticeFor(chart);

    // Units: distinct across the visible datasets.
    const units = [...new Set(chart.data.datasets.map((d) => unitFor(chart, d)).filter(Boolean))];
    const unitText = units.length ? units.join(', ') : 'No unit (values are plain numbers)';

    const generic = {
        shows: 'The values plotted on this chart, drawn from the data stored in iClimate.',
        read: 'Taller bars, larger slices or higher points mean bigger values. Hover or tap a point to see its exact value.',
    };
    const shows = pick(entry.shows, role) || generic.shows;
    const read = pick(entry.read, role) || generic.read;
    const meaning = pick(entry.meaning, role);

    const seriesList = chart.data.datasets.length > 1 || (chart.data.datasets[0] && chart.config.type !== 'doughnut')
        ? chart.data.datasets.filter((d) => (d.data || []).some((v) => v !== null && v !== undefined)).map((d) => {
            const tag = isForecastSeries(d) ? 'forecast' : 'recorded';
            return `<li><strong>${escapeHtml(String(d.label ?? 'Series'))}</strong> <span class="ic-chartinfo__tag is-${tag}">${tag}</span></li>`;
        }).join('')
        : '';

    let selected = '';
    if (picked && picked.value !== null) {
        selected = `<p class="ic-chartinfo__picked">You selected: <strong>${escapeHtml(picked.label)}</strong> — ${escapeHtml(picked.series ? `${picked.series}: ` : '')}<strong>${escapeHtml(withUnit(picked.value, picked.unit))}</strong></p>`;
    }

    const noticeHtml = notes.length
        ? `<ul>${notes.map((n) => `<li>${escapeHtml(n)}</li>`).join('')}</ul>`
        : '<p class="ic-chartinfo__muted">There are not enough data points yet to describe a pattern, so nothing is claimed here. Come back once more records are available.</p>';

    let noteText = entry.note || '';
    if (dt.key === 'predicted' || dt.key === 'mixed') noteText = `${noteText} Predicted values are estimates from a forecast or model — they can change and are not measured results.`.trim();
    else if (dt.key === 'historical') noteText = `${noteText} This shows past records; it does not predict what will happen next.`.trim();
    else if (dt.key === 'calculated') noteText = `${noteText} These values are calculated from stored records, not measured directly.`.trim();
    else if (dt.key === 'count') noteText = `${noteText} This counts records stored in iClimate; it does not measure quality or amounts.`.trim();

    const facts = [
        row('Unit', escapeHtml(unitText)),
        row('Period', escapeHtml(periodFor(chart))),
        row('Source', escapeHtml(entry.source || 'iClimate stored records (source not specified for this chart)')),
        row('Data type', escapeHtml(dt.text)),
    ];
    if (role === 'it') {
        facts.push(row('Data points', `${points} plotted value${points === 1 ? '' : 's'} across ${chart.data.datasets.length} series`));
        facts.push(row('Chart type', escapeHtml(chart.config.type)));
        facts.push(row('Method', escapeHtml(entry.method || 'Values are plotted exactly as stored; no smoothing of data (line curves are visual only).')));
        facts.push(row('Chart id', escapeHtml(chartKey(chart) || '(none)')));
    } else if (role === 'mao' && entry.method) {
        facts.push(row('How it is calculated', escapeHtml(entry.method)));
    }

    return `
        ${selected}
        ${section('What does this show?', `<p>${escapeHtml(shows)}</p>`)}
        ${section('How to read it', `<p>${escapeHtml(read)}</p>${seriesList ? `<ul class="ic-chartinfo__series">${seriesList}</ul>` : ''}`)}
        ${section(role === 'farmer' ? 'What does it mean for my farm?' : 'What does it mean?', meaning ? `<p>${escapeHtml(meaning)}</p>` : '')}
        ${section('What should I notice?', noticeHtml, ' ic-chartinfo__sec--notice')}
        <dl class="ic-chartinfo__facts">${facts.join('')}</dl>
        ${noteText ? `<p class="ic-chartinfo__note"><strong>Important:</strong> ${escapeHtml(noteText)}</p>` : ''}`;
}

function pickedPoint(chart, event) {
    if (!event) return null;
    const els = chart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, false);
    if (!els.length) return null;
    const { datasetIndex, index } = els[0];
    const ds = chart.data.datasets[datasetIndex];
    const raw = ds.data[index];
    const value = num(raw && typeof raw === 'object' ? (raw.y ?? raw.x) : raw);
    const isShare = chart.config.type === 'doughnut' || chart.config.type === 'pie';
    return { label: plainLabel((chart.data.labels || [])[index] ?? index + 1), value, unit: unitFor(chart, ds), series: isShare ? '' : String(ds.label ?? '').replace(/\s*\([^)]*\)\s*$/, '') };
}

export function openInfo(chart, event, trigger) {
    if (!modal) buildModal();
    lastFocus = trigger || document.activeElement;
    liveChart = chart;
    modal.querySelector('#icChartInfoTitle').textContent = chartTitle(chart);
    const dt = dataTypeFor(chart);
    const badge = modal.querySelector('#icChartInfoBadge');
    badge.textContent = dt.key === 'mixed' ? 'Historical + Predicted' : dt.key.charAt(0).toUpperCase() + dt.key.slice(1);
    badge.dataset.kind = dt.key;
    modal.querySelector('#icChartInfoBody').innerHTML = contentFor(chart, pickedPoint(chart, event));
    modal.hidden = false;
    document.documentElement.classList.add('ic-chartinfo-open');
    requestAnimationFrame(() => { modal.classList.add('is-open'); modal.querySelector('.ic-chartinfo__panel').focus(); });
}

function closeInfo() {
    if (!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    document.documentElement.classList.remove('ic-chartinfo-open');
    liveChart = null;
    if (lastFocus && document.contains(lastFocus)) lastFocus.focus();
}

/* ------------------------------------------------------------- Chart.js plugin */
function attachLearnMore(chart) {
    const canvas = chart.canvas;
    if (canvas.dataset.icInfoBound === 'true') return;
    canvas.dataset.icInfoBound = 'true';

    canvas.classList.add('ic-chart-clickable');
    if (!canvas.hasAttribute('tabindex')) canvas.tabIndex = 0;
    canvas.setAttribute('aria-haspopup', 'dialog');
    const title = chartTitle(chart);
    canvas.setAttribute('aria-label', `${title}. Press Enter or click for an explanation of this chart.`);
    if (!canvas.getAttribute('role')) canvas.setAttribute('role', 'img');

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ic-info-btn';
    btn.setAttribute('aria-haspopup', 'dialog');
    const iconOnly = canvas.dataset.chartInfoStyle === 'icon';
    btn.classList.toggle('is-icon', iconOnly);
    btn.innerHTML = iconOnly
        ? '<span aria-hidden="true">ⓘ</span><span class="visually-hidden">About this chart: ' + escapeHtml(title) + '</span>'
        : '<span aria-hidden="true">ⓘ</span> Learn More<span class="visually-hidden"> about ' + escapeHtml(title) + '</span>';
    btn.addEventListener('click', (e) => { e.stopPropagation(); openInfo(chart, null, btn); });

    const heading = findHeading(canvas);
    if (heading) heading.appendChild(btn);
    else canvas.parentElement.insertBefore(btn, canvas);
    chart.$icInfoBtn = btn;

    canvas.addEventListener('click', (e) => {
        // Legend clicks keep their normal show/hide behaviour.
        const legend = chart.legend;
        if (legend && legend.options?.display !== false) {
            const r = canvas.getBoundingClientRect();
            const x = e.clientX - r.left; const y = e.clientY - r.top;
            if (x >= legend.left && x <= legend.right && y >= legend.top && y <= legend.bottom) return;
        }
        openInfo(chart, e, canvas);
    });
    canvas.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openInfo(chart, null, canvas); }
    });
}

const explainerPlugin = {
    id: 'icInfoExplainer',
    afterInit(chart) { try { attachLearnMore(chart); } catch (err) { console.error('iClimate chart explainer failed', err); } },
    destroy(chart) { chart.$icInfoBtn?.remove(); chart.canvas.dataset.icInfoBound = 'false'; },
};

/** Tooltip: title = date/category, body = "Series: value unit". Chart-specific callbacks still win. */
function installTooltipDefaults(Chart) {
    const cb = Chart.defaults.plugins.tooltip.callbacks;
    cb.title = (items) => {
        if (!items.length) return '';
        const l = items[0].chart.data.labels?.[items[0].dataIndex] ?? items[0].label;
        return plainLabel(l);
    };
    cb.label = (ctx) => {
        const isShare = ctx.chart.config.type === 'doughnut' || ctx.chart.config.type === 'pie';
        const raw = isShare ? ctx.parsed : (ctx.chart.options.indexAxis === 'y' ? ctx.parsed.x : ctx.parsed.y);
        if (raw === null || raw === undefined) return `${ctx.dataset.label ?? ''}: no data`;
        const unit = unitFor(ctx.chart, ctx.dataset);
        const label = isShare ? plainLabel(ctx.label) : String(ctx.dataset.label ?? '').replace(/\s*\([^)]*\)\s*$/, '');
        const suffix = isShare ? ` (${Math.round((raw / (ctx.dataset.data.reduce((s, v) => s + (num(v) || 0), 0) || 1)) * 100)}%)` : '';
        return `${label}: ${withUnit(raw, unit)}${suffix}`;
    };
}

export function registerChartExplainer(Chart) {
    Chart.register(explainerPlugin);
    installTooltipDefaults(Chart);
    // Click on chart area must not be swallowed by hover-only interaction settings.
    Chart.defaults.events = ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'];
}
