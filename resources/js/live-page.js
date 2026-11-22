/**
 * Live list pages. A page marks the parts that show stored records with
 *   <div data-live-region="records" data-live-areas="climate,rice">…</div>
 * and those parts are re-read from the server when that data changes, without reloading the page:
 *
 * - Immediately: the server's dashboard.changed signal (Reverb; it carries only area names, no data) for one of the
 *   page's areas. The page is fetched again under the viewer's own permissions and only the marked parts change.
 * - Fallback: while the live connection is off or down, a quiet check every minute when the tab is visible, and
 *   always when the tab is shown again, so a missed signal never leaves the page stale for long.
 *
 * Nothing is replaced while someone is using it (typing, a selected row, an open menu); it catches up right after.
 */
import { initDashboardRealtime } from './dashboard-realtime.js';

const POLL_MS = 60_000;
const BUSY_RETRY_MS = 4_000;

/** Shared by the live list pages and the Advisories page: realtime signal, slow fallback poll, catch-up on return. */
export function initLiveRefresh({ root, refresh, areas = null }) {
    if (!root || root.dataset.liveRefreshBound === 'true') return;
    root.dataset.liveRefreshBound = 'true';
    initDashboardRealtime({ root, refresh, areas });

    let last = Date.now();
    const run = () => { last = Date.now(); void refresh(); };
    const live = () => window.iClimateRealtime?.state === 'connected';
    window.setInterval(() => {
        if (document.visibilityState === 'visible' && !live() && Date.now() - last >= POLL_MS) run();
    }, 15_000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && Date.now() - last >= 15_000) run();
    });
}

/** A form here holds a change that is not saved yet (a picked option, a ticked box, typed text): never replace it. */
const hasUnsavedInput = (element) => [...element.querySelectorAll('form')].some((form) => [...form.elements].some((field) => {
    if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button' || field.disabled) return false;
    if (field.type === 'checkbox' || field.type === 'radio') return field.checked !== field.defaultChecked;
    if (field.tagName === 'SELECT') {
        const options = [...field.options];
        // No option marked selected in the markup: the browser shows the first one (single choice) or none.
        if (!options.some((option) => option.defaultSelected)) return field.multiple ? options.some((o) => o.selected) : field.selectedIndex > 0;
        return options.some((option) => option.selected !== option.defaultSelected);
    }
    if (field.type === 'file') return field.files?.length > 0;
    return 'defaultValue' in field && field.value !== field.defaultValue;
}));

/** Someone is in the middle of something inside this element (typing, an open menu or row form): leave it alone for now. */
export function isBusy(element) {
    if (hasUnsavedInput(element)) return true;
    const active = document.activeElement;
    if (active && element.contains(active) && active.matches('input:not([type="checkbox"]):not([type="radio"]), select, textarea, [contenteditable="true"]')) return true;
    return Boolean(element.querySelector('.dropdown-menu.show, .modal.show, [aria-expanded="true"], details[open] form, details[open][data-live-keep], [data-live-keep].show, input[type="checkbox"][data-bulk-select]:checked'));
}

export function initLivePage() {
    const regions = [...document.querySelectorAll('[data-live-region]')];
    if (regions.length === 0) return;
    const areas = [...new Set(regions.flatMap((r) => (r.dataset.liveAreas || '').split(',').map((a) => a.trim()).filter(Boolean)))];
    let retry = null;
    const serverHtml = new WeakMap();
    // Newer markup for a region that was busy when it arrived: applied as soon as it is free, without asking the
    // server again (a form with an unsaved change can stay busy for minutes).
    const pending = new Map();

    const apply = () => {
        pending.forEach((html, current) => {
            if (!current.isConnected) { pending.delete(current); return; }
            if (isBusy(current)) return;
            pending.delete(current);
            current.innerHTML = html;
            serverHtml.set(current, html);
            window.iClimateEnhance?.(current);
            // Page scripts that draw inside a region (charts) draw again from the new markup.
            current.dispatchEvent(new CustomEvent('iclimate:live-region-updated', { bubbles: true }));
        });
        if (pending.size && !retry) retry = window.setTimeout(() => { retry = null; apply(); }, BUSY_RETRY_MS);
    };

    const refresh = async () => {
        const response = await fetch(window.location.href, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', loader: false,
        }).catch(() => null);
        // Signed out or the page is gone: keep what is shown; the next normal navigation handles it.
        if (!response?.ok || response.redirected) return;
        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
        document.querySelectorAll('[data-live-region]').forEach((current) => {
            const next = fresh.querySelector(`[data-live-region="${CSS.escape(current.dataset.liveRegion)}"]`);
            if (!next) return;
            if (next.innerHTML === serverHtml.get(current)) { pending.delete(current); return; }
            pending.set(current, next.innerHTML);
        });
        apply();
    };

    // Remember the server's own markup, so scripts that decorate a region (labels, scroll boxes) never count as a change.
    regions.forEach((region) => serverHtml.set(region, region.innerHTML));
    initLiveRefresh({ root: regions[0], refresh, areas });
}
