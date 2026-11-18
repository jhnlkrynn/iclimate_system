// ML-side real-time probe (isolated stack + worker + ML service): an open page shows a new yield result / registry
// metric without a reload. Usage: node rt-ml.mjs [yield-request] [registry]
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS));
const trigger = (s) => JSON.parse(execFileSync('php', [`${HERE}ml-trigger.php`, s, 'ML'], { cwd: APP_DIR, encoding: 'utf8' }).trim().split('\n').pop());

async function login(browser, role) {
    const page = await (await browser.newContext()).newPage();
    await page.goto(`${BASE}/#login`); await page.locator('#login-email').fill(creds[role]); await page.locator('#login-password').fill('RtAudit!2026');
    await Promise.all([page.waitForURL((u) => !u.hash.includes('login') && u.pathname !== '/'), page.locator('[data-login-form] button[type="submit"]').click()]);
    return page;
}
const CASES = {
    'yield-request': ['mao', '/analysis/forecast', (o) => `(() => { const t = (document.querySelector('main') || document.body).innerText; return ${JSON.stringify(o.total_tons || '__none__')} !== '__none__' && t.includes(${JSON.stringify(o.total_tons || '')}); })()`],
    registry: ['it', '/system-health/model-registry', (o) => `(document.querySelector('main') || document.body).innerText.includes(${JSON.stringify(o.r2.toFixed(3).replace(/0+$/, ''))})`],
};
const browser = await chromium.launch();
try {
    for (const name of (process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(CASES))) {
        const [role, path, predicate] = CASES[name];
        const page = await login(browser, role);
        const res = await page.goto(BASE + path, { waitUntil: 'load' });
        await page.waitForTimeout(2500);
        await page.evaluate(() => { window.__mark = 1; });
        const o = trigger(name);
        const committed = Date.now();
        let seen = null;
        try { await page.waitForFunction(predicate(o), null, { timeout: 60000, polling: 100 }); seen = Date.now(); } catch {}
        console.log(JSON.stringify({ name, path, status: res.status(), realtime: await page.evaluate(() => window.iClimateRealtime?.state || 'none'), change: o,
            delay_ms: seen ? seen - committed : null, reloaded: (await page.evaluate(() => window.__mark)) !== 1, result: seen ? 'UPDATED' : 'NO-UPDATE' }));
    }
} finally { await browser.close(); }
