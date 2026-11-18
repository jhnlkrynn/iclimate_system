// Isolated real-time probe: real browsers per role on the isolated app (:8100 / Reverb :8091 / clone DB).
// For each case: open the page, mark the window (a full reload loses the mark), make one change in the clone DB
// (trigger.php), and time how long the OPEN page takes to show it. Usage: node rt-observe.mjs [caseName ...]
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR || new URL('../..', import.meta.url).pathname;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || 'RtAudit!2026';
const php = (args, opts = {}) => execFileSync('php', args, { cwd: APP_DIR, encoding: 'utf8', ...opts });


const WAIT_MS = Number(process.env.RT_WAIT_MS || 20000);

const trigger = (scenario, tok) => JSON.parse(php([`${HERE}trigger.php`, scenario, tok]).trim().split('\n').pop());

// 'converge': the server's own render before vs after the change gives the words the change adds; the open page
// passes when it shows all of them (without a reload). No new words → the change is not visible on this page.
const converge = 'converge';
const has = (tok) => `document.body.innerText.includes(${JSON.stringify(tok)})`;

// [name, role, path, scenario, predicate(tok) as JS expression evaluated in the page]
export const CASES = [
    ['advisories:farmer', 'farmer', '/planting-advisories', 'advisory', has],
    ['advisories:mao-pending', 'mao', '/planting-advisories?show=pending', 'advisory-pending', has],
    ['farmer-dashboard:advisory', 'farmer', '/farmer/dashboard', 'advisory', has],
    ['notifications-bell:farmer', 'farmer', '/farmer/dashboard', 'notification',
        () => `(() => { const b = document.querySelector('[data-ic-notif-badge]'); return b && Number(b.textContent.trim()) > Number(window.__rtBefore || 0); })()`],
    ['bell:advisory-published', 'farmer', '/planting-advisories', 'publish-notify',
        () => `(() => { const b = document.querySelector('[data-ic-notif-badge]'); return b && Number(b.textContent.trim()) > Number(window.__rtBefore || 0); })()`],
    ['notifications-page:farmer', 'farmer', '/notifications', 'notification', has],
    ['announcements:farmer', 'farmer', '/planting-advisories', 'post', has],
    ['community-feed:farmer', 'farmer', '/community-feed', 'post', has],
    ['my-farm:farmer', 'farmer', '/farmer/my-farm', 'farm', has],
    ['calendar:farmer', 'farmer', '/calendar', 'schedule', converge],
    ['mao-dashboard:new-farmer', 'mao', '/mao/dashboard', 'user', () => 'window.__rtChanged === true'],
    ['it-dashboard:new-user', 'it', '/admin/dashboard', 'user', () => 'window.__rtChanged === true'],
    ['users-index:it', 'it', '/users', 'user', converge],
    ['farmer-approvals:mao', 'mao', '/mao/farmer-approvals', 'user-pending', converge],
    ['climate-records:mao', 'mao', '/climate-records', 'climate', has],
    ['rice-productions:mao', 'mao', '/rice-productions', 'rice', converge],
    ['field-reports:mao', 'mao', '/field-reports', 'field-report', has],
    ['farmer-profiles:mao', 'mao', '/farmer-profiles', 'user', converge],
    ['reports:mao', 'mao', '/reports', 'farmer-profile', converge],
    ['weather-predictions:mao', 'mao', '/weather-predictions', 'climate', converge],
    ['analysis-weather:mao', 'mao', '/analysis/weather', 'climate', converge],
    ['heat-map:farmer', 'farmer', '/heat-map', 'advisory', converge],
    ['farmer-dashboard:schedule', 'farmer', '/farmer/dashboard', 'schedule', converge],
    ['my-farm:schedule', 'farmer', '/farmer/my-farm', 'schedule', converge],
    ['mao-dashboard:advisory', 'mao', '/mao/dashboard', 'advisory-pending', () => 'window.__rtChanged === true'],
    // Added 2026-11-11: the remaining pages.
    ['barangays:mao', 'mao', '/mao/barangays', 'barangay', converge],
    ['heat-map-flood:farmer', 'farmer', '/heat-map?range=week&layer=flood', 'barangay', converge],
    ['heat-map-flood:mao', 'mao', '/heat-map?range=week&layer=flood', 'barangay', converge],
    ['planner:mao', 'mao', '/mao/planner', 'planner', has],
    ['activity-log:mao', 'mao', '/mao/activity-log', 'activity-log', has],
    ['alerts:mao', 'mao', '/mao/alerts', 'alert', has],
    ['alerts-read:mao', 'mao', '/mao/alerts', 'alert-read', converge],
    ['community-feed:mao-post', 'mao', '/community-feed', 'post', has],
    ['community-feed:comment', 'mao', '/community-feed', 'comment', has],
    ['maintenance:it', 'it', '/admin/maintenance', 'maintenance', has],
    ['system-logs:it', 'it', '/system-logs', 'system-log', converge],
    ['impact:mao', 'mao', '/analysis/impact', 'impact', has],
    ['forecast-history:mao', 'mao', '/analysis/forecast', 'forecast-request', converge],
    ['feedback:it', 'it', '/admin/feedback', 'survey', has],
    ['system-health:it', 'it', '/system-health', 'health', converge],
    ['rice-varieties:it', 'it', '/rice-varieties', 'variety', has],
    ['farmer-profiles:mao-new', 'mao', '/farmer-profiles', 'farmer-profile', has],
    ['farm-associations:mao', 'mao', '/mao/farm-associations', 'association-request', has],
    ['my-reports:farmer', 'farmer', '/farmer/my-reports', 'farm', converge],
    ['historical-rice:mao', 'mao', '/historical-rice-productions', 'historical-rice', converge],
];

