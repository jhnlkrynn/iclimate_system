import { chromium } from 'playwright';
import { execFileSync, execSync, spawn } from 'node:child_process';
import { readFileSync, openSync } from 'node:fs';
const HERE = new URL('.', import.meta.url).pathname;
const APP_DIR = process.env.RT_APP_DIR || new URL('../..', import.meta.url).pathname;
const BASE = process.env.RT_BASE || 'http://127.0.0.1:8100';
const creds = JSON.parse(readFileSync(process.env.RT_CREDS || `${HERE}creds.json`));
const PASSWORD = process.env.RT_PASSWORD || 'RtAudit!2026';
const php = (args, opts = {}) => execFileSync('php', args, { cwd: APP_DIR, encoding: 'utf8', ...opts });

const log=(k,v)=>console.log(JSON.stringify({step:k,...v, at:new Date().toISOString().slice(11,19)}));
const reverbPid=()=>execSync(`lsof -tiTCP:${process.env.RT_REVERB_PORT || 8091} -sTCP:LISTEN || true`).toString().trim();
const b=await chromium.launch(); const c=await b.newContext(); const p=await c.newPage();
const states=[]; await p.exposeFunction('rtState', s=>states.push([Date.now(), s]));
await p.addInitScript(()=>window.addEventListener('iclimate:realtime-state', e=>window.rtState(e.detail.state)));
await p.goto(BASE+'/#login'); await p.fill('#login-email',creds.farmer); await p.fill('#login-password',PASSWORD);
await Promise.all([p.waitForURL(u=>!u.hash.includes('login')&&u.pathname!=='/'), p.click('[data-login-form] button[type="submit"]')]);
await p.goto(BASE+'/planting-advisories'); await p.waitForTimeout(3000);
await p.evaluate(()=>{window.__rtMark=1});
log('start',{state: await p.evaluate(()=>window.iClimateRealtime.state)});
execSync(`kill ${reverbPid()}`); await p.waitForTimeout(3000);
log('reverb-stopped',{state: await p.evaluate(()=>window.iClimateRealtime.state), pid: reverbPid()||'none'});
// Change while Reverb is down.
const tok='RTOUT'+Date.now().toString(36).toUpperCase();
const t=JSON.parse(php([`${HERE}trigger.php`,'advisory',tok]).trim().split('\n').pop());
log('trigger-while-down',{commit_ms:t.t-t.t0});
let seen=null; try { await p.waitForFunction(t=>document.body.innerText.includes(t), tok, {timeout:90000, polling:250}); seen=Date.now()-t.t0; } catch {}
log('fallback-poll',{seen_ms:seen, reloaded: !(await p.evaluate(()=>window.__rtMark===1)), state: await p.evaluate(()=>window.iClimateRealtime.state)});
// Restart Reverb; wait for the page to reconnect by itself.
const out=openSync(process.env.RT_REVERB_LOG || '/dev/null','a'); const r=spawn('php',['artisan','reverb:start','--host=127.0.0.1',`--port=${process.env.RT_REVERB_PORT || 8091}`],{cwd:APP_DIR, detached:true, stdio:['ignore',out,out]}); r.unref();
const restartAt=Date.now();
let reconnected=null; try { await p.waitForFunction(()=>window.iClimateRealtime.state==='connected', null, {timeout:120000, polling:250}); reconnected=Date.now()-restartAt; } catch {}
log('reverb-restarted',{reconnected_ms:reconnected, state: await p.evaluate(()=>window.iClimateRealtime.state)});
// After reconnection a change is live again.
const tok2='RTBACK'+Date.now().toString(36).toUpperCase();
await p.waitForTimeout(1500);
const t2=JSON.parse(php([`${HERE}trigger.php`,'advisory',tok2]).trim().split('\n').pop());
let seen2=null; try { await p.waitForFunction(t=>document.body.innerText.includes(t), tok2, {timeout:20000, polling:100}); seen2=Date.now()-t2.t0; } catch {}
log('live-after-recovery',{seen_ms:seen2});
log('states',{states: states.map(([t,s])=>s).join(' > ')});
await b.close();
