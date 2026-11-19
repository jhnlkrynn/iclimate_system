// Flow checks on the isolated stack: messages between sessions, barangay isolation, channel authorization,
// duplicate refreshes per change, and the busy guard. Usage: node rt-flows.mjs
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR || new URL('../..', import.meta.url).pathname;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || 'RtAudit!2026';
const php = (args, opts = {}) => execFileSync('php', args, { cwd: APP_DIR, encoding: 'utf8', ...opts });


const trigger = (s, tok) => JSON.parse(php([`${HERE}trigger.php`, s, tok]).trim().split('\n').pop());
const tok = () => `RT${Date.now().toString(36).toUpperCase()}${Math.floor(Math.random() * 1e4)}`;
const out = (k, v) => console.log(JSON.stringify({ check: k, ...v }));

async function login(browser, role) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${BASE}/#login`);
    await page.fill('#login-email', creds[role]);
    await page.fill('#login-password', PASSWORD);
    await Promise.all([page.waitForURL((u) => !u.hash.includes('login') && u.pathname !== '/', { timeout: 30000 }), page.click('[data-login-form] button[type="submit"]')]);
    return page;
}

const browser = await chromium.launch();
const farmer = await login(browser, 'farmer');
const farmer2 = await login(browser, 'farmer2');
const mao = await login(browser, 'mao');

// 1. Channel authorization: who may subscribe to what (status of /broadcasting/auth).
const auth = async (page, channel) => page.evaluate(async (channel) => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const r = await fetch('/broadcasting/auth', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams({ socket_id: '1234.5678', channel_name: channel }) });
    return r.status;
}, channel);
await farmer.goto(`${BASE}/farmer/dashboard`);
await mao.goto(`${BASE}/mao/dashboard`);
out('auth', {
    'farmer→private-user.1 (own)': await auth(farmer, 'private-user.1'),
    'farmer→private-user.2 (other)': await auth(farmer, 'private-user.2'),
    'farmer→private-dashboard.farmer': await auth(farmer, 'private-dashboard.farmer'),
    'farmer→private-dashboard.mao': await auth(farmer, 'private-dashboard.mao'),
    'farmer→private-dashboard.it': await auth(farmer, 'private-dashboard.it'),
    'farmer→private-farm.1 (own)': await auth(farmer, 'private-farm.1'),
    'farmer→private-farm.2 (other)': await auth(farmer, 'private-farm.2'),
    'farmer→private-barangay.matabungkay (own)': await auth(farmer, 'private-barangay.matabungkay'),
    'farmer→private-barangay.kapito (other)': await auth(farmer, 'private-barangay.kapito'),
    'mao→private-dashboard.mao': await auth(mao, 'private-dashboard.mao'),
    'mao→private-dashboard.it': await auth(mao, 'private-dashboard.it'),
});

// 2. Barangay isolation + duplicate refreshes: a Matabungkay advisory with both farmers on Advisories.
for (const p of [farmer, farmer2]) {
    await p.goto(`${BASE}/planting-advisories`);
    await p.evaluate(() => { window.__fetches = 0; });
    p.on('request', (r) => { if (r.url().includes('/planting-advisories') && r.resourceType() === 'fetch') p.__fetches = (p.__fetches || 0) + 1; });
    p.__fetches = 0;
}
await farmer.waitForTimeout(2500);
let t = tok();
trigger('advisory', t);
await farmer.waitForTimeout(4000);
out('barangay-isolation', {
    'farmer (Matabungkay) sees': await farmer.evaluate((t) => document.body.innerText.includes(t), t),
    'farmer2 (Kapito) sees': await farmer2.evaluate((t) => document.body.innerText.includes(t), t),
    'farmer page fetches for 1 change': farmer.__fetches,
    'farmer2 page fetches for 1 change': farmer2.__fetches,
});

// 3. Busy guard: farmer types in the Climate-style filter of a live list (MAO Climate Records search box) while a change lands.
await mao.goto(`${BASE}/climate-records`);
await mao.waitForTimeout(2500);
const search = mao.locator('input[name="search"]').first();
if (await search.count()) {
    await search.click();
    await search.type('typing');
    t = tok();
    trigger('climate', t);
    await mao.waitForTimeout(2500);
    const whileTyping = await mao.evaluate((t) => ({ shown: document.body.textContent.includes(t), value: document.querySelector('input[name="search"]').value, focused: document.activeElement?.name }), t);
    await mao.evaluate(() => document.activeElement.blur());
    await mao.waitForTimeout(6000);
    out('busy-guard', { whileTyping, afterBlurShown: await mao.evaluate((t) => document.body.textContent.includes(t), t) });
} else {
    out('busy-guard', { skipped: 'search input is outside the live region or missing' });
}

// 4. Messages: MAO sends to the farmer through the real route; the farmer's open Messages page shows it.
await farmer.goto(`${BASE}/messages`);
await farmer.waitForTimeout(2500);
t = tok();
const t0 = Date.now();
const sent = await mao.evaluate(async (t) => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const r = await fetch('/messages', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams({ recipient_id: '1', body: `Hello ${t}` }) });
    return r.status;
}, t);
let seen = null;
try { await farmer.waitForFunction((t) => document.body.innerText.includes(t), t, { timeout: 20000, polling: 100 }); seen = Date.now() - t0; } catch {}
out('messages', { sendStatus: sent, farmerSawInMs: seen });

await browser.close();
