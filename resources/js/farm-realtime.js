/**
 * Private farm invalidation listener. Farm data is always refetched through the authorized My Farm route;
 * WebSocket payloads contain metadata only.
 */
export function initFarmRealtime() {
    let root = document.querySelector('[data-my-farm]');
    if (!root || root.dataset.farmRealtimeBound === 'true' || !window.Echo) return;

    const userId = Number(root.dataset.farmRealtimeUserId || 0);
    const farmId = Number(root.dataset.farmRealtimeFarmId || 0);
    if (!userId) return;
    root.dataset.farmRealtimeBound = 'true';

    window.__iClimateFarmEvents ??= new Set();
    const seen = window.__iClimateFarmEvents;
    let refreshing = false;
    let queued = false;
    let wasConnected = window.iClimateRealtime?.state === 'connected';

    const refresh = async () => {
        if (refreshing) { queued = true; return; }
        refreshing = true;
        try {
            const response = await fetch(window.location.href, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('farm refresh failed');
            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const next = parsed.querySelector('[data-my-farm]');
            if (!next) throw new Error('farm page fragment missing');
            root.replaceWith(next);
            root = next;
            initFarmRealtime();
        } catch (error) {
            console.error('Farm realtime refresh failed:', error);
        } finally {
            refreshing = false;
            if (queued) { queued = false; void refresh(); }
        }
    };

    const onChange = (event, payload = {}) => {
        const normalized = String(event || '').replace(/^\./, '');
        if (normalized !== 'farm.changed') return;
        const id = String(payload.event_id || '');
        if (id && seen.has(id)) return;
        if (id) {
            seen.add(id);
            if (seen.size > 100) seen.delete(seen.values().next().value);
        }
        void refresh();
    };

    const bind = (channel) => {
        if (!channel) return;
        if (typeof channel.listenToAll === 'function') channel.listenToAll(onChange);
        else channel.listen('.farm.changed', (payload) => onChange('farm.changed', payload));
    };

    bind(window.Echo.private(`user.${userId}`));
    if (farmId) bind(window.Echo.private(`farm.${farmId}`));

    window.addEventListener('iclimate:realtime-state', (event) => {
        const state = event.detail?.state || 'disabled';
        if (state === 'connected' && !wasConnected) void refresh();
        wasConnected = state === 'connected';
    });

    // Existing farm pages had no polling endpoint; retain safe recovery polling while WebSockets are unavailable.
    if (!window.__iClimateFarmPollingBound) {
        window.__iClimateFarmPollingBound = true;
        window.setInterval(() => {
            if (document.visibilityState !== 'visible' || window.iClimateRealtime?.state === 'connected') return;
            const activeRoot = document.querySelector('[data-my-farm]');
            if (activeRoot && typeof activeRoot.__icFarmRealtimeRefresh === 'function') void activeRoot.__icFarmRealtimeRefresh();
        }, 300000);
    }
    root.__icFarmRealtimeRefresh = refresh;
}
