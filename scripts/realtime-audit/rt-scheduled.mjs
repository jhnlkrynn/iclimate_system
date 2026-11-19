// Scheduled data (2026-11-11): run the real scheduled commands in the isolated copy (clone database, live Open-Meteo /
// PAGASA reads) while pages are open, and time (a) the command, (b) the open page's re-read after the signal, and
// (c) the Heat Map's applied update. No reload. Isolated stack only (README.md).
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || creds.password;
if (!APP_DIR) throw new Error('RT_APP_DIR (the isolated copy) is required');
const db = execFileSync('php', ['artisan', 'tinker', '--execute=echo DB::connection()->getDatabaseName();'], { cwd: APP_DIR, encoding: 'utf8' }).trim().split('\n').pop();
if (!db.includes('rt_audit')) throw new Error(`refusing: ${db} is not the disposable clone`);

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
    return page;
}

const browser = await chromium.launch();
const farmer = await login(browser, 'farmer');
const mao = await login(browser, 'mao');
const farmer2 = await login(browser, 'farmer2');
await farmer.goto(`${BASE}/heat-map`, { waitUntil: 'load' });
await mao.goto(`${BASE}/analysis/weather`, { waitUntil: 'load' });
await farmer2.goto(`${BASE}/weather-predictions`, { waitUntil: 'load' });
await Promise.all([farmer.waitForTimeout(3000), mao.waitForTimeout(3000)]);
for (const p of [farmer, mao, farmer2]) await p.evaluate(() => { window.__rtMark = 1; window.__regionUpdates = 0; document.addEventListener('iclimate:live-region-updated', () => { window.__regionUpdates++; }); });

// Page re-reads (the live refresh fetches its own URL) after the command starts.
const reads = { farmer: [], mao: [], farmer2: [] };
farmer2.on('request', (r) => { if (r.method() === 'GET' && r.url().startsWith(`${BASE}/weather-predictions`) && r.resourceType() === 'fetch') reads.farmer2.push(Date.now()); });
farmer.on('request', (r) => { if (r.method() === 'GET' && r.url().startsWith(`${BASE}/heat-map`) && r.resourceType() === 'fetch') reads.farmer.push(Date.now()); });
mao.on('request', (r) => { if (r.method() === 'GET' && r.url().startsWith(`${BASE}/analysis/weather`) && r.resourceType() === 'fetch') reads.mao.push(Date.now()); });

for (const command of ['iclimate:refresh-barangay-weather', 'iclimate:refresh-weather-cache']) {
    reads.farmer.length = 0; reads.mao.length = 0; reads.farmer2.length = 0;
    const before = await farmer.evaluate(() => document.querySelector('[data-heat-map]')?.dataset.liveUpdatedAt || '');
    const t0 = Date.now();
    let output = '';
    try { output = execFileSync('php', ['artisan', command], { cwd: APP_DIR, encoding: 'utf8', timeout: 180000 }); } catch (e) { output = `ERROR ${e.message}`; }
    const t1 = Date.now();
    await farmer.waitForTimeout(8000);
    const after = await farmer.evaluate(() => document.querySelector('[data-heat-map]')?.dataset.liveUpdatedAt || '');
    console.log(JSON.stringify({
        command, command_ms: t1 - t0, output: output.trim().split('\n').slice(-2).join(' | ').slice(0, 220),
        heatMap: { reads: reads.farmer.length, firstReadAfterCommandEndMs: reads.farmer.length ? reads.farmer[0] - t1 : null, applied: after !== before, appliedAtMs: after && after !== before ? Number(after) - t1 : null },
        weatherAnalysis: { reads: reads.mao.length, firstReadAfterCommandEndMs: reads.mao.length ? reads.mao[0] - t1 : null, regionUpdates: await mao.evaluate(() => window.__regionUpdates) },
        weatherPredictions: { reads: reads.farmer2.length, firstReadAfterCommandEndMs: reads.farmer2.length ? reads.farmer2[0] - t1 : null, regionUpdates: await farmer2.evaluate(() => window.__regionUpdates) },
        noReload: await farmer.evaluate(() => window.__rtMark === 1) && await mao.evaluate(() => window.__rtMark === 1) && await farmer2.evaluate(() => window.__rtMark === 1),
    }));
}
await browser.close();
