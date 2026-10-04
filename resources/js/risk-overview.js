// Municipal Risk Overview (partials/risk-overview.blade.php) and its category pop-ups. Called by
// barangay-risk-map.js with the SAME counts and entries that colour the map, so the overview, the map legend and the
// pop-ups can never show different numbers. Nothing here calculates a risk; it only counts and describes ratings.

import { open as openModal, escapeHtml, icon } from './info-modal';

export const CATEGORIES = {
    monitored: { title: 'Barangays Monitored', levels: null, color: '#0f5132', icon: 'map', tone: '' },
    low: { title: 'Low Risk', levels: ['Low'], color: '#2e7d32', icon: 'check', tone: '' },
    moderate: { title: 'Moderate Risk', levels: ['Moderate'], color: '#c98a12', icon: 'alert', tone: 'watch' },
    high: { title: 'High / Severe Risk', levels: ['High', 'Severe'], color: '#c4471f', icon: 'danger', tone: 'danger' },
    nodata: { title: 'No Data Yet', levels: ['No Data'], color: '#8a939b', icon: 'clock', tone: 'info' },
};

const LEVEL_COLORS = { Low: '#2e7d32', Moderate: '#c98a12', High: '#c4471f', Severe: '#8b0000', 'No Data': '#8a939b' };

// What each category means and what to do, worded for each audience. The score bands are the ones
// AgriculturalRiskScorer uses (0.45 Moderate, 0.68 High, 0.85 Severe).
const COPY = {
    staff: {
        monitored: {
            meaning: 'These are the official barangays of Lian, Batangas included in the municipal climate and farm-risk monitoring dataset. Each one is rated from the same live weather, expected rice yield and barangay exposure information, and the same ratings colour the Lian Municipality Risk Map.',
            action: 'Use the list to see which barangays need a closer look, then open the risk map below to inspect a location.',
        },
        low: {
            meaning: 'Weather, expected yield and barangay exposure are all within normal ranges for rice farming. The combined risk score is below 0.45 on a 0–1 scale, so no factor is currently expected to harm the crop.',
            action: 'Continue routine monitoring and regular advisories. No special field validation is needed for these barangays right now.',
        },
        moderate: {
            meaning: 'At least one factor, most often rainfall or heat, is outside the comfortable range for rice, but not enough to threaten the crop on its own. The combined risk score is between 0.45 and 0.67.',
            action: 'Watch the daily rainfall and heat updates, remind farmers in these barangays to check drainage and irrigation, and keep current advisories active.',
        },
        high: {
            meaning: 'High (score 0.68–0.84): conditions are likely to reduce yield or damage crops unless farmers act. Severe (0.85 or more): a serious threat to standing crops. Both need early attention from the MAO.',
            action: 'Prioritise field validation in these barangays, issue a targeted advisory, and coordinate with the MDRRMO if conditions worsen.',
            empty: 'No barangays are currently classified under High / Severe Risk.',
        },
        nodata: {
            meaning: 'These barangays are monitored but do not have a rating yet, usually because the background update is still running. They are never counted as Low.',
            action: 'Wait for the next scheduled update, or use Refresh Data. If a barangay stays without a rating, report it to IT Personnel.',
        },
    },
    farmer: {
        monitored: {
            meaning: 'These are the 19 barangays of Lian, Batangas. Each one gets a farm-risk rating from the weather and recorded rice harvests.',
            action: 'Find your barangay in the list, then look at the map below for the area around your farm.',
        },
        low: {
            meaning: 'Conditions are generally okay for rice farming.',
            action: 'Continue normal farm activities and monitor weather updates.',
        },
        moderate: {
            meaning: 'Some weather conditions may affect the rice field.',
            action: 'Keep monitoring the weather and your field.',
        },
        high: {
            meaning: 'Weather may strongly affect rice farming.',
            action: 'Prepare to take action and follow official advisories.',
            empty: 'No barangays are currently classified under High / Severe Risk.',
        },
        nodata: {
            meaning: 'The farm risk for these barangays is not available yet.',
            action: 'Check again after the next update.',
        },
    },
};

