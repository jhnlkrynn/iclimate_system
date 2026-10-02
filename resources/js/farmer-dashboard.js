// Farmer dashboard (resources/views/dashboards/farmer.blade.php): the clock, "updated N minutes ago", the three charts,
// the refresh button, each card's own "Try again", the late-loading harvest card and the offline / low-data behaviour
// (the page keeps the last information it loaded and says when that was).
// Nothing here runs on other pages: it starts only when the dashboard root is present. All wording comes from the
// page itself (data-text-* / data-age-* attributes, written from lang/en/farmer.php), so the page can be translated.

const TIMEZONE = 'Asia/Manila';
const REFRESH_EVERY_MS = 300000;
const FONT = '"Public Sans", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif';

// Palette (matches the page's status colours; every series also differs by shape or dash, never by colour alone).
const COLORS = { brand: '#0f5132', accent: '#198754', rain: '#1f6fb2', danger: '#c0392b', muted: '#5b6660', grid: '#e3e8e5' };

export function initFarmerDashboard() {
    const root = document.querySelector('[data-farmer-dashboard]');
    if (!root) return;

    const $ = (selector) => root.querySelector(selector);
    const t = (name) => root.dataset[name] || '';
    const charts = {};                      // key -> Chart instance, so a redrawn canvas never leaves a stale chart behind
    const lastHtml = new Map();             // slot name -> HTML last put on the page, so an unchanged section is left alone
    let refreshing = false;
    let lastOkAt = new Date();              // the page itself was rendered from fresh data a moment ago

    const timeText = (d) => d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: TIMEZONE });
    // Data Saver or a very slow connection: the optional charts and the background refresh are skipped, the numbers stay.
    const lowData = () => { const c = navigator.connection; return !!c && (!!c.saveData || /(^|slow-)2g$/.test(c.effectiveType || '')); };
    const small = () => window.matchMedia('(max-width: 640px)').matches;

    const ready = async () => {
        try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ }
        return !!window.Chart;
    };

    // ------------------------------------------------------------------ clock and "updated N minutes ago"

    const tickClock = () => {
        const el = $('[data-mfd-clock]');
        if (!el) return;
        const d = new Date(new Date().toLocaleString('en-US', { timeZone: TIMEZONE }));
        el.textContent = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) + ' • ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    };

    const ageText = (minutes) => {
        if (minutes < 1) return t('ageNow');
        if (minutes === 1) return t('ageMinute');
        if (minutes < 60) return t('ageMinutes').replace(':count', minutes);
        const hours = Math.floor(minutes / 60);
        return hours === 1 ? t('ageHour') : t('ageHours').replace(':count', hours);
    };

    /** Keeps "Updated 21 minutes ago" true while the page stays open, and flags the data as old after the limit. */
    const tickAge = () => {
        const el = $('[data-mfd-age]');
        if (!el || !el.dataset.updated) return;
        const minutes = Math.max(0, Math.floor((Date.now() - new Date(el.dataset.updated).getTime()) / 60000));
        el.textContent = ageText(minutes);
        const old = minutes > Number(root.dataset.staleAfter || 60);
        const fresh = el.parentElement;
        if (old && !fresh.querySelector('.mfd-pill')) {
            const pill = document.createElement('span');
            pill.className = 'mfd-pill is-watch';
            pill.textContent = t('textOld');
            el.after(pill);
        }
    };
    setInterval(() => { tickClock(); tickAge(); }, 30000);

    // ------------------------------------------------------------------ charts

    const axisText = (text) => ({ display: true, text, color: COLORS.muted, font: { size: 13, weight: '500' } });
    const tickFont = { size: 13 };

    /** Defaults for every chart on the page: one font, readable sizes, readable tooltips. */
    const setDefaults = () => {
        const C = window.Chart;
        C.defaults.font.family = FONT;
        C.defaults.font.size = 14;
        C.defaults.color = '#1b1f1d';
        C.defaults.plugins.tooltip.titleFont = { family: FONT, size: 14, weight: '600' };
        C.defaults.plugins.tooltip.bodyFont = { family: FONT, size: 14 };
        C.defaults.plugins.tooltip.padding = 12;
    };

    const baseOptions = (extra = {}) => ({
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 13 } } }, ...(extra.plugins || {}) },
        scales: extra.scales || {},
    });

    /**
     * Draws one chart into a canvas. A chart is optional: when the library, the data or the drawing fails, its box is
     * quietly left out (the text and the screen-reader table stay), and the reason goes to the console for IT.
     */
    async function draw(key, canvasSelector, build) {
        const canvas = $(canvasSelector);
        if (!canvas) return;
        const box = canvas.parentElement;
        const drop = (why) => { console.error('Farmer dashboard chart not drawn (' + key + '):', why); if (box) box.hidden = true; };
        if (lowData()) { if (box) box.hidden = true; return; }
        try {
            if (charts[key]) { charts[key].destroy(); delete charts[key]; }
            if (!(await ready())) return drop('chart library not loaded');
            setDefaults();
            const config = build(JSON.parse(canvas.dataset.mfdChart));
            if (!config) return drop('no values to draw');
            charts[key] = new window.Chart(canvas, config);
            box?.classList.remove('is-loading');
        } catch (e) { drop(e); }
    }

    // Rain each day as bars (mm), with a light dashed line at the heavy-rain level. One y axis only.
    const weekRainChart = () => draw('weekRain', '#farmerWeekRainChart', (cfg) => {
        if (!cfg.labels.length) return null;
        const top = Math.max(cfg.heavy_mm * 1.1, ...cfg.rain.map((v) => v ?? 0));

        return {
            type: 'bar',
            data: {
                labels: cfg.labels,
                datasets: [
                    { type: 'bar', label: 'Rain (mm)', data: cfg.rain, backgroundColor: COLORS.rain, borderRadius: 4, maxBarThickness: 36, order: 2 },
                    { type: 'line', label: 'Heavy rain level (' + cfg.heavy_mm + ' mm)', data: cfg.labels.map(() => cfg.heavy_mm), borderColor: COLORS.danger, borderDash: [6, 6], borderWidth: 1.5, pointRadius: 0, order: 1 },
                ],
            },
            options: baseOptions({
                scales: {
                    y: { beginAtZero: true, suggestedMax: top, title: axisText('Rain (mm)'), ticks: { font: tickFont, maxTicksLimit: 6 }, grid: { color: COLORS.grid } },
                    x: { title: axisText('Day'), ticks: { font: tickFont, maxRotation: 0, autoSkip: true, maxTicksLimit: small() ? 4 : 7 }, grid: { display: false } },
                },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label.startsWith('Heavy') ? c.dataset.label : 'Rain: ' + (c.parsed.y ?? 0).toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' mm' } } },
            }),
        };
    });

    // High and low temperature as two lines on their own small chart (°C).
    const weekTempChart = () => draw('weekTemp', '#farmerWeekTempChart', (cfg) => {
        if (!cfg.labels.length) return null;

        return {
            type: 'line',
            data: {
                labels: cfg.labels,
                datasets: [
                    { label: 'High (°C)', data: cfg.high, borderColor: COLORS.danger, backgroundColor: COLORS.danger, pointStyle: 'circle', pointRadius: 4, tension: .25 },
                    { label: 'Low (°C)', data: cfg.low, borderColor: COLORS.rain, backgroundColor: COLORS.rain, borderDash: [6, 4], pointStyle: 'rectRot', pointRadius: 5, tension: .25 },
                ],
            },
            options: baseOptions({
                scales: {
                    y: { title: axisText('Temperature (°C)'), ticks: { font: tickFont, maxTicksLimit: 5 }, grid: { color: COLORS.grid } },
                    x: { title: axisText('Day'), ticks: { font: tickFont, maxRotation: 0, autoSkip: true, maxTicksLimit: small() ? 4 : 7 }, grid: { display: false } },
                },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label.replace(' (°C)', '') + ': ' + (c.parsed.y ?? 0).toFixed(0) + ' °C' } } },
            }),
        };
    });

    // This year's months next to the usual rain for the same month: filled bars against outlined bars.
    const rainfallChart = () => draw('rainfall', '#farmerRainfallTrendChart', (cfg) => {
        if (!cfg.actual.some((v) => v !== null)) return null;

        return {
            type: 'bar',
            data: {
                labels: cfg.labels,
                datasets: [
                    { label: 'Rain in ' + cfg.year + ' (mm)', data: cfg.actual, backgroundColor: COLORS.accent, borderColor: COLORS.accent, borderWidth: 1, borderRadius: 4, maxBarThickness: 22 },
                    { label: 'Usual rain (mm)', data: cfg.normal, backgroundColor: 'rgba(91,102,96,.12)', borderColor: COLORS.muted, borderWidth: 2, borderRadius: 4, maxBarThickness: 22 },
                ],
            },
            options: baseOptions({
                scales: {
                    y: { beginAtZero: true, title: axisText('Rain (mm)'), ticks: { font: tickFont, maxTicksLimit: 6 }, grid: { color: COLORS.grid } },
                    x: { title: axisText('Month'), ticks: { font: tickFont, maxRotation: 0, autoSkip: true, maxTicksLimit: small() ? 6 : 12 }, grid: { display: false } },
                },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label.replace(' (mm)', '') + ': ' + (c.parsed.y === null ? '—' : c.parsed.y.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' mm') } } },
            }),
        };
    });

    const drawCharts = (which = ['week', 'rain']) => {
        if (which.includes('week')) { weekRainChart(); weekTempChart(); }
        if (which.includes('rain')) rainfallChart();
    };

    // ------------------------------------------------------------------ refresh and per-card retry

    const announce = (message) => { const live = $('[data-mfd-live]'); if (live) live.textContent = message; };

    /** Which refresh/retry button had keyboard focus, so it can be given back after its section is replaced. */
    const focusedControl = () => {
        const active = document.activeElement;
        if (!active || !root.contains(active)) return null;
        const hit = active.closest('[data-mfd-refresh], [data-mfd-retry]');
        if (!hit) return null;
        return hit.hasAttribute('data-mfd-refresh') ? '[data-mfd-refresh]' : '[data-mfd-retry="' + hit.dataset.mfdRetry + '"]';
    };

    /** Shows the "could not update" banner with the time of the information still on the page. */
    const showStale = (why) => {
        const banner = $('[data-mfd-refresh-error]');
        const text = $('[data-mfd-refresh-error-text]');
        const message = why + ' ' + t('textSeeing').replace(':time', timeText(lastOkAt));
        if (text) text.textContent = message;
        if (banner) banner.hidden = false;
        announce(message);
    };

    const setBusy = (busy, slotNames) => {
        root.setAttribute('aria-busy', busy ? 'true' : 'false');
        (slotNames || []).forEach((name) => root.querySelector('[data-slot="' + name + '"]')?.classList.toggle('is-loading', busy));
        const btn = $('[data-mfd-refresh]');
        const label = $('[data-mfd-refresh-text]');
        if (btn && label) { btn.toggleAttribute('aria-disabled', busy); label.textContent = busy ? t('textRefreshing') : t('textRefresh'); }
    };

    /**
     * Asks the server for fresh sections and puts them on the page.
     * `retry` rebuilds one card's own source (its "Try again"); `force` re-fetches the weather (the Refresh button);
     * with neither it is the quiet timed refresh.
     */
    async function refresh({ force = false, retry = null } = {}) {
        if (refreshing || (!force && !retry && lowData())) return;
        refreshing = true;
        // A card's retry key is also its slot name; the weather cards rebuild several sections.
        const busyNames = retry && retry !== 'all' ? [retry] : Array.from(root.querySelectorAll('[data-slot]')).map((e) => e.dataset.slot);
        setBusy(true, busyNames);
        const refocus = focusedControl();
        try {
            const query = new URLSearchParams();
            if (force || retry === 'all') query.set('refresh', '1');
            if (retry && retry !== 'all') query.set('retry', retry);
            const res = await fetch(root.dataset.liveUrl + (query.toString() ? '?' + query : ''), { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('bad status ' + res.status);
            const data = await res.json();
            const redraw = new Set();
            Object.entries(data.slots).forEach(([key, html]) => {
                const el = root.querySelector('[data-slot="' + key + '"]');
                if (!el) return;
                el.hidden = html.trim() === '';
                if (lastHtml.get(key) === html) return;             // unchanged: keep the section, its focus and its screen-reader state
                lastHtml.set(key, html);
                (key === 'week' ? ['weekRain', 'weekTemp'] : key === 'rain' ? ['rainfall'] : []).forEach((c) => { charts[c]?.destroy(); delete charts[c]; });
                el.innerHTML = html;
                if (key === 'week') redraw.add('week');
                if (key === 'rain') redraw.add('rain');
            });
            const banner = $('[data-mfd-refresh-error]');
            if (banner) banner.hidden = true;
            lastOkAt = new Date();
            tickClock();
            tickAge();
            if (redraw.size) drawCharts([...redraw]);
            announce(t('textUpdated'));
        } catch (e) {
            console.error('Farmer dashboard refresh failed:', e);     // the farmer keeps seeing the last loaded data
            showStale(navigator.onLine === false ? t('textOffline') : t('textFailed'));
        } finally {
            refreshing = false;
            setBusy(false, busyNames);
            if (refocus && (!document.activeElement || document.activeElement === document.body)) $(refocus)?.focus();
        }
    }

    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-mfd-refresh]')) return refresh({ force: true });
        const retry = e.target.closest('[data-mfd-retry]');
        if (retry) refresh({ retry: retry.dataset.mfdRetry });
    });
    setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, REFRESH_EVERY_MS);

    window.addEventListener('offline', () => showStale(t('textOffline')));
    window.addEventListener('online', () => refresh({ force: true }));

    const start = () => {
        tickClock();
        tickAge();
        drawCharts();
        // The harvest model is slow, so the page opens with a placeholder and fills the card in here.
        if ($('[data-mfd-load="harvest"]')) refresh({ retry: 'harvest' });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
