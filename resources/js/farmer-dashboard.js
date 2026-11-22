// Farmer Home (resources/views/dashboards/farmer.blade.php): the language selector, the Refresh button, a quiet refresh
// when the server says something changed (dashboard-realtime.js), and a few later looks at the yield card alone while its estimate is still being prepared. Everything the page
// shows is saved data (FarmerDashboardService); nothing here asks a weather provider or runs a model, except Refresh,
// which also asks the server for the latest weather. All wording comes from the page (data-text-* attributes).

import { initFarmerMyFarm } from './farmer-my-farm';
import { initFarmerHomeCharts } from './farmer-home-charts';
import { initForecastSurvey } from './farmer-forecast-survey';
import { initDashboardRealtime } from './dashboard-realtime';

// While the yield card is a placeholder: look again after these waits (about 4 minutes in all), then leave it to the
// real-time refresh. The estimate is normally ready within seconds of the page being sent.
const HARVEST_CHECKS_MS = [4000, 6000, 10000, 15000, 20000, 30000, 45000, 60000, 60000];

export function initFarmerDashboard() {
    // Farmer – Naicel: the other farmer pages' scripts start here too, so the shared app.js needs no change.
    initFarmerMyFarm();
    initForecastSurvey();

    const root = document.querySelector('[data-farmer-dashboard]');
    if (!root) return;
    initFarmerHomeCharts(root);

    const $ = (selector) => root.querySelector(selector);
    const t = (name) => root.dataset[name] || '';
    const lastHtml = new Map();             // slot name -> HTML last put on the page, so an unchanged part is left alone
    let refreshing = false;

    // Data Saver or a very slow connection: the background refresh is skipped, the page keeps what it shows.
    const lowData = () => { const c = navigator.connection; return !!c && (!!c.saveData || /(^|slow-)2g$/.test(c.effectiveType || '')); };
    const announce = (message) => { const live = $('[data-mfd-live]'); if (live) live.textContent = message; };
    const showError = (message) => {
        const banner = $('[data-mfd-refresh-error]');
        if (!banner) return;
        banner.querySelector('[data-mfd-refresh-error-text]').textContent = message;
        banner.hidden = false;
    };

    /**
     * Asks the server for the parts and puts them on the page. `force` is the Refresh button (latest weather too);
     * `retry: 'harvest-check'` only asks whether the background yield estimate is saved yet.
     */
    async function refresh({ force = false, retry = null } = {}) {
        if (refreshing || (!force && !retry && lowData())) return;
        refreshing = true;
        const button = $('[data-mfd-refresh]');
        const label = $('[data-mfd-refresh-text]');
        if (force && button) { button.disabled = true; if (label) label.textContent = t('textRefreshing'); }
        try {
            const query = new URLSearchParams();
            if (force) query.set('refresh', '1');
            if (retry) query.set('retry', retry);
            const res = await fetch(root.dataset.liveUrl + (query.toString() ? '?' + query : ''), { headers: { Accept: 'application/json' }, loader: false });
            if (!res.ok) throw new Error('bad status ' + res.status);
            const data = await res.json();
            Object.entries(data.slots).forEach(([key, html]) => {
                const el = root.querySelector('[data-slot="' + key + '"]');
                if (!el) return;
                el.hidden = html.trim() === '';
                if (lastHtml.get(key) === html) return;
                lastHtml.set(key, html);
                el.innerHTML = html;
            });
            const banner = $('[data-mfd-refresh-error]');
            if (banner) banner.hidden = true;
            if (force) announce(data.updated || '');
        } catch (e) {
            console.error('Farmer dashboard refresh failed:', e);     // the farmer keeps seeing the saved data already shown
            if (force) showError(t('textFailed'));
        } finally {
            refreshing = false;
            if (force && button) { button.disabled = false; if (label) label.textContent = t('textRefresh'); }
        }
    }

    initDashboardRealtime({ root, refresh });

    // English / Filipino: saved on the farmer's account, then the page opens again in that language (same numbers).
    // Without scripts the form posts and the server sends the page back.
    root.addEventListener('change', async (e) => {
        const select = e.target.closest('[data-mfd-language] select');
        if (!select) return;
        const form = select.form;
        const saved = Array.from(select.options).find((o) => o.defaultSelected)?.value;
        if (select.value === saved) return;
        select.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ language: select.value }),
            });
            if (!res.ok) throw new Error('bad status ' + res.status);
            window.location.reload();
        } catch (err) {
            console.error('Changing the dashboard language failed:', err);
            select.value = saved;
            select.disabled = false;
            showError(t('textLanguageFailed'));
        }
    });

    root.addEventListener('click', (e) => {
        if (e.target.closest('[data-mfd-refresh]')) refresh({ force: true });
    });
    initCardDrawer(root);
    // The yield estimate is made in the background (PrepareFarmerYieldEstimate): while the card is a placeholder, ask for
    // that card alone, a little less often each time and only while the tab is visible, and stop once it is filled.
    const harvestPending = () => !!$('[data-mfd-load="harvest"]');
    let harvestTimer = null;
    let harvestStep = 0;
    const scheduleHarvest = () => {
        if (harvestTimer || !harvestPending() || harvestStep >= HARVEST_CHECKS_MS.length || document.visibilityState !== 'visible') return;
        harvestTimer = setTimeout(async () => {
            harvestTimer = null;
            await refresh({ retry: 'harvest-check' });    // skipped while another refresh is in flight, never doubled
            scheduleHarvest();
        }, HARVEST_CHECKS_MS[harvestStep++]);
    };
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            scheduleHarvest();
        } else if (harvestTimer) {
            // A hidden tab asks nothing; the same wait starts again when the farmer comes back.
            clearTimeout(harvestTimer);
            harvestTimer = null;
            harvestStep = Math.max(0, harvestStep - 1);
        }
    });
    scheduleHarvest();
}