const CIRCUMFERENCE = 2 * Math.PI * 46;

export const categoryOf = (level) => ({ Low: 'low', Moderate: 'moderate', High: 'high', Severe: 'high' }[level] || 'nodata');

/** Fills the overview from one counts object { Low, Moderate, High, Severe, 'No Data' } and the monitored total. */
export function renderOverview(root, { counts, monitored, forLine }) {
    if (!root) return;
    const values = {
        monitored,
        low: counts.Low,
        moderate: counts.Moderate,
        high: counts.High + counts.Severe,
        nodata: counts['No Data'],
    };
    Object.entries(values).forEach(([key, value]) => {
        root.querySelectorAll(`[data-risk-overview-count="${key}"]`).forEach((el) => { el.textContent = value; });
        const bar = root.querySelector(`[data-risk-overview-bar="${key}"]`);
        if (bar) bar.style.width = monitored ? `${(value / monitored) * 100}%` : '0';
        const item = root.querySelector(`.risk-overview-item[data-risk-category="${key}"]`);
        if (item && key !== 'monitored') {
            const share = monitored ? Math.round((value / monitored) * 100) : 0;
            item.setAttribute('aria-label', `${CATEGORIES[key].title}: ${value} of ${monitored} barangays (${share}%). Open meaning and barangays.`);
        }
    });
    root.querySelector('[data-risk-overview-total]').textContent = monitored;
    root.querySelector('[data-risk-overview-nodata]').hidden = values.nodata === 0;
    root.querySelector('[data-risk-overview-for]').textContent = forLine;
    root.querySelector('[data-risk-overview-body]').setAttribute('aria-busy', 'false');

    // Text version of the chart, and the consistency check: the categories always add up to the monitored total.
    const parts = [`${values.low} Low`, `${values.moderate} Moderate`, `${values.high} High / Severe`];
    if (values.nodata) parts.push(`${values.nodata} No Data`);
    const sum = values.low + values.moderate + values.high + values.nodata;
    root.querySelector('[data-risk-overview-check]').textContent = sum === monitored
        ? `${parts.join(' + ')} = ${monitored} barangays monitored. The same ratings colour the map below.`
        : `Counts do not add up (${parts.join(' + ')} ≠ ${monitored}). Please refresh the data.`;

    // Donut: one arc per non-empty category, in risk order, with a small gap between arcs.
    const svg = root.querySelector('[data-risk-donut]');
    svg.querySelectorAll('.risk-donut-seg').forEach((seg) => seg.remove());
    const segments = ['low', 'moderate', 'high', 'nodata'].filter((key) => values[key] > 0);
    const gap = segments.length > 1 ? 1.6 : 0;
    let offset = 0;
    segments.forEach((key) => {
        const length = (values[key] / Math.max(monitored, 1)) * CIRCUMFERENCE;
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', '60');
        circle.setAttribute('cy', '60');
        circle.setAttribute('r', '46');
        circle.setAttribute('class', 'risk-donut-seg');
        circle.setAttribute('stroke', CATEGORIES[key].color);
        circle.setAttribute('stroke-dasharray', `${Math.max(length - gap, 0.5)} ${CIRCUMFERENCE}`);
        circle.setAttribute('stroke-dashoffset', String(-offset));
        circle.setAttribute('transform', 'rotate(-90 60 60)');
        circle.dataset.riskCategory = key;
        const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
        title.textContent = `${CATEGORIES[key].title}: ${values[key]} of ${monitored} barangays. Click for details.`;
        circle.appendChild(title);
        svg.appendChild(circle);
        offset += length;
    });
    svg.querySelector('title').textContent = `Barangays by farm risk level: ${parts.join(', ')}, out of ${monitored}.`;
}

