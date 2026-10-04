import assert from 'node:assert/strict';
import test from 'node:test';
import { initAdvisoryModal } from '../../resources/js/farmer-advisories.js';

// Small unit doubles for the refresh handler; these are not browser/visual tests.
function setup(t, { desktop = true, auto = false } = {}) {
    const saved = new Map(['document', 'window', 'fetch', 'FormData', 'CustomEvent'].map(key => [key, globalThis[key]]));
    t.after(() => saved.forEach((value, key) => { globalThis[key] = value; }));
    const listeners = [];
    const calls = [];
    const status = { hidden: true, textContent: '' };
    const button = { innerHTML: '<svg></svg><span>Refresh PAGASA</span>', disabled: false, setAttribute() {} };
    const form = { action: 'http://localhost/planting-advisories/refresh-pagasa', dataset: {}, querySelector: () => button };
    const details = [{ dataset: {}, open: !desktop }, { dataset: { forceOpen: 'true' }, open: false }];
    const updated = { querySelectorAll: () => details };
    let replaced = 0;
    let autoPromise;
    const modal = { dataset: {}, querySelector: () => ({}), addEventListener() {} };
    globalThis.document = {
        body: { appendChild() {} },
        querySelector: selector => ({ '[data-fad-modal]': modal, '[data-fad-refresh-status]': status,
            '.fad': { replaceWith() { replaced++; } }, '[data-fad-auto-refresh]': auto ? {} : null,
            '[data-fad-refresh]': form })[selector],
        getElementById: () => ({}),
        addEventListener: (type, callback, capture) => listeners.push({ type, callback, capture }),
        createElement: () => ({ content: { querySelector: () => updated } }),
    };
    globalThis.window = { location: { href: 'http://localhost/planting-advisories?target_barangay=San%20Diego' },
        matchMedia: () => ({ matches: desktop }), dispatchEvent() {} };
    globalThis.FormData = class {};
    globalThis.CustomEvent = class {};
    globalThis.fetch = async (url, options) => {
        calls.push({ url, options });
        return { ok: true, status: 200, json: async () => ({ message: 'Advisories refreshed.' }), text: async () => '<div class="fad"></div>' };
    };
    const event = () => ({ target: { closest: () => form }, defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; } });
    form.requestSubmit = () => { autoPromise = listeners.find(item => item.type === 'submit').callback(event()); };
    initAdvisoryModal();
    return { calls, status, button, form, details, event, submit: listeners.find(item => item.type === 'submit'),
        replaced: () => replaced, autoPromise: () => autoPromise };
}

test('refresh prevents shared native navigation and updates the current filtered page', async t => {
    const f = setup(t);
    const event = f.event();
    const pending = f.submit.callback(event);
    assert.equal(f.submit.capture, true);
    assert.equal(event.defaultPrevented, true);
    assert.equal(f.button.disabled, true);
    assert.equal(f.status.hidden, false);
    await pending;
    assert.deepEqual(f.calls.map(call => call.options.method ?? 'GET'), ['POST', 'GET']);
    assert.equal(f.calls[1].url, window.location.href);
    assert.ok(f.calls.every(call => call.options.loader === false));
    assert.equal(f.replaced(), 1);
    assert.equal(f.status.textContent, 'Advisories refreshed.');
    assert.equal(f.button.disabled, false);
    assert.match(f.button.innerHTML, /<svg>/);
});

test('rapid repeated submit is prevented without a second POST', async t => {
    const f = setup(t);
    const pending = f.submit.callback(f.event());
    const repeated = f.event();
    await f.submit.callback(repeated);
    assert.equal(repeated.defaultPrevented, true);
    await pending;
    assert.equal(f.calls.filter(call => call.options.method === 'POST').length, 1);
});

test('an HTTP 503 updates the page failure state instead of throwing away saved guidance', async t => {
    const f = setup(t, { desktop: false });
    globalThis.fetch = async (url, options) => {
        f.calls.push({ url, options });
        return { ok: options.method !== 'POST', status: options.method === 'POST' ? 503 : 200,
            json: async () => ({ message: 'Advisory information is temporarily unavailable.' }), text: async () => '<div class="fad"></div>' };
    };
    await f.submit.callback(f.event());
    assert.equal(f.replaced(), 1);
    assert.equal(f.status.textContent, 'Advisory information is temporarily unavailable.');
    assert.deepEqual(f.details.map(detail => detail.open), [false, true]);
});

test('request errors remain visible and re-enable the refresh button', async t => {
    const f = setup(t);
    globalThis.fetch = async () => { throw new Error('Network unavailable'); };
    await f.submit.callback(f.event());
    assert.equal(f.status.textContent, 'Network unavailable');
    assert.equal(f.button.disabled, false);
    assert.equal(f.replaced(), 0);
});

test('a first or stale visit starts exactly one refresh', async t => {
    const f = setup(t, { auto: true });
    await f.autoPromise();
    assert.equal(f.calls.filter(call => call.options.method === 'POST').length, 1);
    assert.equal(f.replaced(), 1);
});
