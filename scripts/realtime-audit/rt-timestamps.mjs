// Timestamp probe (isolated stack only, see README.md): real Chromium sessions per role, no reloads.
// Per page: (1) the shell clock shows Asia/Manila hh:mm:ss AM/PM, matches the real Manila time and advances every
// second; (2) every "x ago" text ([data-relative-time]) is correct for its stored time and keeps moving;
// (3) optionally, one controlled change in the clone DB (trigger.php or an artisan command in the isolated copy)
// moves a shown stored time (datetime attribute) on the open page, and how long that took.
// Usage: node rt-timestamps.mjs [caseName ...]
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { formatRelative } from '../../resources/js/relative-time.js';

const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR || new URL('../..', import.meta.url).pathname;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || 'RtAudit!2026';
const WAIT_MS = Number(process.env.RT_WAIT_MS || 20000);
const php = (args) => execFileSync('php', args, { cwd: APP_DIR, encoding: 'utf8' });
const manila = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

const change = {
    trigger: (scenario) => () => { php([`${HERE}trigger.php`, scenario, `TS${Date.now().toString(36)}`]); },
    artisan: (...args) => () => { php(['artisan', ...args]); },
};

// [name, role, path, change | null]
const CASES = [
    // --force: a real Open-Meteo fetch even inside the 10-minute interval (as the Refresh button does); without it a
    // second run within 10 minutes correctly saves nothing and no stored time moves.
    ['farmer-dashboard', 'farmer', '/farmer/dashboard', change.artisan('iclimate:refresh-barangay-weather', '--force')],
    ['farmer-notifications', 'farmer', '/notifications', null],
    // The whole yield warm-up (all farms + 19 barangays, minutes): needs the ML service and RT_WAIT_MS ≥ 600000.
    ['farmer-heat-map-yield', 'farmer', '/heat-map?range=week&layer=yield', change.artisan('iclimate:warm-farmer-yields')],
    ['farmer-heat-map', 'farmer', '/heat-map', change.artisan('iclimate:refresh-barangay-weather', '--force')],
    ['farmer-advisories', 'farmer', '/planting-advisories', null],
    ['farmer-weather-predictions', 'farmer', '/weather-predictions', null],
    ['mao-dashboard', 'mao', '/mao/dashboard', change.trigger('user')],
    ['mao-heat-map', 'mao', '/heat-map', null],
    ['mao-community-feed', 'mao', '/community-feed', change.trigger('post')],
    ['mao-weather-predictions', 'mao', '/weather-predictions', null],
    ['it-dashboard', 'it', '/admin/dashboard', change.trigger('user')],
    ['it-system-health', 'it', '/system-health', null],
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

const readTimes = (page) => page.evaluate(() => [...document.querySelectorAll('[data-relative-time]')]
    // Visible ones only: a hidden panel (e.g. the Heat Map's Yield layer summary) has its own, genuinely older time.
    .filter((el) => el.getClientRects().length > 0)
    .map((el) => ({ iso: el.getAttribute('datetime'), text: el.textContent.trim(), lang: el.closest('[lang]')?.getAttribute('lang') || 'en' })));

async function runCase(browser, [name, role, path, mutate], sessions) {
    sessions[role] ??= await login(browser, role);
    const { page } = sessions[role];
    const response = await page.goto(BASE + path, { waitUntil: 'load' });
    await page.waitForTimeout(2500); // let the clock start and realtime subscribe
    await page.evaluate(() => { window.__tsMark = 1; });
    const result = { name, status: response?.status(), realtime: await page.evaluate(() => window.iClimateRealtime?.state || 'none') };

    // (1) Clock: three readings ~1 s apart, each compared with the real Manila time at that instant.
    const clock = page.locator('[data-live-clock]').first();
    if (await clock.count()) {
        const readings = [];
        for (let i = 0; i < 3; i += 1) {
            const shown = (await clock.textContent()).trim();
            readings.push({ shown, real: manila.format(new Date()) });
            await page.waitForTimeout(1050);
        }
        const toSec = (t) => { const m = t.match(/^(\d{2}):(\d{2}):(\d{2}) ([AP]M)$/); if (!m) return NaN; return ((Number(m[1]) % 12) + (m[4] === 'PM' ? 12 : 0)) * 3600 + Number(m[2]) * 60 + Number(m[3]); };
        const format = readings.every((r) => /^\d{2}:\d{2}:\d{2} [AP]M$/.test(r.shown));
        const advances = readings[0].shown !== readings[1].shown && readings[1].shown !== readings[2].shown;
        const maxSkew = Math.max(...readings.map((r) => Math.abs(toSec(r.shown) - toSec(r.real))));
        result.clock = { pass: format && advances && maxSkew <= 1, readings: readings.map((r) => r.shown), maxSkewSec: maxSkew };
    } else {
        result.clock = { pass: null, note: 'no clock on page' };
    }

    // (2) Relative times: each text equals the rule applied to its stored time, now; and a seconds/minutes text moves.
    const first = await readTimes(page);
    const firstAt = Date.now();
    const wrong = first.filter((t) => ![formatRelative(t.iso, firstAt, t.lang), formatRelative(t.iso, firstAt - 1000, t.lang)].includes(t.text));
    let moved = null;
    const ticking = first.find((t) => /second|segundo/.test(t.text));
    if (ticking) {
        await page.waitForTimeout(2100);
        const later = await readTimes(page);
        moved = later.some((t) => t.iso === ticking.iso && t.text !== ticking.text);
    }
    result.relative = { count: first.length, wrong: wrong.slice(0, 3), ticked: moved, sample: first.slice(0, 3).map((t) => `${t.text} (${t.iso})`) };

    // (3) A real change moves a shown stored time on the open page.
    if (mutate) {
        const before = new Set(first.map((t) => t.iso));
        const t0 = Date.now();
        mutate();
        const tCommitDone = Date.now();
        try {
            const handle = await page.waitForFunction((old) => [...document.querySelectorAll('[data-relative-time]')]
                .filter((el) => el.getClientRects().length > 0).map((el) => el.getAttribute('datetime')).find((iso) => iso && !old.includes(iso)), [...before], { timeout: WAIT_MS, polling: 50 });
            const seenAt = Date.now();
            const iso = await handle.jsonValue();
            result.change = {
                pass: await page.evaluate(() => window.__tsMark === 1),
                newStoredTime: iso,
                storedTimeNotInFuture: Date.parse(iso) <= Date.now() + 1000,
                delayAfterChangeMs: seenAt - tCommitDone,
                changeTookMs: tCommitDone - t0,
            };
            // A refresh can pass through an in-between state (weather saved, rating not yet redone); record where the
            // open page settles.
            await page.waitForTimeout(8000);
            result.change.settledStoredTimes = [...new Set((await readTimes(page)).map((t) => t.iso))];
        } catch {
            result.change = { pass: false, note: `no shown stored time changed within ${WAIT_MS} ms`, noReload: await page.evaluate(() => window.__tsMark === 1) };
        }
    }

    result.pass = result.status === 200 && result.clock.pass !== false && wrong.length === 0 && moved !== false && (result.change?.pass ?? true);
    return result;
}

const only = process.argv.slice(2);
const browser = await chromium.launch();
const sessions = {};
try {
    for (const c of CASES.filter((c) => only.length === 0 || only.includes(c[0]))) {
        try {
            console.log(JSON.stringify(await runCase(browser, c, sessions)));
        } catch (error) {
            console.log(JSON.stringify({ name: c[0], pass: false, error: String(error).slice(0, 300) }));
        }
    }
} finally {
    await browser.close();
}
