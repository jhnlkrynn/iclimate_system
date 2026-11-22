/**
 * Dashboard invalidation listener. Events contain no dashboard data; every refresh remains authorized by Laravel.
 */
const ROLE_CHANNELS = { Farmer: 'farmer', 'MAO Personnel': 'mao', farmer: 'farmer', mao: 'mao' };

/**
 * Areas only list and admin pages show (added 2026-11-11). A dashboard (no `areas`) ignores a change that touches
 * nothing else, so e.g. an audit-log line or a planner entry never makes a whole dashboard read itself again.
 */
const LIST_ONLY_AREAS = new Set(['community-activity', 'notification-reads', 'planner', 'mao-activity', 'varieties',
    'maintenance', 'model-feedback', 'system-logs', 'system-health', 'weather-analysis', 'yield-requests']);

// A missed Reverb event must not leave a dashboard stale until its next full-page visit. This is deliberately
// slower than the live clock: it only re-reads the prepared dashboard while the tab is visible and disconnected.
const FALLBACK_POLL_MS = 60_000;

/**
 * `areas` (optional): refresh only for changes to these CacheScope areas (a list page ignores e.g. a weather refresh).
 * weather.changed counts as the 'weather' area. Without `areas` every change refreshes (the dashboards).
 */
/**
 * weather.fetched: a weather fetch succeeded (also one whose values did not change). The event carries only the real
 * fetch time; the matching "Updated" labels ([data-weather-fetched="current" | "barangays"]) move to it without reading
 * anything again. A label only ever moves forward, and only to a time the server sent.
 */
const latestFetch = {};
const CLOCK = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', hour12: true });
const FULL = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });

export function applyFetchTimes(scope = document) {
    let moved = false;
    Object.entries(latestFetch).forEach(([kind, at]) => {
        scope.querySelectorAll(`[data-weather-fetched="${kind}"]`).forEach((stamp) => {
            const time = stamp.querySelector('[data-relative-time]');
            const shown = time ? Date.parse(time.getAttribute('datetime') || '') : NaN;
            if (!time || !(at > shown)) return;
            time.setAttribute('datetime', new Date(at).toISOString());
            time.setAttribute('title', `${FULL.format(new Date(at))} (Philippine time)`);
            const clock = stamp.querySelector('[data-updated-clock]');
            if (clock) clock.textContent = CLOCK.format(new Date(at));
            moved = true;
        });
    });
    // live-clock.js rewrites the "x ago" text at once (and keeps it ticking every second).
    if (moved) document.dispatchEvent(new CustomEvent('iclimate:times-changed'));
}

export function noteWeatherFetched(payload = {}) {
    const at = Date.parse(payload.fetched_at || '');
    const kind = String(payload.kind || '');
    // A time from the future would be a clock fault somewhere: never show it.
    if (!kind || Number.isNaN(at) || at > Date.now() + 5 * 60 * 1000) return;
    if (!(latestFetch[kind] >= at)) latestFetch[kind] = at;
    applyFetchTimes();
}

export function initDashboardRealtime({ root, refresh, areas = null }) {
    if (!root || typeof refresh !== 'function' || root.dataset.dashboardRealtimeBound === 'true') return;
    const userId = Number(root.dataset.dashboardUserId || document.body.dataset.userId || 0);
    const role = root.dataset.dashboardRole || ROLE_CHANNELS[document.body.dataset.userRole] || '';
    const wanted = Array.isArray(areas) && areas.length ? new Set(areas) : null;
    if (!window.Echo || !userId) return;
    root.dataset.dashboardRealtimeBound = 'true';

    const seen = new Set();
    let refreshing = false;
    let queued = false;
    let wasConnected = window.iClimateRealtime?.state === 'connected';
    let lastRefreshAt = Date.now();

    const runRefresh = async () => {
        if (refreshing) { queued = true; return; }
        lastRefreshAt = Date.now();
        refreshing = true;
        try { await refresh({ realtime: true }); } finally {
            refreshing = false;
            // A part that was just re-read may have been rendered a moment before the newest fetch.
            applyFetchTimes();
            if (queued) { queued = false; void runRefresh(); }
        }
    };

    const onChange = (payload = {}, event = 'dashboard.changed') => {
        const changed = event === 'weather.changed' ? ['weather'] : (Array.isArray(payload.areas) ? payload.areas : []);
        // Yield completion is scoped to the authenticated user channel, so it is safe to refresh the farmer's
        // authorized dashboard when a prediction changes. The event contains status metadata only.
        if (event === 'prediction.changed') {
            void runRefresh();
            return;
        }
        if (wanted) {
            if (!changed.some((area) => wanted.has(area))) return;
        } else if (changed.length && changed.every((area) => LIST_ONLY_AREAS.has(area))) {
            return;
        }
        const id = String(payload.change_id || payload.event_id || '');
        if (id && seen.has(id)) return;
        if (id) {
            seen.add(id);
            if (seen.size > 100) seen.delete(seen.values().next().value);
        }
        void runRefresh();
    };

    // Reverb delivers the event on the authenticated Pusher channel, but some
    // browser/Pusher combinations do not invoke dotted event-name bindings
    // registered through `listen()`. Echo's all-events binding receives the
    // same authenticated channel event reliably; filter it narrowly here and
    // keep the change_id guard for duplicate user/role deliveries.
    const bindDashboardChanges = (channel) => {
        if (typeof channel.listenToAll === 'function') {
            channel.listenToAll((event, payload) => {
                const normalized = String(event || '').replace(/^\./, '');
                if (normalized === 'weather.fetched') noteWeatherFetched(payload);
                if (['dashboard.changed', 'weather.changed', 'prediction.changed'].includes(normalized)) onChange(payload, normalized);
            });
            return;
        }
        channel.listen('.weather.fetched', noteWeatherFetched);
        channel.listen('.dashboard.changed', (payload) => onChange(payload, 'dashboard.changed'));
        channel.listen('.weather.changed', (payload) => onChange(payload, 'weather.changed'));
        channel.listen('.prediction.changed', (payload) => onChange(payload, 'prediction.changed'));
    };

    bindDashboardChanges(window.Echo.private(`user.${userId}`));
    if (role) bindDashboardChanges(window.Echo.private(`dashboard.${role}`));
    window.addEventListener('iclimate:realtime-state', (event) => {
        const state = event.detail?.state || 'disabled';
        if (state === 'connected' && !wasConnected) void runRefresh();
        wasConnected = state === 'connected';
    });

    // Reverb is the fast path; this is the bounded recovery path for outages, missed subscriptions, and sleeping
    // browser connections. It reads the authorized live endpoint only while visible and never runs every second.
    window.setInterval(() => {
        if (document.visibilityState === 'visible'
            && window.iClimateRealtime?.state !== 'connected'
            && Date.now() - lastRefreshAt >= FALLBACK_POLL_MS) {
            void runRefresh();
        }
    }, 15_000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible'
            && window.iClimateRealtime?.state !== 'connected'
            && Date.now() - lastRefreshAt >= 15_000) {
            void runRefresh();
        }
    });
}

// The MAO dashboard swaps its own root after a server-rendered refresh. Keep a
// small bridge so the replacement root can subscribe again without reloading
// the application shell or importing the module a second time.
// (Guarded so the module can also be imported by the Node unit tests in tests/Frontend, which have no window.)
if (typeof window !== 'undefined') {
    window.iClimateInitDashboardRealtime = initDashboardRealtime;
}
