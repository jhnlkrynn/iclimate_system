// One server-synchronised Philippine clock for the signed-in application shell, plus the "x seconds ago" text
// beside stored data. Data cards keep their own stored "Updated" times (the real database or source time);
// this module only reports now and how long ago those stored times were. It never changes a stored time.
import { applyRelativeTimes } from './relative-time.js';

const TIME_ZONE = 'Asia/Manila';
const FORMATTER = new Intl.DateTimeFormat('en-US', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
});

let timer = null;
let startTimeout = null;
let initialized = false;
let lifecycleBound = false;
let serverOffsetMs = 0;

/** Server time in ms, corrected for a wrong device clock (from <body data-server-now>). */
export function serverNowMs() {
    return Date.now() + serverOffsetMs;
}

/**
 * How far the device clock is from the server's. The server wrote its time while it built the page, i.e. between
 * this request being sent and its first byte arriving. Inside that window the device clock agrees with the server
 * (offset 0); a slow page must not set the clock back by its own render time (seen 2026-11-11: Weather Predictions
 * ran ~1.5 s slow). Outside it, the device clock is wrong and is corrected by the distance to the window.
 */
export function offsetFromWindow(serverMs, sentMs, firstByteMs, toleranceMs = 250) {
    if (serverMs < sentMs - toleranceMs) return serverMs - sentMs;
    if (serverMs > firstByteMs + toleranceMs) return serverMs - firstByteMs;
    return 0;
}

function clockOffset(serverMs) {
    const nav = window.performance?.getEntriesByType?.('navigation')?.[0];
    if (!nav || !nav.responseStart) return serverMs - Date.now();
    const origin = window.performance.timeOrigin;
    return offsetFromWindow(serverMs, origin + nav.requestStart, origin + nav.responseStart);
}

function update() {
    const now = serverNowMs();
    const text = FORMATTER.format(new Date(now));
    document.querySelectorAll('[data-live-clock]').forEach((element) => {
        if (element.isConnected && element.textContent !== text) element.textContent = text;
    });
    applyRelativeTimes(document, now, document.documentElement.lang);
}

function stop() {
    if (startTimeout !== null) window.clearTimeout(startTimeout);
    if (timer !== null) window.clearInterval(timer);
    startTimeout = null;
    timer = null;
    initialized = false;
}

export function initLiveClock() {
    if (initialized) return;
    initialized = true;
    const source = document.body?.dataset.serverNow;
    const parsed = source ? Date.parse(source) : NaN;
    serverOffsetMs = Number.isNaN(parsed) ? 0 : clockOffset(parsed);

    update();
    if (timer !== null) return;

    // Align the first interval to the next wall-clock second, then update once per second.
    const delay = 1000 - (Date.now() % 1000);
    startTimeout = window.setTimeout(() => {
        startTimeout = null;
        update();
        timer = window.setInterval(update, 1000);
    }, delay);

    if (!lifecycleBound) {
        window.addEventListener('pagehide', stop);
        window.addEventListener('pageshow', initLiveClock);
        // Background tabs throttle timers; show the right time the moment the tab is visible again.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') update();
        });
        // A live region that was just replaced shows its fresh times at once, not up to a second later.
        document.addEventListener('iclimate:live-region-updated', update);
        document.addEventListener('iclimate:times-changed', update);
        lifecycleBound = true;
    }
}
