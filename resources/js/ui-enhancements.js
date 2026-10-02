// iClimate UI refresh: small, progressive enhancements layered on top of the
// server-rendered Blade markup. Every function here is optional polish — the
// page reads and works the same when JS is off or motion is reduced.
//
//  - Pointer spotlight on glass cards (CSS reads --ic-mx / --ic-my).
//  - Count-up for plain numeric stat values when they scroll into view.
//  - Weather metric decoration: icon + accent + humidity/rain meters for
//    any card whose label reads Temperature / Rain / Humidity / Wind / Condition.
//  - Weather "mood" (sunny, cloudy, rain, storm, fog) on live weather cards,
//    kept in sync when the dashboards' own refresh code rewrites the text.
//  - Forecast strips: temperature range bars and rain-chance bars.

const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

const SPOTLIGHT_SELECTOR = [
    '.card', '.glass-card', '.module-tile', '.climate-chip', '.soft-section',
    '.mao-card', '.it-card', '.farmer-panel', '.farmer-org-card',
    '.wp-result-card', '.wp-advisory', '.wp-input-card',
    '.live-weather-card', '.forecast-day', '.forecast-strip-day', '.weather-mini',
    '.risk-level-card', '.field-card', '.feed-card', '.variable-info-card',
].join(',');

// ---------------------------------------------------------------- spotlight
export function bindSpotlight() {
    // Disabled across iClimate: hovering elements must not display green radial overlays or spotlights
    return;
}

// ----------------------------------------------------------------- count-up
const COUNT_SELECTOR = [
    '.stat-value', '.mao-value', '.it-value', '.it-pipeline-value',
    '.farmer-org-card-value', '.risk-level-value', '.field-value', '.wp-value',
    '[data-count-up]',
].join(',');

// "1,234", "12.5", "87%", "31.2°C", "4.10 t/ha" — a single number with an
// optional short prefix/suffix. Anything else (dates, "N/A", "—", words) is
// left exactly as the server rendered it.
const NUMERIC_PATTERN = /^(\D{0,3}?)(-?\d{1,3}(?:,\d{3})+(?:\.\d+)?|-?\d+(?:\.\d+)?)(\s?[^\d\s]{0,8}(?:\s?[a-zA-Z/%]{0,6})?)$/;

function parseCountable(text) {
    const trimmed = text.trim();
    const match = trimmed.match(NUMERIC_PATTERN);
    if (!match) return null;

    const [, prefix, raw, suffix] = match;
    const value = Number(raw.replace(/,/g, ''));
    if (!Number.isFinite(value)) return null;

    return {
        prefix,
        suffix,
        value,
        decimals: (raw.split('.')[1] || '').length,
        grouped: raw.includes(','),
        original: trimmed,
    };
}

function formatCount(parsed, value) {
    const fixed = value.toFixed(parsed.decimals);
    const number = parsed.grouped
        ? Number(fixed).toLocaleString('en-US', { minimumFractionDigits: parsed.decimals, maximumFractionDigits: parsed.decimals })
        : fixed;

    return `${parsed.prefix}${number}${parsed.suffix}`;
}

function runCountUp(element) {
    if (element.dataset.countBound === 'true') return;
    element.dataset.countBound = 'true';

    // Only animate leaf-ish elements so nested markup (units in <span>) is never flattened.
    if (element.children.length > 0) return;

    const parsed = parseCountable(element.textContent || '');
    if (!parsed || parsed.value === 0) return;
    // Bare years ("2026") read as dates, not quantities.
    if (!parsed.prefix && !parsed.suffix && !parsed.decimals && parsed.value >= 1900 && parsed.value <= 2100) return;

    const duration = Math.min(1400, 700 + Math.log10(Math.abs(parsed.value) + 1) * 180);
    const start = performance.now();
    let lastWritten = formatCount(parsed, 0);
    element.textContent = lastWritten;
    element.classList.add('ic-counting');

    const step = (now) => {
        // Another script (live weather refresh, drawer) replaced the text:
        // stop immediately and let that value stand.
        if (element.textContent !== lastWritten) {
            element.classList.remove('ic-counting');
            return;
        }

        const progress = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);

        lastWritten = progress < 1 ? formatCount(parsed, parsed.value * eased) : parsed.original;
        element.textContent = lastWritten;

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            element.classList.remove('ic-counting');
        }
    };

    requestAnimationFrame(step);
}

export function bindCountUp(root = document) {
    if (motionQuery.matches || !('IntersectionObserver' in window)) return;

    const elements = [...root.querySelectorAll(COUNT_SELECTOR)]
        .filter((element) => element.dataset.countBound !== 'true' && !element.closest('.modal, .ic-drawer, [hidden]'));
    if (!elements.length) return;

    bindCountUp.observer ??= new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            bindCountUp.observer.unobserve(entry.target);
            runCountUp(entry.target);
        });
    }, { threshold: 0.4 });

    elements.forEach((element) => bindCountUp.observer.observe(element));
}

