// Barangay AI Insight pop-up: opened from a barangay row of the Municipal Risk Overview lists (Barangays Monitored
// and the risk categories) on the Heat Map and the MAO dashboard. The values on the cards are the same verified
// entry the list shows (shown at once from the list, then from /heat-map/insight/{barangay}); the AI only explains
// them in two short parts. Nothing here calculates a risk or a weather value.

import { open as openModal, update as updateModal, close as closeModal, escapeHtml, icon } from './info-modal';
import { LEVEL_COLORS } from './risk-overview';

const LEVELS = ['Low', 'Moderate', 'High', 'Severe'];
const LANGUAGE_NAMES = { en: 'English', fil: 'Filipino' };
const UNAVAILABLE = 'AI insight is temporarily unavailable.';

// The pop-up open now: { name, ctx, language, signature, request }. Null when closed or showing something else.
let state = null;

const numberText = (value) => (value == null || value === '' || Number.isNaN(Number(value)) ? null : String(Number(value)));

/** The list entry (GET /api/lian/risk-map) in the endpoint's `data` shape, for the instant first view. */
function dataFromEntry(entry, weatherParts) {
    const weather = entry?.weather || {};
    const level = LEVELS.includes(entry?.risk_level) ? entry.risk_level : null;
    const status = weatherParts.status;
    const hasWeather = status !== 'unavailable';
    return {
        risk_level: level,
        risk_status: !level ? 'unavailable' : entry?.last_known_good ? 'refreshing' : 'current',
        risk_calculated_at: level ? entry?.risk_calculated_at || null : null,
        weather_status: !hasWeather ? 'unavailable' : status === 'stale' ? 'delayed' : 'current',
        temperature_c: hasWeather ? weather.temperature_c : null,
        humidity_percent: hasWeather ? weather.humidity_percent : null,
        rainfall_24h_mm: hasWeather ? weather.rainfall_24h_mm : null,
        condition: hasWeather ? weather.condition || null : null,
        weather_updated_at: hasWeather ? weather.fetched_at || null : null,
        weather_source: weather.provider || 'Open-Meteo',
        risk_source: 'iClimate',
    };
}

const signatureOf = (data) => `${data.weather_updated_at || '-'}|${data.risk_calculated_at || '-'}`;

const noticesFor = (data) => [
    data.weather_status === 'delayed' ? 'Based on the last available weather update.' : null,
    data.weather_status === 'unavailable' ? 'Latest weather is temporarily unavailable.' : null,
    data.risk_status !== 'current' ? 'Risk is being updated.' : null,
].filter(Boolean);

const isCurrent = (data) => data.weather_status === 'current' && data.risk_status === 'current';

function cardsMarkup(data) {
    const level = data.risk_level;
    const sentence = (text) => (text ? text.charAt(0).toUpperCase() + text.slice(1).toLowerCase() : '');
    const temperature = numberText(data.temperature_c);
    const humidity = numberText(data.humidity_percent);
    const rain = numberText(data.rainfall_24h_mm);
    const cards = [
        // No verified rating: a dash, never a guessed level (the notice below says it is being refreshed).
        ['Risk', level ? level.toUpperCase() : '—', null, level ? LEVEL_COLORS[level] : LEVEL_COLORS['No Data']],
        ['Temperature', temperature ? `${temperature}°C` : '—', sentence(data.condition) || null],
        ['Humidity', humidity ? `${humidity}%` : '—'],
        ['Rain next 24 h', rain ? `${rain} mm` : '—'],
    ];
    return `<dl class="bi-cards">${cards.map(([label, value, sub, color]) => `<div${color ? ` class="bi-card-risk" style="--risk-color:${color}"` : ''}><dt>${escapeHtml(label)}</dt><dd><strong>${escapeHtml(value)}</strong>${sub ? `<span>${escapeHtml(sub)}</span>` : ''}</dd></div>`).join('')}</dl>`;
}

function aiMarkup(insight, language, loading) {
    const toggle = `<div class="bi-lang" role="group" aria-label="Insight language">${Object.entries(LANGUAGE_NAMES).map(([code, label]) => `<button type="button" data-bi-lang="${code}" aria-pressed="${code === language}">${label}</button>`).join('')}</div>`;
    let content;
    if (loading) {
        content = '<div class="ic-m-loading bi-loading" aria-busy="true" role="status"><span class="fad-skel is-wide"></span><span class="fad-skel"></span><span class="ic-sr">Preparing the AI insight…</span></div>';
    } else if (insight?.status === 'ok') {
        content = `<p class="bi-ai-text">${escapeHtml(insight.text)}</p><h3>What You Can Do</h3><p class="bi-ai-action">${escapeHtml(insight.action)}</p>`;
    } else {
        // The values above stay; only the explanation is missing. iClimate's own short action is shown when available.
        content = `<p class="bi-ai-off" role="status">${UNAVAILABLE}</p>${insight?.action ? `<h3>What You Can Do</h3><p class="bi-ai-action">${escapeHtml(insight.action)}</p>` : ''}`;
    }
    return `<section class="bi-ai" aria-live="polite"><div class="bi-ai-head"><h3>${icon('spark')}AI Insight</h3>${toggle}</div>${content}</section>`;
}

