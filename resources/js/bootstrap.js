import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const realtimeState = {
    state: 'disconnected',
    changedAt: new Date().toISOString(),
};

window.iClimateRealtime = realtimeState;

const setRealtimeState = (state) => {
    realtimeState.state = state;
    realtimeState.changedAt = new Date().toISOString();
    window.dispatchEvent(new CustomEvent('iclimate:realtime-state', { detail: { ...realtimeState } }));
};

window.Echo = null;

const realtimeKey = import.meta.env.VITE_REVERB_APP_KEY;
const realtimeHost = import.meta.env.VITE_REVERB_HOST;
const realtimeEnabled = document.querySelector('meta[name="iclimate-realtime"]')?.getAttribute('content') === '1';

if (realtimeKey && realtimeHost && realtimeEnabled) {
    Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: Echo }, { default: Pusher }]) => {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: realtimeKey,
            wsHost: realtimeHost,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });

    const connection = window.Echo.connector?.pusher?.connection;
    let reconnectTimer = null;
    let reconnectAttempts = 0;
    const clearReconnect = () => {
        if (reconnectTimer) window.clearTimeout(reconnectTimer);
        reconnectTimer = null;
        reconnectAttempts = 0;
    };
    // Keep trying while the page is open (1, 2, 4, 8, then every 15 s). Giving up after 3 tries left a page waiting
    // ~50 s on pusher-js's own timer after Reverb came back; pages poll meanwhile, so a missed signal is never lost.
    const scheduleReconnect = () => {
        if (!connection || reconnectTimer) return;
        const delay = Math.min(1000 * (2 ** reconnectAttempts), 15000);
        reconnectTimer = window.setTimeout(() => {
            reconnectTimer = null;
            reconnectAttempts += 1;
            // A connection interruption can leave Pusher "connecting" or "unavailable", where its own runner waits
            // ~50 s and connect() does nothing. Reset that attempt, then let its normal channel registry resubscribe
            // on `connected`.
            if (['connecting', 'unavailable'].includes(connection.state)) connection.disconnect();
            if (connection.state !== 'connected') connection.connect();
        }, delay);
    };
    const reconnect = () => {
        if (!connection || connection.state === 'connected') return;
        clearReconnect();
        scheduleReconnect();
    };
    connection?.bind('state_change', ({ current }) => {
        if (current === 'connected') {
            clearReconnect();
            setRealtimeState('connected');
        } else if (current === 'connecting') {
            setRealtimeState('connecting');
        } else if (current === 'unavailable') {
            setRealtimeState('unavailable');
            scheduleReconnect();
        } else if (current === 'disconnected') {
            setRealtimeState('disconnected');
            scheduleReconnect();
        } else {
            setRealtimeState('reconnecting');
            scheduleReconnect();
        }
    });
    connection?.bind('error', () => setRealtimeState('failed'));
    realtimeState.reconnect = reconnect;
    // Back online or back on the tab: try at once instead of waiting for the next timer.
    window.addEventListener('online', reconnect);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') reconnect(); });
    });
} else {
    setRealtimeState('disabled');
}

// Route axios through the global iClimate loader too (public/js/iclimate-loader.js).
// Pass `loader: false` or `loader: 'Saving...'` in a request config to override.
window.axios.interceptors.request.use((config) => {
    config.iclLoaderToken = window.iClimateLoader?.beginRequest(config.url, config.method, config.loader);
    return config;
});
window.axios.interceptors.response.use(
    (response) => {
        window.iClimateLoader?.hide(response.config?.iclLoaderToken);
        return response;
    },
    (error) => {
        window.iClimateLoader?.hide(error.config?.iclLoaderToken);
        return Promise.reject(error);
    },
);