// ------------------------------------------------------- weather metrics
const METRIC_ICONS = {
    temperature: '<path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/>',
    rain: '<path d="M20 16.2A4.5 4.5 0 0 0 17.5 8h-1.8A7 7 0 1 0 4 14.9"/><path d="M16 14v6"/><path d="M8 14v6"/><path d="M12 16v6"/>',
    humidity: '<path d="M7 16.3c2.2 0 4-1.83 4-4.05 0-1.16-.57-2.26-1.71-3.19S7.29 6.75 7 5.3c-.29 1.45-1.14 2.84-2.29 3.76S3 11.1 3 12.25c0 2.22 1.8 4.05 4 4.05z"/><path d="M12.56 6.6A10.97 10.97 0 0 0 14 3.02c.5 2.5 2 4.9 4 6.5s3 3.5 3 5.5a6.98 6.98 0 0 1-11.91 4.97"/>',
    wind: '<path d="M17.7 7.7a2.5 2.5 0 1 1 1.8 4.3H2"/><path d="M9.6 4.6A2 2 0 1 1 11 8H2"/><path d="M12.6 19.4A2 2 0 1 0 14 16H2"/>',
    condition: '<path d="M12 2v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="M20 12h2"/><path d="m19.07 4.93-1.41 1.41"/><path d="M15.95 12.65a4 4 0 0 0-5.93-4.98"/><path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"/>',
};

const METRIC_LABEL_SELECTOR = '.wp-label, .mao-label, .weather-mini > span, .risk-weather-label, .field-label, .stat-label';
const METRIC_CARD_SELECTOR = '.wp-result-card, .wp-advisory, .live-weather-card, .weather-mini, .risk-weather-tile, .field-card, .stat-card, .risk-level-card';

function metricFor(label) {
    const text = label.trim();
    if (!text || text.length > 40) return null;
    if (/temp/i.test(text)) return 'temperature';
    if (/humid/i.test(text)) return 'humidity';
    if (/\bwind/i.test(text)) return 'wind';
    if (/\brain|precip/i.test(text)) return 'rain';
    if (/condition/i.test(text) && !/farm condition/i.test(text)) return 'condition';
    return null;
}

