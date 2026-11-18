// Status-only prediction updates. Authorized HTTP responses remain the source
// of truth; Reverb never carries inputs, results, or failure details.
const STATUS_RANK = { requested: 0, pending: 0, queued: 0, processing: 1, completed: 2, failed: 2 };

const normalizeStatus = (status) => status === 'pending' || status === 'queued' ? 'requested' : String(status || '');

function labelsFor(element) {
    try { return JSON.parse(element.closest('[data-prediction-realtime]')?.dataset.predictionLabels || '{}'); } catch (_) { return {}; }
}

function renderStatus(element, run) {
    const status = normalizeStatus(run.status);
    const current = normalizeStatus(element.dataset.predictionStatus);
    if ((STATUS_RANK[current] ?? -1) > (STATUS_RANK[status] ?? -1)) return;
    element.dataset.predictionStatus = status;

    const labels = labelsFor(element);
    const label = labels[status] || ({ requested: 'Requested', processing: 'Processing', completed: 'Completed', failed: 'Failed' }[status] || status);
    const text = element.querySelector('[data-prediction-status-text]');
    if (text) text.textContent = label;
    const detail = element.querySelector('[data-prediction-status-detail]');
    if (detail) detail.textContent = status === 'failed' ? (run.error || labels.failedDetail || 'The prediction could not be completed.') : (labels[`${status}Detail`] || 'This status is current.');
    element.classList.toggle('alert-info', status === 'requested' || status === 'processing');
    element.classList.toggle('alert-success', status === 'completed');
    element.classList.toggle('alert-danger', status === 'failed');

    const rowText = element.matches('tr') ? element.querySelector('[data-prediction-live-status]') : null;
    if (rowText) {
        rowText.textContent = label;
        rowText.classList.toggle('text-bg-secondary', status === 'requested' || status === 'processing');
        rowText.classList.toggle('text-bg-success', status === 'completed');
        rowText.classList.toggle('text-bg-danger', status === 'failed');
    }
    element.dispatchEvent(new CustomEvent('iclimate:prediction-updated', { bubbles: true, detail: run }));
}

export function initPredictionRealtime() {
    if (!window.Echo || document.documentElement.dataset.predictionRealtimeBound === 'true') return;
    const targets = [...document.querySelectorAll('[data-analytics-run]')];
    const rows = [...document.querySelectorAll('[data-prediction-status-url]')];
    if (!targets.length && !rows.length) return;
    document.documentElement.dataset.predictionRealtimeBound = 'true';

    const seen = new Set();
    const requestVersions = new WeakMap();
    let connected = window.iClimateRealtime?.state === 'connected';
    const refetch = async (element) => {
        const version = (requestVersions.get(element) || 0) + 1;
        requestVersions.set(element, version);
        const url = element.dataset.analyticsRun || element.dataset.predictionStatusUrl;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', loader: false });
            if (!response.ok || requestVersions.get(element) !== version) return;
            renderStatus(element, await response.json());
        } catch (_) { /* polling remains the recovery path */ }
    };
    const refetchAll = () => [...targets, ...rows].forEach((element) => void refetch(element));
    const onChange = (payload = {}) => {
        const eventId = String(payload.event_id || '');
        if (eventId && seen.has(eventId)) return;
        if (eventId) {
            seen.add(eventId);
            if (seen.size > 100) seen.delete(seen.values().next().value);
        }
        const reference = String(payload.reference || '');
        const matchingRows = rows.filter((row) => String(row.dataset.predictionReference) === reference);
        const matchingTargets = targets.filter((target) => target.dataset.analyticsRun?.endsWith(`/${reference}`));
        (matchingRows.length || matchingTargets.length ? [...matchingRows, ...matchingTargets] : [...targets, ...rows]).forEach((element) => void refetch(element));
    };

    const channel = window.Echo.private(`user.${document.body.dataset.userId || ''}`);
    if (typeof channel.listenToAll === 'function') {
        channel.listenToAll((event, payload) => {
            if (String(event || '').replace(/^\./, '') === 'prediction.changed') onChange(payload);
        });
    } else channel.listen('.prediction.changed', onChange);

    window.addEventListener('iclimate:realtime-state', (event) => {
        const state = event.detail?.state || 'disabled';
        if (state === 'connected' && !connected) refetchAll();
        connected = state === 'connected';
    });
    window.addEventListener('iclimate:prediction-poll', (event) => {
        if (event.detail?.element && event.detail?.run) renderStatus(event.detail.element, event.detail.run);
    });
    if (rows.length) window.setInterval(() => { if (document.visibilityState === 'visible') refetchAll(); }, 8000);
    window.iClimatePredictionApply = renderStatus;
}
