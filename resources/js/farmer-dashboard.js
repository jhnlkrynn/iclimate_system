// Farmer Home (resources/views/dashboards/farmer.blade.php): the language selector, the Refresh button, a quiet refresh
// every 5 minutes, and one later look at the yield card while its estimate is still being prepared. Everything the page
// shows is saved data (FarmerDashboardService); nothing here asks a weather provider or runs a model, except Refresh,
// which also asks the server for the latest weather. All wording comes from the page (data-text-* attributes).

import { initFarmerMyFarm } from './farmer-my-farm';
import { initFarmerHomeCharts } from './farmer-home-charts';
import { initForecastSurvey } from './farmer-forecast-survey';

const REFRESH_EVERY_MS = 300000;
const HARVEST_CHECK_MS = 30000;

export function initFarmerDashboard() {
    // The other farmer pages' scripts start here too, so the shared app.js needs no change (Farmer – Naicel).
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
            // The live URL may already carry ?farm= (the farm selector).
            const url = new URL(root.dataset.liveUrl, window.location.href);
            if (force) url.searchParams.set('refresh', '1');
            if (retry) url.searchParams.set('retry', retry);
            const res = await fetch(url, { headers: { Accept: 'application/json' }, loader: false });
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

    // The farm selector (shown with two or more farms): Home opens again for the chosen farm.
    root.addEventListener('change', (e) => {
        const select = e.target.closest('[data-fh-farm-select]');
        if (select) select.form.submit();
    });
    setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, REFRESH_EVERY_MS);

    // The yield estimate is made in the background (PrepareFarmerYieldEstimate): while the card is a placeholder, look
    // once more later, reading only what is saved.
    if ($('[data-mfd-load="harvest"]')) setTimeout(() => { if ($('[data-mfd-load="harvest"]')) refresh({ retry: 'harvest-check' }); }, HARVEST_CHECK_MS);
}