async function login(browser, role) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE}/#login`, { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(creds[role]);
    await page.locator('#login-password').fill(PASSWORD);
    await Promise.all([
        page.waitForURL((url) => !url.hash.includes('login') && url.pathname !== '/', { timeout: 30000 }),
        page.locator('[data-login-form] button[type="submit"]').click(),
    ]);
    return { context, page };
}

const words = (html) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    // Ticking times ("5 seconds ago", the clock) differ between any two renders; they are not the change under test.
    doc.querySelectorAll('script,style,template,noscript,[data-relative-time],[data-live-clock]').forEach((n) => n.remove());
    const main = doc.querySelector('main') || doc.body;
    return [...new Set((main.textContent || '').split(/\s+/).filter((w) => w.length > 1))];
};

async function runConverge(page, scenario, tok) {
    const before = await page.evaluate(async (src) => { const words = eval(src); return words(await (await fetch(location.href, { headers: { Accept: 'text/html' } })).text()); }, words.toString());
    const t = trigger(scenario, tok);
    const after = await page.evaluate(async (src) => { const words = eval(src); return words(await (await fetch(location.href, { headers: { Accept: 'text/html' } })).text()); }, words.toString());
    const beforeSet = new Set(before);
    const added = after.filter((w) => !beforeSet.has(w) && !/^\d{1,2}:\d{2}$/.test(w) && !/^(AM|PM)$/.test(w));
    if (added.length === 0) return { t, added, seenAt: null, visible: false };
    let seenAt = null;
    try {
        await page.waitForFunction((added) => { const text = (document.querySelector('main') || document.body).textContent; return added.every((w) => text.includes(w)); }, added, { timeout: WAIT_MS, polling: 100 });
        seenAt = Date.now();
    } catch { /* not seen */ }
    return { t, added, seenAt, visible: true };
}

async function runCase(browser, [name, role, path, scenario, predicate], sessions) {
    sessions[role] ??= await login(browser, role);
    const { page } = sessions[role];
    const tok = `RT${Date.now().toString(36).toUpperCase()}${Math.floor(Math.random() * 1e4)}`;
    const response = await page.goto(BASE + path, { waitUntil: 'load' });
    const status = response?.status();
    await page.waitForTimeout(2500); // let realtime subscribe
    const realtime = await page.evaluate(() => window.iClimateRealtime?.state || 'none');
    await page.evaluate(() => {
        window.__rtMark = 1;
        const b = document.querySelector('[data-ic-notif-badge]');
        window.__rtBefore = b ? Number(b.textContent.trim()) || 0 : 0;
        window.__rtChanged = false;
        const root = document.querySelector('main') || document.body;
        new MutationObserver(() => { window.__rtChanged = true; }).observe(root, { childList: true, subtree: true, characterData: true });
        // Ignore churn from the first moments (spinners, clocks): only changes after the trigger count.
        setTimeout(() => { window.__rtChanged = false; }, 900);
    });
    await page.waitForTimeout(1200);
    let already = false; let t; let seenAt = null; let added = null; let visible = true;
    if (predicate === converge) {
        ({ t, added, seenAt, visible } = await runConverge(page, scenario, tok));
        added = added.slice(0, 6);
    } else {
        already = await page.evaluate(predicate(tok)).catch(() => false);
        t = trigger(scenario, tok);
        try {
            await page.waitForFunction(predicate(tok), null, { timeout: WAIT_MS, polling: 100 });
            seenAt = Date.now();
        } catch { /* not seen */ }
    }
    const reloaded = !(await page.evaluate(() => window.__rtMark === 1).catch(() => false));
    let afterReload = null;
    if (!seenAt && predicate === has) {
        await page.reload({ waitUntil: 'load' });
        afterReload = await page.evaluate(predicate(tok)).catch(() => false);
    }
    return {
        name, path, status, realtime, already,
        delay_ms: seenAt ? seenAt - t.t0 : null,
        reloaded, afterReload, added, result: !visible ? 'NOT-VISIBLE-HERE' : (seenAt && !reloaded && !already ? 'UPDATED' : (already ? 'INVALID' : 'NO-UPDATE')),
    };
}

const only = process.argv.slice(2);
const browser = await chromium.launch();
const sessions = {};
const results = [];
for (const c of CASES.filter((c) => only.length === 0 || only.includes(c[0]))) {
    try { results.push(await runCase(browser, c, sessions)); }
    catch (e) { results.push({ name: c[0], error: String(e.message).slice(0, 200) }); }
    console.log(JSON.stringify(results.at(-1)));
}
await browser.close();
