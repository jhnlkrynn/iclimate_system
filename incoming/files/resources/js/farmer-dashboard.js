// Farmer dashboard (resources/views/dashboards/farmer.blade.php): the clock, the three charts, the refresh button and the
// offline / low-data behaviour (the page keeps the last information it loaded and says when that was).
// Nothing here runs on other pages: it starts only when the dashboard root is present.

const TIMEZONE = 'Asia/Manila';
const REFRESH_EVERY_MS = 300000;

export function initFarmerDashboard() {
    const root = document.querySelector('[data-farmer-dashboard]');
    if (!root) return;

    const $ = (selector) => root.querySelector(selector);
    const charts = {};                      // key -> Chart instance, so a redrawn canvas never leaves a stale chart behind
    const lastHtml = new Map();             // slot name -> HTML last put on the page, so an unchanged section is left alone
    let refreshing = false;
    let lastOkAt = new Date();              // the page itself was rendered from fresh data a moment ago

    const timeText = (d) => d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: TIMEZONE });
    // Data Saver or a very slow connection: the optional charts and the background refresh are skipped, the numbers stay.
    const lowData = () => { const c = navigator.connection; return !!c && (!!c.saveData || /(^|slow-)2g$/.test(c.effectiveType || '')); };

    const ready = async () => {
        try { await window.iClimateVisualsReady; } catch (e) { /* handled by callers */ }
        return !!window.Chart;
    };

    // ------------------------------------------------------------------ clock (day and time to the minute, no seconds)

    const tickClock = () => {
        const el = $('[data-mfd-clock]');
        if (!el) return;
        const d = new Date(new Date().toLocaleString('en-US', { timeZone: TIMEZONE }));
        el.textContent = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }) + ' • ' + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    };
    setInterval(tickClock, 30000);

    // ------------------------------------------------------------------ charts

    const axisText = (text) => ({ display: true, text, font: { size: 12 } });
    const baseOptions = (extra = {}) => ({
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 14, font: { size: 12 } } }, ...(extra.plugins || {}) },
        scales: extra.scales || {},
    });

    /**
     * Draws one chart into a canvas. A chart is optional: when the library, the data or the drawing fails, its card (or the
     * chart box alone, when `boxOnly`) is quietly left out, and the reason goes to the console for IT.
     */
    async function draw(key, canvasSelector, build, { cardSelector = null } = {}) {
        const canvas = $(canvasSelector);
        if (!canvas) return;
        const box = canvas.parentElement;
        const card = cardSelector ? canvas.closest(cardSelector) : null;
        if (lowData()) { if (card) card.hidden = true; else if (box) box.hidden = true; return; }
        const drop = (why) => {
            console.error('Farmer dashboard chart not drawn (' + key + '):', why);
            if (card) card.hidden = true; else if (box) box.hidden = true;
        };
        try {
            if (charts[key]) { charts[key].destroy(); delete charts[key]; }
            if (!(await ready())) return drop('chart library not loaded');
            const config = build(JSON.parse(canvas.dataset.mfdChart));
            if (!config) return drop('no values to draw');
            charts[key] = new window.Chart(canvas, config);
            box?.classList.remove('is-loading');
        } catch (e) { drop(e); }
    }

    const rainfallChart = () => draw('rainfall', '#farmerRainfallTrendChart', (cfg) => {
        const datasets = cfg.datasets.map((d) => {
            const predicted = d.kind === 'predicted';
            return { label: d.label, data: d.data, backgroundColor: predicted ? 'rgba(43,122,155,.28)' : '#2b7a9b', borderColor: '#2b7a9b',
                borderWidth: predicted ? 1.5 : 0, borderDash: predicted ? [5, 4] : [], borderRadius: 4, maxBarThickness: 34 };
        }).filter((d) => d.data.some((v) => v !== null));
        if (!datasets.length) return null;

        return {
            type: 'bar', data: { labels: cfg.labels, datasets },
            options: baseOptions({
                scales: {
                    y: { beginAtZero: true, title: axisText('Rainfall (mm)'), ticks: { font: { size: 12 } }, grid: { color: '#eef2ef' } },
                    x: { title: axisText('Month'), ticks: { font: { size: 12 } }, grid: { display: false } },
                },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' mm' } } },
            }),
        };
    }, { cardSelector: '[data-mfd-rainfall]' });

    // The week ahead: expected rain as bars (mm, left axis) with the high and low temperature as lines (°C, right axis).
    const forecastChart = () => draw('forecast', '#farmerForecastChart', (cfg) => {
        if (!cfg.labels.length) return null;

        return {
            type: 'bar',
            data: {
                labels: cfg.labels,
                datasets: [
                    { type: 'bar', label: 'Rain expected (mm)', data: cfg.rain, yAxisID: 'y', backgroundColor: 'rgba(43,122,155,.35)', borderColor: '#2b7a9b', borderWidth: 1, borderRadius: 4, maxBarThickness: 30 },
                    { type: 'line', label: 'High (°C)', data: cfg.high, yAxisID: 'y1', borderColor: '#b5411f', backgroundColor: '#b5411f', pointStyle: 'circle', pointRadius: 4, tension: .25 },
                    { type: 'line', label: 'Low (°C)', data: cfg.low, yAxisID: 'y1', borderColor: '#1f5a46', backgroundColor: '#1f5a46', borderDash: [6, 4], pointStyle: 'rectRot', pointRadius: 4, tension: .25 },
                ],
            },
            options: baseOptions({
                scales: {
                    y: { beginAtZero: true, position: 'left', title: axisText('Rain (mm)'), ticks: { font: { size: 12 } }, grid: { color: '#eef2ef' } },
                    y1: { position: 'right', title: axisText('Temperature (°C)'), ticks: { font: { size: 12 } }, grid: { display: false } },
                    x: { ticks: { font: { size: 12 } }, grid: { display: false } },
                },
            }),
        };
    });

    // Lian's yearly average harvest, with the farmer's own best guess as a dashed line (a different scope: labelled as such).
    const yieldChart = () => draw('yield', '#farmerYieldHistoryChart', (cfg) => {
        if (cfg.labels.length < 2) return null;
        const datasets = [{ type: 'bar', label: 'Lian average harvest (t/ha)', data: cfg.values, backgroundColor: '#5f8b73', borderRadius: 4, maxBarThickness: 44 }];
        if (cfg.estimate !== null) {
            datasets.push({ type: 'line', label: 'Your expected harvest, best guess (t/ha)', data: cfg.labels.map(() => cfg.estimate), borderColor: '#1f5a46', borderDash: [6, 4], borderWidth: 2, pointRadius: 0 });
        }

        return {
            type: 'bar', data: { labels: cfg.labels, datasets },
            options: baseOptions({
                scales: {
                    y: { beginAtZero: true, title: axisText('Tons per hectare'), ticks: { font: { size: 12 } }, grid: { color: '#eef2ef' } },
                    x: { title: axisText('Year'), ticks: { font: { size: 12 } }, grid: { display: false } },
                },
                plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.parsed.y.toFixed(2) + ' t/ha' } } },
            }),
        };
    }, { cardSelector: null });

    // ------------------------------------------------------------------ refresh

    const announce = (message) => { const live = $('[data-mfd-live]'); if (live) live.textContent = message; };

    /** Which refresh/retry button had keyboard focus, so it can be given back after its section is replaced. */
    const focusedControl = () => {
        const active = document.activeElement;
        if (!active || !root.contains(active)) return null;
        const hit = active.closest('[data-mfd-refresh], [data-mfd-retry]');
        return hit ? (hit.hasAttribute('data-mfd-refresh') ? '[data-mfd-refresh]' : '[data-mfd-retry]') : null;
    };

    /** Shows the "could not refresh" banner with the time of the information still on the page. */
    const showStale = (why) => {
        const banner = $('[data-mfd-refresh-error]');
        const text = $('[data-mfd-refresh-error-text]');
        const message = why + ' You are seeing the information from ' + timeText(lastOkAt) + '.';
        if (text) text.textContent = message;
        if (banner) banner.hidden = false;
        announce(message);
    };

    async function refresh(force) {
        if (refreshing || (!force && lowData())) return;
        refreshing = true;
        const btn = $('[data-mfd-refresh]');
        if (btn) { btn.setAttribute('aria-disabled', 'true'); btn.textContent = 'Refreshing...'; }
        root.classList.add('is-loading');
        root.setAttribute('aria-busy', 'true');
        const refocus = focusedControl();
        try {
            const res = await fetch(root.dataset.liveUrl + (force ? '?refresh=1' : ''), { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error('bad status');
            const data = await res.json();
            let forecastChanged = false;
            Object.entries(data.slots).forEach(([key, html]) => {
                const el = root.querySelector('[data-slot="' + key + '"]');
                if (!el) return;
                el.hidden = html.trim() === '';
                if (lastHtml.get(key) === html) return;             // unchanged: keep the section, its focus and its screen-reader state
                lastHtml.set(key, html);
                el.innerHTML = html;
                if (key === 'forecast') forecastChanged = true;
            });
            const banner = $('[data-mfd-refresh-error]');
            if (banner) banner.hidden = true;
            lastOkAt = new Date();
            tickClock();
            if (forecastChanged) forecastChart();
            announce(data.updated ? 'Dashboard updated at ' + data.updated + '.' : 'Dashboard updated.');
        } catch (e) {
            console.error('Farmer dashboard refresh failed:', e);     // the farmer keeps seeing the last loaded data
            showStale(navigator.onLine === false ? 'You are offline.' : "We couldn't refresh your dashboard.");
        } finally {
            refreshing = false;
            root.classList.remove('is-loading');
            root.removeAttribute('aria-busy');
            const b = $('[data-mfd-refresh]');
            if (b) { b.removeAttribute('aria-disabled'); b.textContent = 'Refresh'; }
            if (refocus && (!document.activeElement || document.activeElement === document.body)) $(refocus)?.focus();
        }
    }

    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-mfd-refresh]') || e.target.closest('[data-mfd-retry]')) refresh(true);
    });
    setInterval(() => { if (document.visibilityState === 'visible') refresh(false); }, REFRESH_EVERY_MS);

    window.addEventListener('offline', () => showStale('You are offline.'));
    window.addEventListener('online', () => refresh(true));

    const start = () => { tickClock(); rainfallChart(); forecastChart(); yieldChart(); };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