/**
 * Farmer – Naicel (2026-11-22): a card's summary panel. A card (a key-figure tile, or the Weather / Barangay Risk /
 * Expected Yield card) carries data-fh-detail-open="<key>"; its panel is a <template data-fh-detail="<key>"> rendered
 * with the card (dashboards/farmer/detail.blade.php), so it is already on the page and stays current with the timed
 * refresh. Opening asks the server nothing. Closes with the X, a click outside the panel, or Esc; focus stays inside
 * while it is open and goes back to the card after. A Ctrl/Cmd/Shift or middle click on a tile still opens its
 * "full details" page, as a plain link would.
 */
function initCardDrawer(root) {
    const drawer = root.querySelector('[data-fh-drawer]');
    if (!drawer) return;
    // Out of the page's animated sections, so `position: fixed` is always the screen.
    document.body.appendChild(drawer);
    const panel = drawer.querySelector('[data-fh-drawer-panel]');
    const body = drawer.querySelector('[data-fh-drawer-body]');
    let opener = null;
    let closeTimer = null;

    const focusables = () => Array.from(panel.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
        .filter((el) => el.offsetParent !== null || el === document.activeElement);

    function open(key, trigger) {
        const template = root.querySelector('template[data-fh-detail="' + CSS.escape(key) + '"]');
        if (!template) return false;
        clearTimeout(closeTimer);
        body.replaceChildren(template.content.cloneNode(true));
        const title = body.querySelector('[data-fh-drawer-title]');
        if (title) title.id = 'fhDrawerTitle';
        opener = trigger;
        drawer.hidden = false;
        document.documentElement.classList.add('fh-drawer-lock');
        requestAnimationFrame(() => {
            drawer.classList.add('is-open');
            panel.focus({ preventScroll: true });
        });
        return true;
    }

    function close() {
        if (drawer.hidden) return;
        drawer.classList.remove('is-open');
        document.documentElement.classList.remove('fh-drawer-lock');
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        closeTimer = setTimeout(() => { drawer.hidden = true; body.replaceChildren(); }, reduce ? 0 : 220);
        // The card may have been redrawn by a refresh while the panel was open: focus its new copy.
        const key = opener?.dataset.fhDetailOpen;
        const target = opener?.isConnected ? opener : (key ? root.querySelector('[data-fh-detail-open="' + CSS.escape(key) + '"]') : null);
        opener = null;
        target?.focus({ preventScroll: true });
    }

    root.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-fh-detail-open]');
        if (!trigger || !root.contains(trigger)) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;   // open the page in a new tab, as before
        if (open(trigger.dataset.fhDetailOpen, trigger)) e.preventDefault();
    });

    drawer.addEventListener('click', (e) => {
        if (e.target.closest('[data-fh-drawer-close]')) close();
    });

    document.addEventListener('keydown', (e) => {
        if (drawer.hidden) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        } else if (e.key === 'Tab') {
            const items = focusables();
            if (items.length === 0) { e.preventDefault(); return; }
            const first = items[0];
            const last = items[items.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
}
