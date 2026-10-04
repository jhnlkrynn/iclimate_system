// Farmer dashboard (resources/views/dashboards/farmer.blade.php): the clock, "updated N minutes ago", the three charts,
// the refresh button, each card's own "Try again", the late-loading harvest card and the offline / low-data behaviour
// (the page keeps the last information it loaded and says when that was).
// Nothing here runs on other pages: it starts only when the dashboard root is present. All wording comes from the
// page itself (data-text-* / data-age-* attributes, written from lang/en/farmer.php), so the page can be translated.

const TIMEZONE = 'Asia/Manila';
const REFRESH_EVERY_MS = 300000;
const FONT = '"Inter", Arial, system-ui, -apple-system, "Segoe UI", sans-serif';

// Palette (matches the page's status colours; every series also differs by shape or dash, never by colour alone).
// The sections each dashboard pop-up changes (they show as busy while they are rebuilt); the server holds the same list.
const POPUP_SLOTS = { planting: ['farm', 'season', 'harvest', 'setup', 'quick'], profile: ['header', 'farm', 'today', 'risk', 'setup', 'advisories', 'quick'], map: ['farm', 'setup', 'quick'] };

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
        const busyNames = POPUP_SLOTS[retry] ? POPUP_SLOTS[retry]
            : retry && retry !== 'all' ? [retry] : Array.from(root.querySelectorAll('[data-slot]')).map((e) => e.dataset.slot);
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

    // ------------------------------------------------------------------ dashboard pop-ups

    /**
     * The dashboard's setup buttons open a pop-up over the page instead of leaving it, and which one depends on the
     * button: data-mfd-popup names it (planting, farm-size, profile, farm-map). All of them share one frame
     * (components/farmer-popup.blade.php) and the behaviour here; each adds only its own checks, what it sends and
     * which sections to rebuild after saving. Without scripts the buttons stay ordinary links to the full pages.
     */
    const initPopups = () => {
        const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const popups = {};
        let active = null;                                              // the open pop-up: there is never a second one

        const toast = (message) => {
            document.querySelector('.mfd-toast')?.remove();
            const el = document.createElement('div');
            el.className = 'mfd-toast';
            el.setAttribute('role', 'status');
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 5000);
        };

        /**
         * @param {string} name   the pop-up's name, as on the buttons that open it
         * @param {object} hooks  validate(p) -> [[field, message], ...]; payload(p) -> what is sent; retry: the sections
         *                        to rebuild; optional onOpen(p), dirty(p), canSave(p), saved(p, data), thenHarvest, focusAfter
         */
        const make = (name, hooks) => {
            const modal = document.querySelector('[data-mfd-popup-modal="' + name + '"]');
            if (!modal) return;
            const dialog = modal.querySelector('.fad-modal-dialog');
            const form = modal.querySelector('[data-mfd-popup-form]');
            const save = modal.querySelector('[data-mfd-popup-save]');
            const alertBox = modal.querySelector('[data-mfd-popup-alert]');
            const controls = Array.from(form.elements).filter((el) => el.name && el.name !== '_token');
            const heading = dialog.querySelector('h2');
            const intro = dialog.querySelector('.mfd-pm-intro');
            const wording = { title: heading.textContent, intro: intro.textContent };
            let opener = null;
            let saving = false;

            // The page wrapper is animated with a transform, which would make it the box a fixed pop-up covers.
            document.body.appendChild(modal);

            const p = {
                name, modal, form, save,
                text: (key) => modal.dataset[key] || '',
                field: (key) => form.elements[key] || modal.querySelector('[data-mfd-field="' + key + '"]'),
                setError(key, message) {
                    const slot = modal.querySelector('[data-mfd-error="' + key + '"]');
                    const field = p.field(key);
                    if (field) { if (message) field.setAttribute('aria-invalid', 'true'); else field.removeAttribute('aria-invalid'); }
                    if (slot) { slot.textContent = message || ''; slot.hidden = !message; }
                },
                setAlert(message) { alertBox.textContent = message || ''; alertBox.hidden = !message; },
                clearErrors() { modal.querySelectorAll('[data-mfd-error]').forEach((slot) => p.setError(slot.dataset.mfdError, '')); p.setAlert(''); },
            };

            const changed = () => controls.some((el) => (el.tagName === 'SELECT'
                ? Array.from(el.options).some((o) => o.selected !== o.defaultSelected) : el.value !== el.defaultValue)) || !!hooks.dirty?.(p);
            /** What was just saved becomes what "Cancel" and the next opening go back to. */
            const commit = (values = {}) => controls.forEach((el) => {
                if (values[el.name] !== undefined && values[el.name] !== null) el.value = values[el.name];
                if (el.tagName === 'SELECT') Array.from(el.options).forEach((o) => { o.defaultSelected = o.selected; });
                else el.defaultValue = el.value;
            });

            const open = (trigger) => {
                if (active) return;
                active = popups[name];
                opener = trigger;
                form.reset();                                           // back to the last saved values
                p.clearErrors();
                // A button may open the pop-up in its own form (Quick Actions' "Farm Information" is the farm size pop-up
                // with the farm type shown): the title and description are the button's, the rest is the same pop-up.
                modal.dataset.variant = trigger?.dataset.mfdPopupVariant || '';
                heading.textContent = trigger?.dataset.mfdPopupTitle || wording.title;
                intro.textContent = trigger?.dataset.mfdPopupIntro || wording.intro;
                modal.hidden = false;
                document.documentElement.classList.add('fad-modal-open');
                hooks.onOpen?.(p);
                const first = controls.find((el) => el.required && !el.value) || controls[0] || heading;
                first.focus();
            };
            const close = () => {
                if (modal.hidden || saving) return;
                modal.hidden = true;
                active = null;
                document.documentElement.classList.remove('fad-modal-open');
                // The section holding the opener may have been redrawn since: fall back to the same button in the new markup.
                (opener && document.contains(opener) ? opener : root.querySelector('[data-mfd-popup="' + name + '"]'))?.focus();
                opener = null;
            };

            modal.addEventListener('click', (e) => {
                // A click on the dimmed page closes it only while nothing has been changed, so entries are not lost by accident.
                if (e.target.closest('[data-mfd-popup-close]') || (e.target === modal && !changed())) close();
            });
            controls.forEach((el) => el.addEventListener(el.tagName === 'SELECT' || el.type === 'date' ? 'change' : 'input', () => {
                if (!el.hasAttribute('aria-invalid')) return;
                p.setError(el.name, (hooks.validate(p).find(([key]) => key === el.name) || [])[1] || '');
            }));

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (saving) return;                                     // a second click or Enter while the first is still being saved
                p.clearErrors();
                const problems = hooks.validate(p);
                problems.forEach(([key, message]) => p.setError(key, message));
                if (problems.length) { p.field(problems[0][0])?.focus(); return; }

                saving = true;
                save.disabled = true;
                const label = save.textContent;
                save.textContent = p.text('textSaving');
                let saved = null;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(hooks.payload(p)),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 422 && data.errors) {
                        // "boundary_coordinates.3.lat" belongs to the boundary_coordinates field.
                        const shown = [];
                        Object.entries(data.errors).forEach(([key, messages]) => {
                            const field = key.split('.')[0];
                            if (shown.includes(field) || !modal.querySelector('[data-mfd-error="' + field + '"]')) return;
                            shown.push(field);
                            p.setError(field, messages[0]);
                        });
                        if (shown.length) p.field(shown[0])?.focus(); else p.setAlert(p.text('textFailed'));
                    } else if (!res.ok) {
                        throw new Error('bad status ' + res.status);
                    } else {
                        saved = data;
                    }
                } catch (err) {
                    console.error('Saving from the "' + name + '" pop-up failed:', err);
                    p.setAlert(p.text('textFailed'));                    // the pop-up stays open with what was entered
                } finally {
                    saving = false;
                    save.disabled = hooks.canSave ? !hooks.canSave(p) : false;
                    save.textContent = label;
                }
                if (!saved) return;

                commit(saved.values);
                hooks.saved?.(p, saved);
                close();
                toast(saved.message);
                announce(saved.message);
                while (refreshing) await new Promise((done) => setTimeout(done, 200));     // let a refresh already under way finish first
                await refresh({ retry: hooks.retry });
                if (hooks.thenHarvest) refresh({ retry: 'harvest' });   // the slow estimate follows on its own
                // The button that opened the pop-up is gone once its card is redrawn: keyboard focus goes to the updated part.
                if (!document.activeElement || document.activeElement === document.body) {
                    const target = root.querySelector(hooks.focusAfter) || root.querySelector('h1');
                    if (target) { target.setAttribute('tabindex', '-1'); target.focus({ preventScroll: true }); }
                }
            });

            popups[name] = { open, close, dialog };
            return p;
        };

        // Shared farm size rule: a number above 0 with at most 2 decimals.
        const areaProblem = (field, invalid, decimals) => {
            const raw = field.value.trim();
            if (raw === '' || field.validity.badInput || !(Number(raw) > 0) || Number(raw) > Number(field.max)) return invalid;
            return /^\d*(\.\d{1,2})?$/.test(raw) ? '' : decimals;
        };
        const problems = (pairs) => pairs.filter(([, message]) => message);

        // ---- Add Planting Information (Add planting date; Quick Actions)
        const planting = make('planting', {
            retry: 'planting', focusAfter: '#mfdSeasonTitle',
            validate: (p) => {
                const date = p.field('planting_date');
                const dateMessage = !date.value ? (date.validity.badInput ? p.text('textDateInvalid') : p.text('textDateRequired'))
                    : (date.min && date.value < date.min) || (date.max && date.value > date.max) ? p.text('textDateInvalid') : '';
                return problems([['planting_date', dateMessage], ['farm_area', areaProblem(p.field('farm_area'), p.text('textAreaInvalid'), p.text('textAreaDecimals'))]]);
            },
            payload: (p) => ({ planting_date: p.field('planting_date').value, farm_area: p.field('farm_area').value.trim() }),
            onOpen: () => showDate(),
            saved: (p, data) => {
                p.field('planting_date').defaultValue = data.planting_date;
                p.field('farm_area').defaultValue = data.farm_area;
                sizeSaved(data.farm_area);
            },
        });
        /** The chosen date written out the way the dashboard writes dates ("Oct 4, 2026"). */
        const showDate = () => {
            const shown = planting.modal.querySelector('[data-mfd-planting-shown]');
            const [y, mo, d] = (planting.field('planting_date').value || '').split('-').map(Number);
            const ok = y && mo && d;
            shown.hidden = !ok;
            if (ok) shown.textContent = planting.text('textSelected').replace(':date', new Date(y, mo - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
        };
        planting?.field('planting_date').addEventListener('change', showDate);

        // ---- Add Farm Size (Add farm size)
        const size = make('farm-size', {
            retry: 'planting', focusAfter: '#mfdHarvestTitle',
            validate: (p) => problems([['farm_area', areaProblem(p.field('farm_area'), p.text('textInvalid'), p.text('textDecimals'))]]),
            // The farm type is sent only when its field is on screen ("Farm Information").
            payload: (p) => ({ farm_area: p.field('farm_area').value.trim(), ...(p.modal.dataset.variant === 'info' ? { farm_type: p.field('farm_type').value } : {}) }),
            onOpen: (p) => { p.save.textContent = p.modal.dataset.variant === 'info' ? p.text('textSaveInfo') : p.field('farm_area').defaultValue ? p.text('textUpdate') : p.text('textAdd'); },
            saved: (p, data) => { sizeSaved(data.values.farm_area); farmTypeSaved(data.values.farm_type); },
        });
        /** The farm type can be set in two pop-ups (Farm Information, Update Profile): both start from the saved one. */
        const farmTypeSaved = (value) => {
            if (!value) return;
            ['farm-size', 'profile'].forEach((name) => {
                const select = document.querySelector('[data-mfd-popup-modal="' + name + '"] [name="farm_type"]');
                if (select) Array.from(select.options).forEach((o) => { o.selected = o.defaultSelected = o.value === value; });
            });
        };
        /** A farm size saved by either pop-up is what both start from next time; with one saved the button says "Update". */
        const sizeSaved = (value) => {
            [planting, size].forEach((p) => { const field = p?.field('farm_area'); if (field) { field.defaultValue = value; field.value = value; } });
        };

        // ---- Update Profile (Update profile)
        make('profile', {
            retry: 'profile', thenHarvest: true, focusAfter: 'h1',
            validate: (p) => {
                const contact = p.field('contact_number').value.trim();
                return problems([
                    ['name', p.field('name').value.trim() ? '' : p.text('textNameRequired')],
                    ['email', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.field('email').value.trim()) ? '' : p.text('textEmailInvalid')],
                    ['contact_number', contact === '' || /^\+?[0-9][0-9\s\-()]{6,19}$/.test(contact) ? '' : p.text('textContactInvalid')],
                ]);
            },
            payload: (p) => Object.fromEntries(['name', 'email', 'contact_number', 'address', 'barangay', 'farm_type'].map((key) => [key, p.field(key).value.trim()])),
            saved: (p, data) => {
                farmTypeSaved(data.values.farm_type);
                // The top bar is outside the refreshed sections: keep the name and barangay shown there in step.
                const name = document.querySelector('.topbar-name');
                const place = document.querySelector('.topbar-user-meta .topbar-eyebrow');
                const avatar = document.querySelector('.topbar-avatar');
                if (name) name.textContent = data.values.name;
                if (place) place.textContent = 'Farmer' + (data.values.barangay ? ' • ' + data.values.barangay : '');
                if (avatar) avatar.textContent = data.values.name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('') || '?';
            },
        });

        // ---- Map My Farm (Map my farm): the farm's outline, drawn as on the My Farm Map page
        // Only a farm inside Lian can be saved, and its barangay is read from the map: both come from the official
        // boundary files the server checks again when saving (App\Support\LianGeography does the same work there).
        const farmMap = (() => {
            const state = { map: null, markers: [], polygon: null, saved: [], loading: null, geo: null, place: { state: 'none', barangay: null }, lit: null, frame: 0 };
            const EARTH = 6371008.8;
            const points = () => state.markers.map((marker) => { const at = marker.getLatLng(); return { lat: Number(at.lat.toFixed(7)), lng: Number(at.lng.toFixed(7)) }; });
            /** Hectares inside the outline (the server measures it again when saving). */
            const hectares = (pts) => {
                if (pts.length < 3) return null;
                const mean = pts.reduce((sum, pt) => sum + pt.lat, 0) / pts.length * Math.PI / 180;
                const flat = pts.map((pt) => ({ x: pt.lng * Math.PI / 180 * EARTH * Math.cos(mean), y: pt.lat * Math.PI / 180 * EARTH }));
                let area = 0;
                flat.forEach((pt, i) => { const next = flat[(i + 1) % flat.length]; area += pt.x * next.y - next.x * pt.y; });
                return Math.abs(area) / 2 / 10000;
            };

            // -- where a position is: inside Lian or not, and in which barangay (shapes are [lng, lat] rings)
            const polygonsOf = (feature) => (feature.geometry.type === 'MultiPolygon' ? feature.geometry.coordinates : [feature.geometry.coordinates]);
            const inRing = (lng, lat, ring) => {
                let inside = false;
                for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                    const xi = ring[i][0], yi = ring[i][1], xj = ring[j][0], yj = ring[j][1];
                    if ((yi > lat) !== (yj > lat) && lng < (xj - xi) * (lat - yi) / (yj - yi) + xi) inside = !inside;
                }
                return inside;
            };
            const inPolygons = (pt, polygons) => polygons.some((rings) => inRing(pt.lng, pt.lat, rings[0]) && !rings.slice(1).some((hole) => inRing(pt.lng, pt.lat, hole)));
            const inLian = (pt) => inPolygons(pt, state.geo.lian);
            const barangayAt = (pt) => (state.geo.barangays.find((b) => inPolygons(pt, b.polygons)) || {}).name || null;
            const cross = (ax, ay, bx, by, cx, cy, dx, dy) => {
                const side = (px, py, qx, qy, rx, ry) => (qx - px) * (ry - py) - (qy - py) * (rx - px);
                const d1 = side(cx, cy, dx, dy, ax, ay), d2 = side(cx, cy, dx, dy, bx, by), d3 = side(ax, ay, bx, by, cx, cy), d4 = side(ax, ay, bx, by, dx, dy);
                return ((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) && ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0));
            };
            /** Does a side of the outline cut across the Lian boundary? Boundary pieces far from the farm are skipped. */
            const crossesLian = (pts) => {
                if (pts.length < 2) return false;
                const lats = pts.map((pt) => pt.lat), lngs = pts.map((pt) => pt.lng);
                const south = Math.min(...lats), north = Math.max(...lats), west = Math.min(...lngs), east = Math.max(...lngs);
                const sides = pts.length === 2 ? 1 : pts.length;
                return state.geo.lian.some((rings) => rings.some((ring) => {
                    for (let i = 0; i < ring.length - 1; i++) {
                        const ax = ring[i][0], ay = ring[i][1], bx = ring[i + 1][0], by = ring[i + 1][1];
                        if (Math.max(ax, bx) < west || Math.min(ax, bx) > east || Math.max(ay, by) < south || Math.min(ay, by) > north) continue;
                        for (let s = 0; s < sides; s++) {
                            const from = pts[s], to = pts[(s + 1) % pts.length];
                            if (cross(from.lng, from.lat, to.lng, to.lat, ax, ay, bx, by)) return true;
                        }
                    }
                    return false;
                }));
            };
            /**
             * none: nothing marked. outside: no part is in Lian. partly: some of it is. unknown: in Lian, barangay not found.
             * ok: all of it is in Lian, in `barangay` (the one holding most corners and the middle; a tie goes to the first
             * in the official list, so it never flips for the same outline).
             */
            const locate = (pts) => {
                if (!pts.length) return { state: 'none', barangay: null, out: [] };
                const out = pts.map((pt) => !inLian(pt));
                const outside = out.filter(Boolean).length;
                if (outside === pts.length) return { state: 'outside', barangay: null, out };
                if (outside > 0 || crossesLian(pts)) return { state: 'partly', barangay: null, out };
                const middle = { lat: pts.reduce((sum, pt) => sum + pt.lat, 0) / pts.length, lng: pts.reduce((sum, pt) => sum + pt.lng, 0) / pts.length };
                const votes = new Map(state.geo.barangays.map((b) => [b.name, 0]));
                [...pts, middle].forEach((pt) => { const name = barangayAt(pt); if (name) votes.set(name, votes.get(name) + 1); });
                let barangay = null, best = 0;
                votes.forEach((count, name) => { if (count > best) { best = count; barangay = name; } });
                return barangay ? { state: 'ok', barangay, out } : { state: 'unknown', barangay: null, out };
            };
            const barangayLabel = (name) => (name.startsWith('Barangay') ? name : 'Barangay ' + name);

            /** The "Selected Farm Location" panel, the farm's pop-up label, the barangay highlight and the Save button. */
            const describe = (p, pts) => {
                const place = state.place = locate(pts);
                const t = (key) => p.text(key);
                const ready = place.state === 'ok' && pts.length >= 3;
                const panel = p.modal.querySelector('[data-mfd-map-place]');
                const note = p.modal.querySelector('[data-mfd-map-note]');
                panel.dataset.state = place.state === 'ok' ? 'ok' : place.state === 'none' ? 'none' : 'invalid';
                p.modal.querySelector('[data-mfd-map-barangay]').textContent = {
                    none: t('textNone'), outside: t('textBarangayOutside'), partly: t('textBarangayOutside'), unknown: t('textBarangayUnknown'),
                }[place.state] || place.barangay;
                p.modal.querySelector('[data-mfd-map-status]').textContent = place.state === 'none' ? t('textNone') : place.state === 'ok' ? t('textStatusOk') : t('textStatusInvalid');
                const message = { ok: ready ? t('textNoteOk') : t('textNoteMore'), outside: t('textOutside'), partly: t('textPartly'), unknown: t('textUnknown') }[place.state] || '';
                note.hidden = !message;
                note.innerHTML = '';
                if (place.state === 'outside') { const title = document.createElement('strong'); title.textContent = t('textOutsideTitle'); note.append(title, ' '); }
                note.append(message);
                p.field('boundary_coordinates')?.toggleAttribute('aria-invalid', panel.dataset.state === 'invalid');
                p.setError('boundary_coordinates', '');
                p.save.disabled = !ready;

                state.markers.forEach((marker, i) => marker.getElement()?.classList.toggle('is-out', !!place.out[i]));
                if (state.lit?.name !== place.barangay) {
                    state.lit?.layer.setStyle({ fillOpacity: 0, weight: 1, opacity: .55 });
                    state.lit = state.geo.barangays.find((b) => b.name === place.barangay) || null;
                    state.lit?.layer.setStyle({ fillOpacity: .22, weight: 2, opacity: .95 });
                }
                // Shown when the farm (or one of its points) is clicked: where it is, from the boundaries, not from map text.
                const label = document.createElement('div');
                label.className = 'mfd-pm-popup';
                const head = document.createElement('strong');
                head.textContent = t('textYourFarm');
                label.append(head, document.createElement('br'), place.state === 'ok' ? barangayLabel(place.barangay) : place.state === 'unknown' ? t('textBarangayUnknown') : t('textBarangayOutside'));
                if (place.state === 'ok') label.append(document.createElement('br'), t('textLian'));
                [state.polygon, ...state.markers].forEach((layer) => layer?.bindPopup(label.cloneNode(true)));
            };

            const redraw = (p) => {
                const pts = points();
                state.polygon?.remove();
                state.polygon = pts.length >= 3 ? window.L.polygon(pts.map((pt) => [pt.lat, pt.lng]), { color: '#1f5a46', fillColor: '#52b788', fillOpacity: .32, weight: 3, bubblingMouseEvents: false }).addTo(state.map) : null;
                const area = hectares(pts);
                p.modal.querySelector('[data-mfd-map-points]').textContent = pts.length;
                p.modal.querySelector('[data-mfd-map-area]').textContent = area === null ? p.text('textAreaNone') : area.toFixed(2) + ' ha';
                describe(p, pts);
                if (state.polygon && state.place.state !== 'ok') state.polygon.setStyle({ color: '#c0392b', fillColor: '#c0392b' });
            };
            /** While a point is dragged the checks run at most once per frame, so the map stays smooth. */
            const redrawSoon = (p) => { if (!state.frame) state.frame = requestAnimationFrame(() => { state.frame = 0; redraw(p); }); };
            const add = (p, pt) => {
                // A drawn dot instead of Leaflet's picture pin: it needs no image file and is easier to place on a field corner.
                const icon = window.L.divIcon({ className: 'mfd-pm-pin', iconSize: [22, 22], iconAnchor: [11, 11] });
                const marker = window.L.marker([pt.lat, pt.lng], { draggable: true, icon, keyboard: false }).addTo(state.map);
                marker.on('drag', () => redrawSoon(p));
                marker.on('dragend', () => redraw(p));
                state.markers.push(marker);
            };
            const set = (p, pts) => {
                state.markers.forEach((marker) => marker.remove());
                state.markers = [];
                pts.forEach((pt) => add(p, pt));
                redraw(p);
                // A saved farm is shown up close; with none the whole municipality is in view.
                if (pts.length >= 3) state.map.fitBounds(pts.map((pt) => [pt.lat, pt.lng]), { padding: [48, 48], maxZoom: 17 });
                else state.map.fitBounds(state.geo.bounds, { padding: [12, 12] });
            };
            const boundaries = async (p) => {
                const [lian, barangays] = await Promise.all([p.modal.dataset.lianUrl, p.modal.dataset.barangaysUrl].map(async (url) => {
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error('boundary file ' + res.status);
                    return res.json();
                }));
                return { lian: lian.features.flatMap(polygonsOf), barangays: barangays.features.map((feature) => ({ name: feature.properties.brgy_name, polygons: polygonsOf(feature), feature })) };
            };
            /** The map is built the first time the pop-up opens: it needs to be on screen to know its size. */
            const show = async (p) => {
                const box = p.modal.querySelector('#mfdFarmMap');
                p.save.disabled = true;
                if (!state.map) {
                    state.loading ||= (async () => {
                        try { await window.iClimateVisualsReady; } catch (e) { /* reported below */ }
                        try { state.geo = await boundaries(p); } catch (e) { console.error('Lian boundaries did not load:', e); }
                        // Without the boundaries a location cannot be checked, so nothing can be marked or saved.
                        if (!window.L || !state.geo) { box.textContent = p.text('textUnavailable'); box.classList.add('is-unavailable'); return; }
                        const L = window.L;
                        try { state.saved = JSON.parse(p.modal.dataset.boundary || '[]') || []; } catch (e) { state.saved = []; }
                        state.map = L.map(box, { scrollWheelZoom: false, minZoom: 11 });
                        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles &copy; Esri', maxZoom: 19 }).addTo(state.map);
                        const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 });
                        L.control.layers({ 'Satellite imagery': satellite, 'Street map': street }, null, { position: 'topright', collapsed: true }).addTo(state.map);

                        // Everything outside Lian is dimmed (still visible, for bearings); Lian's edge and its barangays are outlined.
                        const edge = state.geo.lian.map((rings) => rings[0].map(([lng, lat]) => [lat, lng]));
                        L.polygon([[[89, -179], [89, 179], [-89, 179], [-89, -179]], ...edge], { stroke: false, fillColor: '#0b1f17', fillOpacity: .5, interactive: false }).addTo(state.map);
                        state.geo.barangays.forEach((b) => { b.layer = L.geoJSON(b.feature, { interactive: false, style: { color: '#ffffff', weight: 1, opacity: .55, fillColor: '#9be3b8', fillOpacity: 0 } }).addTo(state.map); });
                        const outline = L.polygon(edge, { fill: false, color: '#ffe27a', weight: 3, opacity: .95, interactive: false }).addTo(state.map);
                        state.geo.bounds = outline.getBounds();
                        state.map.setMaxBounds(state.geo.bounds.pad(.75));

                        state.map.on('click', (event) => { add(p, { lat: event.latlng.lat, lng: event.latlng.lng }); redraw(p); });
                        p.modal.querySelector('[data-mfd-map-undo]').addEventListener('click', () => { state.markers.pop()?.remove(); redraw(p); });
                        p.modal.querySelector('[data-mfd-map-clear]').addEventListener('click', () => { state.markers.forEach((marker) => marker.remove()); state.markers = []; redraw(p); });
                    })();
                    await state.loading;
                }
                if (!state.map) return;
                state.map.invalidateSize();
                set(p, state.saved);                                    // the saved outline, never an abandoned edit
            };
            const ready = () => !!state.map && state.place.state === 'ok' && points().length >= 3;

            return make('farm-map', {
                retry: 'map', focusAfter: '#mfdSetupTitle',
                validate: (p) => problems([['boundary_coordinates', ready() ? '' : {
                    outside: p.text('textOutside'), partly: p.text('textPartly'), unknown: p.text('textUnknown'),
                }[state.place.state] || p.text('textRequired')]]),
                payload: () => ({ boundary_coordinates: points() }),
                onOpen: (p) => { show(p); },
                dirty: () => !!state.map && JSON.stringify(points()) !== JSON.stringify(state.saved),
                canSave: ready,
                saved: (p, data) => { state.saved = data.boundary_coordinates || points(); },
            });
        })();
        void farmMap;

        root.addEventListener('click', (e) => {
            const link = e.target.closest('[data-mfd-popup]');
            // Ctrl/Cmd/Shift/middle click keep their normal "open in a new tab/window" meaning.
            if (!link || !popups[link.dataset.mfdPopup] || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            e.preventDefault();
            popups[link.dataset.mfdPopup].open(link);
        });
        document.addEventListener('keydown', (e) => {
            if (!active) return;
            if (e.key === 'Escape') { e.preventDefault(); active.close(); return; }
            if (e.key !== 'Tab') return;
            // Keep keyboard focus inside the pop-up.
            const items = Array.from(active.dialog.querySelectorAll(FOCUSABLE)).filter((el) => el.getClientRects().length > 0);
            const first = items[0];
            const last = items[items.length - 1];
            if (e.shiftKey && (document.activeElement === first || !active.dialog.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && (document.activeElement === last || !active.dialog.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
        });
    };

    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-mfd-refresh]')) return refresh({ force: true });
        const retry = e.target.closest('[data-mfd-retry]');
        if (retry) refresh({ retry: retry.dataset.mfdRetry });
    });
    // A chart inside a closed "Show ..." section has no size until it is opened.
    root.addEventListener('toggle', (e) => { if (e.target.open) Object.values(charts).forEach((chart) => chart.resize()); }, true);
    setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, REFRESH_EVERY_MS);

    window.addEventListener('offline', () => showStale(t('textOffline')));
    window.addEventListener('online', () => refresh({ force: true }));

    const start = () => {
        initPopups();
        tickClock();
        tickAge();
        drawCharts();
        // The harvest model is slow, so the page opens with a placeholder and fills the card in here.
        if ($('[data-mfd-load="harvest"]')) refresh({ retry: 'harvest' });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
}
