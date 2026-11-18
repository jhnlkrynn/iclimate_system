// Open conversation (2026-11-11): MAO and farmer both have the same conversation open; each reply must appear on the
// other's open page without a reload. Also: the live connection is dropped in one browser (polling fallback), then
// restored (catch-up), and every message must show exactly once. Isolated stack only (README.md).
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || creds.password;
const FARMER_ID = process.env.RT_FARMER_ID || '1';
const out = (name, data) => console.log(JSON.stringify({ name, ...data }));
const tok = () => `RT${Date.now().toString(36).toUpperCase()}${Math.floor(Math.random() * 1e4)}`;

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

const send = (page, conversationId, body) => page.evaluate(async ({ conversationId, body }) => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const r = await fetch(`/messages/${conversationId}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams({ body }) });
    return r.status;
}, { conversationId, body });

// Copies inside the open thread only (the conversation list beside it previews the newest message too).
const count = (page, text) => page.evaluate((text) => { const list = document.getElementById('threadList'); return [...(document.querySelector('main') || document.body).querySelectorAll('*')].filter((n) => n.children.length === 0 && n.textContent.includes(text) && !list?.contains(n)).length; }, text);

async function waitShown(page, text, timeout = 20000) {
    const t0 = Date.now();
    try { await page.waitForFunction((t) => (document.querySelector('main') || document.body).innerText.includes(t), text, { timeout, polling: 100 }); return Date.now() - t0; } catch { return null; }
}

const browser = await chromium.launch();
const mao = await login(browser, 'mao');
const farmer = await login(browser, 'farmer');

// The MAO opens (or creates) the conversation with the farmer.
const opened = await mao.evaluate(async (recipient) => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const r = await fetch('/messages/open', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams({ recipient_id: recipient }) });
    return r.json();
}, FARMER_ID);
const id = opened.conversation_id;
await mao.goto(`${BASE}/messages/${id}`, { waitUntil: 'load' });
await farmer.goto(`${BASE}/messages/${id}`, { waitUntil: 'load' });
await Promise.all([mao.waitForTimeout(2500), farmer.waitForTimeout(2500)]);
await farmer.evaluate(() => { window.__rtMark = 1; });
await mao.evaluate(() => { window.__rtMark = 1; });
const state = async (p) => p.evaluate(() => window.iClimateRealtime?.state || 'none');
out('setup', { conversation: id, mao: await state(mao), farmer: await state(farmer) });

// 1. MAO -> farmer over the live connection.
let t = tok();
let status = await send(mao, id, `From MAO ${t}`);
out('mao-to-farmer', { status, farmerSawInMs: await waitShown(farmer, t) });

// 2. Farmer -> MAO.
t = tok();
status = await send(farmer, id, `From farmer ${t}`);
out('farmer-to-mao', { status, maoSawInMs: await waitShown(mao, t) });

// 3. Reverb itself stops (the isolated one, RT_REVERB_PORT): the open conversation keeps up through its fallback poll.
// (Disconnecting one browser is not enough: the app reconnects it at once.)
const { execSync, spawn } = await import('node:child_process');
const { openSync } = await import('node:fs');
const port = process.env.RT_REVERB_PORT || '8091';
const reverbPid = () => execSync(`lsof -tiTCP:${port} -sTCP:LISTEN || true`).toString().trim();
if (!process.env.RT_APP_DIR || port === '8080') throw new Error('refusing: only the isolated Reverb (RT_APP_DIR, not 8080) is stopped');
execSync(`kill ${reverbPid()}`);
await farmer.waitForTimeout(3000);
const downState = await state(farmer);
t = tok();
const offlineTok = t;
status = await send(mao, id, `While offline ${t}`);
const offlineMs = await waitShown(farmer, t, 30000);
out('fallback-poll', { farmerState: downState, status, farmerSawInMs: offlineMs });

// 4. Reverb back: the browser reconnects by itself, the next message arrives live again, and nothing shows twice.
const log = openSync(process.env.RT_REVERB_LOG || '/dev/null', 'a');
spawn('php', ['artisan', 'reverb:start', '--host=127.0.0.1', `--port=${port}`], { cwd: process.env.RT_APP_DIR, detached: true, stdio: ['ignore', log, log] }).unref();
const restartAt = Date.now();
await farmer.waitForFunction(() => window.iClimateRealtime?.state === 'connected', null, { timeout: 30000 }).catch(() => {});
t = tok();
status = await send(mao, id, `After reconnect ${t}`);
const reconnectMs = await waitShown(farmer, t);
await farmer.waitForTimeout(9000);   // one more poll cycle: a duplicate would appear now
out('reconnect', { reconnectedAfterRestartMs: Date.now() - restartAt - (reconnectMs ?? 0) - 9000, farmerState: await state(farmer), status, farmerSawInMs: reconnectMs, copies: await count(farmer, t),
    copiesOfOfflineMessage: await count(farmer, offlineTok),
    noReload: await farmer.evaluate(() => window.__rtMark === 1) && await mao.evaluate(() => window.__rtMark === 1) });

await browser.close();