export function metricIconSvg(metric, size = 16) {
    const paths = METRIC_ICONS[metric];
    if (!paths) return '';
    return `<svg class="ic-metric-icon" viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
}

function numberIn(text) {
    const match = String(text || '').replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);
    return match ? Number(match[0]) : null;
}

// Meter scale per metric: humidity is a true 0–100%, rain/wind/temperature
// are "how much of a typical Lian day's upper range" so the bar reads as
// a quick relative cue, never as a threshold or warning level.
const METER_MAX = { humidity: 100, rain: 50, wind: 60, temperature: 40 };

function updateMeter(card, metric, valueEl) {
    const meter = card.querySelector(':scope > .ic-metric-meter > span');
    if (!meter) return;
    const value = numberIn(valueEl?.textContent);
    const pct = value === null ? 0 : Math.max(0, Math.min(100, (value / METER_MAX[metric]) * 100));
    meter.style.width = `${pct}%`;
    card.classList.toggle('ic-metric-empty', value === null);
}

function decorateMetrics(root = document) {
    root.querySelectorAll(METRIC_LABEL_SELECTOR).forEach((label) => {
        if (label.dataset.metricBound === 'true') return;
        label.dataset.metricBound = 'true';

        const metric = metricFor(label.textContent || '');
        if (!metric) return;

        const card = label.closest(METRIC_CARD_SELECTOR) || label.parentElement;
        if (!card || card.dataset.metric) return;

        card.dataset.metric = metric;
        card.classList.add('ic-metric-card');
        label.classList.add('ic-metric-label');
        label.insertAdjacentHTML('afterbegin', metricIconSvg(metric, 15));

        if (!(metric in METER_MAX) || card.matches('.stat-card')) return;

        const valueEl = [...card.children].find((child) => child !== label && /value|strong/i.test(`${child.className} ${child.tagName}`))
            || card.querySelector('strong, [class*="value"]');
        if (!valueEl) return;

        card.insertAdjacentHTML('beforeend', '<div class="ic-metric-meter" aria-hidden="true"><span></span></div>');
        updateMeter(card, metric, valueEl);

        // Live dashboards rewrite these values on refresh; keep the bar honest.
        new MutationObserver(() => updateMeter(card, metric, valueEl))
            .observe(valueEl, { childList: true, characterData: true, subtree: true });
    });
}

// ---------------------------------------------------------- weather mood
export function weatherMoodFor(condition) {
    const value = String(condition || '').toLowerCase();
    if (!value || /unavailable|n\/a|no weather/.test(value)) return 'neutral';
    if (/thunder|storm/.test(value)) return 'storm';
    if (/heavy rain|rain|drizzle|shower/.test(value)) return 'rain';
    if (/fog|mist|haze/.test(value)) return 'fog';
    if (/night/.test(value)) return 'night';
    if (/overcast|cloud/.test(value)) return 'cloudy';
    if (/clear|sunny|fair/.test(value)) return 'sunny';
    return 'neutral';
}

function bindWeatherMood(root = document) {
    root.querySelectorAll('[data-weather-root], [data-weather-mood-root]').forEach((card) => {
        if (card.dataset.moodBound === 'true') return;
        card.dataset.moodBound = 'true';

        const source = card.querySelector('[data-weather-condition], [data-weather-mood-source]');
        if (!source) return;

        const apply = () => { card.dataset.weatherMood = weatherMoodFor(source.textContent); };
        apply();
        new MutationObserver(apply).observe(source, { childList: true, characterData: true, subtree: true });
    });
}

// ---------------------------------------------------------- forecast bars
// Daily rainfall totals (MAO outlook): bar relative to the wettest day shown.
function enhanceRainTotals(strip) {
    const days = [...strip.querySelectorAll('[data-rain-mm]')];
    const values = days.map((d) => (d.dataset.rainMm === '' ? NaN : Number(d.dataset.rainMm)));
    const wettest = Math.max(0, ...values.filter(Number.isFinite));

    days.forEach((day, index) => {
        const mm = values[index];
        if (!Number.isFinite(mm) || day.querySelector('.fd-rain')) return;
        const pct = wettest > 0 ? (mm / wettest) * 100 : 0;
        day.insertAdjacentHTML('beforeend', `<div class="fd-rain" aria-hidden="true"><span style="width:${pct.toFixed(1)}%"></span></div>`);
        day.classList.toggle('is-wet', mm >= 10);
        day.classList.toggle('is-wettest', wettest > 0 && mm === wettest);
    });
}

function enhanceForecast(strip) {
    enhanceRainTotals(strip);
    const days = [...strip.querySelectorAll('[data-tmax][data-tmin]')];
    const num = (v) => (v === '' || v === undefined ? NaN : Number(v));
    const maxes = days.map((d) => num(d.dataset.tmax)).filter(Number.isFinite);
    const mins = days.map((d) => num(d.dataset.tmin)).filter(Number.isFinite);
    if (!maxes.length || !mins.length) return;

    const low = Math.min(...mins);
    const high = Math.max(...maxes);
    const span = Math.max(1, high - low);

    days.forEach((day, index) => {
        const tmax = num(day.dataset.tmax);
        const tmin = num(day.dataset.tmin);
        if (index === 0) day.classList.add('is-today');

        if (Number.isFinite(tmax) && Number.isFinite(tmin) && !day.querySelector('.fd-range')) {
            day.insertAdjacentHTML('beforeend', `
                <div class="fd-range" role="img" aria-label="Low ${tmin.toFixed(0)}°C, high ${tmax.toFixed(0)}°C">
                    <span style="--lo:${(((tmin - low) / span) * 100).toFixed(1)}%;--hi:${(((tmax - low) / span) * 100).toFixed(1)}%"></span>
                </div>`);
        }

        const pop = num(day.dataset.pop);
        if (Number.isFinite(pop) && !day.querySelector('.fd-rain')) {
            day.insertAdjacentHTML('beforeend', `
                <div class="fd-rain" aria-hidden="true"><span style="width:${Math.max(0, Math.min(100, pop))}%"></span></div>`);
            day.classList.toggle('is-wet', pop >= 60);
        }
    });
}

function bindForecastStrips(root = document) {
    root.querySelectorAll('[data-weather-forecast], [data-forecast-strip]').forEach((strip) => {
        enhanceForecast(strip);
        if (strip.dataset.forecastBound === 'true') return;
        strip.dataset.forecastBound = 'true';
        // The Farmer dashboard re-renders the strip on every weather refresh.
        new MutationObserver(() => enhanceForecast(strip)).observe(strip, { childList: true });
    });
}

// ------------------------------------------------------------------ init
export function initUiEnhancements(root = document) {
    bindSpotlight();
    decorateMetrics(root);
    bindWeatherMood(root);
    bindForecastStrips(root);
    bindCountUp(root);
}

window.iClimateUi = { init: initUiEnhancements, weatherMoodFor, metricIconSvg };
