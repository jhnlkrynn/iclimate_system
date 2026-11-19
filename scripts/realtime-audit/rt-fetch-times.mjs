// Fetch-time probe (isolated stack only): the farmer's weather and farm-risk "Updated" times, and the Heat Map's,
// follow every successful fetch on open pages (no reload, no re-read needed), also when the values did not change.
// Usage: RT_APP_DIR=… RT_CREDS=… node rt-fetch-times.mjs
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const APP_DIR = process.env.RT_APP_DIR;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS));
const artisan = (...a) => execFileSync('php', ['artisan', ...a], { cwd: APP_DIR, encoding: 'utf8' });
const browser = await chromium.launch();
async function open(path) {
    const page = await (await browser.newContext()).newPage();
    await page.goto(`${BASE}/#login`); await page.locator('#login-email').fill(creds.farmer); await page.locator('#login-password').fill(process.env.RT_PASSWORD || 'RtAudit!2026');
    await Promise.all([page.waitForURL((u) => !u.hash.includes('login') && u.pathname !== '/'), page.locator('[data-login-form] button[type="submit"]').click()]);
    await page.goto(BASE + path, { waitUntil: 'load' }); await page.waitForTimeout(3000);
    const reads = []; page.on('request', (r) => { if (['fetch', 'xhr'].includes(r.resourceType())) reads.push(Date.now()); });
    await page.evaluate(() => { window.__mark = 1; });
    return { page, reads };
}
const stamp = (page, kind) => page.evaluate((kind) => { const s = [...document.querySelectorAll(`[data-weather-fetched="${kind}"]`)].find((e) => e.getClientRects().length > 0); if (!s) return null;
    return { iso: s.querySelector('[data-relative-time]').getAttribute('datetime'), clock: s.querySelector('[data-updated-clock]').textContent.trim(), ago: s.querySelector('[data-relative-time]').textContent.trim() }; }, kind);
const home = await open('/farmer/dashboard'); const map = await open('/heat-map');
const out = [];
for (const [name, kind, cmd, pages] of [
    ['current #1', 'current', ['iclimate:refresh-weather-cache'], [['home', home]]],
    ['current #2 (same values)', 'current', ['iclimate:refresh-weather-cache'], [['home', home]]],
    ['barangays #1', 'barangays', ['iclimate:refresh-barangay-weather', '--force'], [['home', home], ['map', map]]],
    ['barangays #2 (same values)', 'barangays', ['iclimate:refresh-barangay-weather', '--force'], [['home', home], ['map', map]]],
]) {
    const before = {}; for (const [n, p] of pages) before[n] = await stamp(p.page, kind);
    const readsBefore = Object.fromEntries(pages.map(([n, p]) => [n, p.reads.length]));
    const t0 = Date.now(); const text = artisan(...cmd); const t1 = Date.now();
    const row = { name, commandMs: t1 - t0 };
    for (const [n, p] of pages) {
        let seen = null;
        try { await p.page.waitForFunction(([kind, old]) => { const s = [...document.querySelectorAll(`[data-weather-fetched="${kind}"]`)].find((e) => e.getClientRects().length > 0); return s && s.querySelector('[data-relative-time]').getAttribute('datetime') !== old; }, [kind, before[n]?.iso], { timeout: 15000, polling: 50 }); seen = Date.now(); } catch {}
        const after = await stamp(p.page, kind);
        await p.page.waitForTimeout(1200);
        row[n] = { from: before[n]?.iso, to: after?.iso, clock: after?.clock, moved: seen !== null, msAfterCommandEnd: seen ? seen - t1 : null, msAfterStoredTime: seen && after ? seen - Date.parse(after.iso) : null,
            notFuture: after ? Date.parse(after.iso) <= Date.now() : null, pageReads: p.reads.length - readsBefore[n], noReload: await p.page.evaluate(() => window.__mark === 1), agoTicks: (await stamp(p.page, kind))?.ago };
    }
    if (pages.length === 2) row.sameTimeOnBoth = Date.parse(row.home.to) === Date.parse(row.map.to);
    out.push(row); console.log(JSON.stringify(row));
}
// A reload must show the same times (the server overlay), not older ones.
const homeBefore = { current: await stamp(home.page, 'current'), barangays: await stamp(home.page, 'barangays') };
await home.page.reload({ waitUntil: 'load' }); await home.page.waitForTimeout(1500);
console.log(JSON.stringify({ name: 'reload keeps times', current: (await stamp(home.page, 'current'))?.iso === homeBefore.current?.iso, barangays: (await stamp(home.page, 'barangays'))?.iso === homeBefore.barangays?.iso }));
await browser.close();