function bodyMarkup(name, data, insight, { loading, own, back }) {
    const notices = noticesFor(data);
    return `<div class="bi" data-barangay-insight="${escapeHtml(name)}">`
        + (back ? `<button type="button" class="ic-notif-back bi-back" data-bi-back>${icon('back')}All barangays</button>` : '')
        + (own ? '<p class="bi-own">Your barangay</p>' : '')
        + cardsMarkup(data)
        + (notices.length ? `<div class="bi-notices">${notices.map((text) => `<p>${escapeHtml(text)}</p>`).join('')}</div>` : '')
        + aiMarkup(insight, state?.language, loading)
        + '</div>';
}

function noteMarkup(data, timeFor) {
    const weather = timeFor(data.weather_updated_at);
    const risk = timeFor(data.risk_calculated_at);
    return `${icon('clock')} Weather updated: ${escapeHtml(weather || 'not available')} · Risk updated: ${escapeHtml(risk || 'not available')}<br>Weather source: ${escapeHtml(data.weather_source || 'Open-Meteo')} · Risk assessment: iClimate`;
}

const kickerFor = (data) => (isCurrent(data) ? 'Current AI Insight' : 'Latest Available AI Insight');

/** True while the pop-up still shows this barangay's insight (not closed, not replaced by another pop-up). */
const showing = (name) => Boolean(document.querySelector(`#icInfoModal:not([hidden]) [data-barangay-insight="${CSS.escape(name)}"]`));

function render(data, insight, loading) {
    const { name, ctx } = state;
    updateModal({
        body: bodyMarkup(name, data, insight, { loading, own: state.own, back: Boolean(ctx.onBack) }),
        note: noteMarkup(data, ctx.timeFor),
    });
    const kicker = document.querySelector('#icInfoModal .fad-m-head .fad-label');
    if (kicker) kicker.textContent = kickerFor(data);
}

async function load() {
    const current = state;
    const request = Symbol('insight');
    current.request = request;
    const url = new URL(`${current.ctx.url}/${encodeURIComponent(current.name)}`, window.location.href);
    url.searchParams.set('lang', current.language);
    let payload = null;
    try {
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', loader: false });
        payload = response.ok ? await response.json() : null;
    } catch (error) {
        payload = null;
    }
    if (state !== current || current.request !== request || !showing(current.name)) return;
    if (payload?.status === 'ok') {
        current.data = payload.data;
        current.signature = payload.signature;
        current.own = current.own || Boolean(payload.is_own_barangay);
        render(payload.data, payload.insight, false);
    } else {
        // The endpoint could not answer: the list's verified values stay, only the explanation is missing.
        render(current.data, null, false);
    }
}

/**
 * Opens the insight for one barangay.
 * ctx: { url, entry, weatherParts, displayName, own, language, timeFor(iso) -> text, onViewMap(name), onBack() | null }
 */
export function openBarangayInsight(name, trigger, ctx) {
    const data = dataFromEntry(ctx.entry, ctx.weatherParts);
    state = { name, ctx, language: ctx.language === 'fil' ? 'fil' : 'en', own: Boolean(ctx.own), data, signature: signatureOf(data), request: null };
    const dialog = openModal({
        title: ctx.displayName(name),
        kicker: kickerFor(data),
        description: 'Lian, Batangas',
        icon: 'spark',
        size: 'compact',
        trigger,
        body: bodyMarkup(name, data, null, { loading: true, own: state.own, back: Boolean(ctx.onBack) }),
        note: noteMarkup(data, ctx.timeFor),
        actions: '<button type="button" class="fad-btn" data-bi-map>View on Heat Map</button>',
    });
    const opened = state;
    // Bound to this opening's body and footer, which the next pop-up replaces, so listeners never pile up.
    dialog.querySelector('[data-ic-actions] [data-bi-map]')?.addEventListener('click', () => {
        closeModal();
        ctx.onViewMap(name);
    });
    dialog.querySelector('[data-ic-body]')?.addEventListener('click', (event) => {
        if (state !== opened) return;
        const lang = event.target.closest('[data-bi-lang]');
        if (lang && lang.dataset.biLang !== state.language) {
            state.language = lang.dataset.biLang;
            ctx.onLanguage?.(state.language);
            render(state.data, null, true);
            load();
        } else if (event.target.closest('[data-bi-back]')) {
            state = null;
            ctx.onBack();
        }
    });
    load();
}

/**
 * Called after the map takes in new weather/risk data: if the open insight's weather or risk time changed, the
 * insight is asked for again (the server explains new data once); unchanged data is left as it is.
 */
export function refreshOpenInsight(entryFor, weatherPartsFor) {
    if (!state || !showing(state.name)) return;
    const entry = entryFor(state.name);
    const data = dataFromEntry(entry, weatherPartsFor(entry));
    if (signatureOf(data) === state.signature) return;
    state.data = data;
    state.signature = signatureOf(data);
    render(data, null, true);
    load();
}