const levelBadge = (level, label) => `<span class="ic-risk-level" style="--risk-color:${LEVEL_COLORS[level] || LEVEL_COLORS['No Data']}">${escapeHtml(label || (level === 'No Data' ? 'No data' : level))}</span>`;

const average = (values) => {
    const list = values.filter((value) => value != null && !Number.isNaN(Number(value))).map(Number);
    return list.length ? list.reduce((a, b) => a + b, 0) / list.length : null;
};

/**
 * One category's pop-up.
 * ctx: { audience, names (rendered barangays, map order), entryFor(name), levelOf(entry), displayName(name),
 *        weatherFor(entry) -> { line, notice, fresh }, updatedFor(entry), month (forecast month or null),
 *        generatedAt (text), sourceNote }
 */
export function openRiskCategory(category, trigger, ctx) {
    const meta = CATEGORIES[category];
    if (!meta) return;
    const copy = (COPY[ctx.audience] || COPY.staff)[category];
    const monitored = ctx.names.length;
    const rows = ctx.names
        .map((name) => ({ name, entry: ctx.entryFor(name) }))
        .map((row) => ({ ...row, level: ctx.levelOf(row.entry) }))
        .filter((row) => !meta.levels || meta.levels.includes(row.level));
    const count = rows.length;
    const share = monitored ? Math.round((count / monitored) * 100) : 0;
    const month = ctx.month;
    const staff = ctx.audience !== 'farmer';

    const countBlock = `<div class="ic-risk-count" style="--risk-color:${meta.color}">
            <strong>${count}</strong><span>${count === 1 ? 'Barangay' : 'Barangays'}${category === 'monitored' ? ' in Lian, Batangas' : ` of ${monitored} monitored`}</span>
            ${category === 'monitored' ? '' : `<span class="ic-risk-share">${share}% of monitored barangays</span>`}
        </div>`;

    let list;
    if (!count) {
        list = `<div class="fad-m-note is-good" role="status"><strong>${escapeHtml(copy.empty || `No barangays are currently classified under ${meta.title}.`)}</strong>${category === 'high' ? '<span>This is a good sign. The category is explained above so it is easy to recognise if it appears later.</span>' : ''}</div>`;
    } else {
        const head = ['Barangay', 'Risk', ...(staff && !month ? ['Score'] : []), month ? 'Forecast Period' : 'Current Weather', ...(month ? [] : ['Updated'])];
        list = `<div class="ic-risk-table-wrap"><table class="ic-risk-table"><caption class="ic-sr">${escapeHtml(meta.title)} barangays</caption>
            <thead><tr>${head.map((h) => `<th scope="col">${h}</th>`).join('')}</tr></thead>
            <tbody>${rows.map(({ name, entry, level }) => {
                const weather = ctx.weatherFor(entry);
                const score = entry?.risk_score != null ? Number(entry.risk_score).toFixed(2) : '—';
                return `<tr>
                    <td>${escapeHtml(ctx.displayName(name))}</td>
                    <td data-label="Risk">${levelBadge(level, month && level === 'No Data' ? 'Insufficient data' : null)}</td>
                    ${staff && !month ? `<td data-label="Score">${score}</td>` : ''}
                    <td data-label="${month ? 'Forecast Period' : 'Weather'}">${escapeHtml(month ? month.label : (weather.line || weather.notice))}</td>
                    ${month ? '' : `<td data-label="Updated">${escapeHtml(ctx.updatedFor(entry) || '—')}</td>`}
                </tr>`;
            }).join('')}</tbody></table></div>`;
    }

    // Current Conditions: the live readings of the barangays in this category, or the Lian forecast for the month.
    let conditions;
    if (month) {
        const w = month.weather || {};
        const facts = [
            ['Lian Forecast Rainfall', w.rainfall_mm != null ? `About ${w.rainfall_mm} mm for the month` : null],
            ['Lian Forecast Temperature', w.temperature_c != null ? `${w.temperature_c}°C` : null],
            ['Lian Forecast Humidity', w.humidity_percent != null ? `${w.humidity_percent}%` : null],
            ['Forecast Confidence', month.confidence_level],
            ['Main Concern', month.summary?.main_concern],
        ].filter(([, value]) => value != null && value !== '');
        conditions = facts.length
            ? `<dl class="fad-m-details">${facts.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl><p class="fad-m-small">Forecast values apply to Lian as a whole; barangay ratings differ by local farm and exposure factors.</p>`
            : '<p class="fad-m-empty">Forecast weather values are not available for this month.</p>';
    } else {
        const fresh = rows.map((row) => row.entry?.weather).filter((w) => w?.status === 'fresh');
        if (!fresh.length) {
            conditions = `<p class="fad-m-empty">${count ? 'Live weather for these barangays is refreshing. The ratings shown are the last verified results.' : 'No barangays to describe in this category right now.'}</p>`;
        } else {
            const temp = average(fresh.map((w) => w.temperature_c));
            const humidity = average(fresh.map((w) => w.humidity_percent));
            const rain = average(fresh.map((w) => w.rainfall_24h_mm));
            const statuses = [...new Set(fresh.map((w) => w.rainfall_status).filter(Boolean))];
            const cells = [
                ['Temperature', temp != null ? `${temp.toFixed(1)}°C` : null, 'average of live readings'],
                ['Humidity', humidity != null ? `${Math.round(humidity)}%` : null, 'average of live readings'],
                ['Rainfall (24 h)', rain != null ? `${rain.toFixed(1)} mm` : null, statuses.join(', ') || 'average of live readings'],
                ['Live Readings', `${fresh.length} of ${count}`, 'barangays with current weather'],
            ].filter(([, value]) => value != null);
            conditions = `<dl class="fad-m-weather">${cells.map(([label, value, sub]) => `<div><dt>${escapeHtml(label)}</dt><dd><strong>${escapeHtml(value)}</strong><span>${escapeHtml(sub)}</span></dd></div>`).join('')}</dl>`;
        }
    }

    const body = `${countBlock}
        <div class="ic-risk-grid">
            <div class="ic-risk-side">
                <section class="fad-m-section"><h3>${category === 'monitored' ? 'Purpose' : `What does ${escapeHtml(meta.title)} mean?`}</h3><p class="fad-m-body">${escapeHtml(copy.meaning)}</p></section>
                <section class="fad-m-section"><h3>${category === 'monitored' ? 'Barangay List' : 'Current Barangays'}</h3>${list}</section>
            </div>
            <div class="ic-risk-side">
                <section class="fad-m-section"><h3>${month ? 'Forecast Conditions' : 'Current Conditions'}</h3>${conditions}</section>
                <section class="fad-m-summary"><h3>${staff ? 'Recommended MAO Action' : 'What You Can Do'}</h3><p>${escapeHtml(copy.action)}</p></section>
            </div>
        </div>
        ${staff && ctx.sourceNote ? `<p class="fad-m-small">About these ratings: ${escapeHtml(ctx.sourceNote)}</p>` : ''}`;

    openModal({
        title: meta.title,
        kicker: month ? `Municipal Risk Overview · ${month.label}` : 'Municipal Risk Overview',
        description: month ? `Forecast farm-risk ratings for Lian's barangays, ${month.label}.` : `Current farm-risk ratings for Lian's ${monitored} barangays.`,
        icon: meta.icon,
        tone: meta.tone,
        size: 'wide',
        trigger,
        body,
        note: `${icon('clock')} Data updated: ${escapeHtml(ctx.generatedAt || 'not available')}`,
        actions: ctx.mapAnchor ? `<button type="button" class="fad-btn is-quiet" data-ic-close data-risk-goto-map>View on the risk map</button>` : '',
    });
}
