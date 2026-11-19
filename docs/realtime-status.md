# Real-time status by module (verified 2026-11-11, second pass; timestamps pass the same evening)

How each page stays current, how fast, and the evidence. Measurements come from an isolated stack: a copy of the
app on :8100, Reverb on :8091, a real queue worker, the Python ML service on :8011, and the disposable MariaDB clone
`iclimate_rt_audit_20261111`, **restored from the latest active backup** (`iclimate_2026-11-10_1915.backup`) before the
run. They use real Chromium sessions per role (farmer, second farmer in another barangay, MAO, IT) and the probes in
`scripts/realtime-audit/`. A delay runs from just before the database commit until the change is visible on an
already-open page, with no reload.

How updates travel: a saved row → `CacheScope::changed` (model events) → `DashboardChanged` after commit
(area names only, never record data) → Reverb private channels `user.{id}` and `dashboard.{role}` → the page
re-reads its own data under the viewer's permissions and replaces only its marked parts (`data-live-region`).
Bulk work that skips model events signals once at the end (`CacheScope::signal`). When Reverb is unreachable, the
server queues a retry (`broadcasts` queue), and pages fall back to polling and re-read on reconnect.

What changed in this pass: every remaining list and admin page is wired (below), the Heat Map now updates while
open, dashboards ignore list-only areas (an audit-log line never makes a dashboard re-read), a part of a page with an
unsaved form change is never replaced (the update waits, without asking the server again), scheduled analytics are
no longer queued into a stopped worker, and the local supervisor recovers from a crash loop by itself.

## Pages

| Module | Mechanism | Measured (open page, no reload) | Classification |
|---|---|---|---|
| Farmer Home dashboard | WebSocket + 5 min poll | 126 ms (new advisory) | REAL-TIME — PASS |
| MAO dashboard (incl. field-report data) | WebSocket | 612 ms (new farmer), 429 ms (advisory to review) | REAL-TIME — PASS |
| IT dashboard | WebSocket | 200 ms (new user) | REAL-TIME — PASS |
| Advisories (farmer, MAO) | WebSocket + 60 s poll offline | 127 ms; MAO "To approve" 125 ms; announcements 121 ms | REAL-TIME — PASS |
| Notification bell | WebSocket `notification.changed` | 39 ms; after an advisory is published 392 ms | REAL-TIME — PASS |
| Notifications page | Live region | 139 ms | REAL-TIME — PASS |
| My Farm | WebSocket `farm.changed` | 126 ms | REAL-TIME — PASS |
| Calendar | Live region | 124 / 126 / 131 ms (3 runs, one replacement each) | REAL-TIME — PASS |
| Users (IT) | Live region | 553 ms | REAL-TIME — PASS |
| Farmer Approvals (MAO) | Live region | 239 ms | REAL-TIME — PASS |
| Climate Records / Rice Production (MAO) | Live region | 126 ms / 85 ms | REAL-TIME — PASS |
| Messages: conversation list | WebSocket on `user.{id}` + 30 s poll offline | 153 ms | REAL-TIME — PASS |
| Messages: open conversation | WebSocket `conversation.{id}` + 8 s poll | 7 ms MAO→farmer, 6 ms farmer→MAO (after the send returns); Reverb stopped: 2.4 s by poll; back live after restart, each message shown once | REAL-TIME — PASS (new) |
| Heat Map (farmer, MAO) | WebSocket (areas risk, weather, barangays, planting, yield) + 60 s poll offline | flood layer after a barangay edit 170 ms (farmer) / 143 ms (MAO); new weather ratings applied 80 ms after the refresh ended | REAL-TIME — PASS (new; map position, layer, period and selection kept) |
| Barangays (MAO) | Live region | 72 ms | REAL-TIME — PASS (new) |
| MAO Planner | Live region (activities; farmer activities) | 122 ms | REAL-TIME — PASS (new) |
| MAO Activity Log | Live region | 121 ms | REAL-TIME — PASS (new) |
| MAO Send Alert history (sent, read counts) | Live region | new alert 411 ms (includes notifying farmers); a farmer reads it 49 ms | REAL-TIME — PASS (new) |
| Community feed (MAO / IT) | Live region (posts, comments, reactions) | post 120 ms, comment 120 ms | REAL-TIME — PASS (new) |
| Farmer Profiles (MAO) | Live region | 336 ms (new farmer) | REAL-TIME — PASS (new) |
| Farm Associations review (MAO) | Live region | 122 ms (new "not listed" request) | REAL-TIME — PASS (new) |
| My Reports (farmer) | Live region | 125 ms | REAL-TIME — PASS (new) |
| Weather Impact Analysis (MAO) | Live region (result, history) | 121 ms | REAL-TIME — PASS (new) |
| Yield Forecast (MAO) | Live region (history, predicted vs actual) | 304 ms (another MAO user's run) | REAL-TIME — PASS (new) |
| Weather analysis | Live region; charts redrawn | re-read 2 ms after the weather-cache refresh signal, region updated | SCHEDULED source — updates on arrival (new) |
| Weather predictions | Live region (forecast days, farmer's answer) | re-read 2 ms after the weather-cache refresh signal, region updated | SCHEDULED source — updates on arrival (new) |
| Reports | Live region on the source-data counts | 300 ms (new farmer). A generated report is a snapshot and is not changed | REAL-TIME — PASS (new) |
| System Health (IT) | Live region; signalled when a check result changes (prepared every 2 min) | 134 ms after the prepared result changed | NEAR REAL-TIME — PASS (new; ≤ 2 min after the cause) |
| System Logs (IT) | Live region (list + counts) | 48–50 ms | REAL-TIME — PASS (new) |
| Maintenance (IT) | Live region | 119 ms | REAL-TIME — PASS (new) |
| Model feedback (IT) | Live region | 223 ms | REAL-TIME — PASS (new) |
| Rice Varieties (IT list, catalog) | Live region | 131 ms | REAL-TIME — PASS (new) |
| Historical Rice (MAO) | Live region (CRUD) | 49 ms | REAL-TIME — PASS (new) |
| ML Models, Model Registry (IT) | Live region (`ml-models`) | not timed (no model was retrained in the clone) | NOT VERIFIED (wired) |
| Model Evaluation (IT) | Page load | — | NOT REAL-TIME (on demand) |
| Report generation status | Queue → Python; WebSocket `prediction.changed` + 8 s poll | ~1 s for a run with the worker up | REAL-TIME — PASS |
| Field Reports pages | — | switched off by config (`iclimate.field_reports_ui`); the data shows live on the MAO dashboard | NOT APPLICABLE |

## Background data

| Source | Producer | Interval | Measured (isolated, live sources) | Classification |
|---|---|---|---|---|
| Barangay weather + risk | Open-Meteo, `iclimate:refresh-barangay-weather` | every 10 min | 1.9 s, 9 of 9 barangays; the open Heat Map applied it 80 ms after the command ended | SCHEDULED — PASS |
| Weather cache (forecast) | `iclimate:refresh-weather-cache` | every 3 min | 9.4 s; Heat Map, Weather analysis and Weather predictions re-read 2 ms after its signal | SCHEDULED — PASS (new) |
| PAGASA advisories | `iclimate:fetch-pagasa-advisories` | every 15 min | 3 s, 4 sources (first pass) | SCHEDULED — PASS |
| Python analytics runs | `iclimate:automation-run` → `analytics` queue | every 30 min | 2 runs completed in ~1 s with the worker up; skipped (nothing queued) 2 min after the worker stopped | SCHEDULED — PASS (fixed) |
| Monthly weather forecasts | `GenerateMonthlyWeatherForecast` | daily | the 11 restored waiting jobs ran in ~92 s (8 s each), 0 failures, worker peak RSS 72 MB | SCHEDULED — PASS |
| Yield estimates | `iclimate:warm-farmer-yields` | every 3 h | one signal per run, only when an estimate changed (unit-tested; not run in the clone) | SCHEDULED — NOT VERIFIED in browser |

## Recovery and correctness (isolated)

- Reverb stopped: the Advisories page caught up through the fallback poll in 54 s (60 s design); the open
  conversation in 2.4 s (8 s poll). Reverb restarted: pages reconnected by themselves (14 s / 6.7 s) and were live
  again (122 ms).
- Channel authorization: 11/11 correct. Barangay isolation: a Matabungkay advisory is not shown in Kapito.
- One change → one page re-read; a message is shown once after reconnect.
- A list never changes under someone typing, and never while one of its forms holds an unsaved change; it updates
  about 4 s after they are done, without another request.
- Worker: TERM ends it after the current job; the heartbeat ages out after 2 min and scheduled analytics stop
  queueing until a worker is back.

## Active environment (read-only, 2026-11-11 ~04:00 UTC)

All five services were stopped (the Mac restarted at 12:01 Manila time; nothing restarts the stack after a reboot).
Last scheduler activity 2026-11-10 19:16 UTC. 14 jobs wait: 11 monthly forecasts (11 different months, not
duplicates) and 3 broadcast retries from 02:47 UTC. 41 analytics runs since 09:30 UTC failed with "Timed out while
waiting for the queue worker": the scheduler ran without a worker (fixed, see above). Nothing was started on the
active database. See `docs/ICLIMATE_ACTIVE_WORKER_APPROVAL_PLAN.md` for the approval request.

## Timestamps (third pass, 2026-11-11 evening)

Same isolated setup (copy on :8100, Reverb :8091, clone `iclimate_rt_audit_20261111`), probe
`scripts/realtime-audit/rt-timestamps.mjs`: per page, three clock readings compared with the real Asia/Manila time,
every "x ago" text checked against its stored time, and one real change that must move a shown stored time on the
open page (no reload).

**One component for every stored time:** `<x-live-time :at>` renders `<time datetime=… data-relative-time>` ("5 seconds
ago", "ngayon lang" in Filipino); `resources/js/live-clock.js` updates it every second together with the shell clock,
at once when a tab becomes visible again and right after a live region is replaced. `<x-updated-at>` ("Updated: 3:15 PM
· 5 seconds ago") uses it, so every page with that label ticks. The text counts from the stored database/source time;
nothing sets it to "now". Same wording rules in PHP (`App\Support\RelativeTime`) and JS (`resources/js/relative-time.js`),
tested from one table on both sides.

| Module | Live clock | Live data | Live timestamps | Measured (change → open page) | Status |
|---|---|---|---|---|---|
| Farmer Dashboard | PASS (skew 0 s) | PASS | PASS | weather stored time 6–7 ms after the forced Open-Meteo refresh ended; advisory 143 ms | REAL-TIME — PASS |
| MAO Dashboard | PASS | PASS | PASS | new farmer 303–364 ms (stored time = real build time) | REAL-TIME — PASS |
| IT Dashboard | PASS | PASS (fixed) | PASS (fixed) | new user: count 891 → 892 and the account listed 83–213 ms later | REAL-TIME — PASS (fixed, see below) |
| Weather Predictions | PASS (fixed) | SCHEDULED | PASS | forecast follows the 3-min weather cache; a queued estimate needs a worker (none started) | SCHEDULED — estimate path NOT VERIFIED |
| Heat Map (farmer, MAO) | PASS | PASS | PASS | new rating 7 ms; settles on the new time within 8 s | REAL-TIME — PASS (≈1 s in-between state, below) |
| Notifications page / bell | PASS | PASS | PASS | 143 ms; "ago" ticks in the popup too | REAL-TIME — PASS |
| Community feed | PASS | PASS | PASS | post 57–123 ms, comment 122 ms | REAL-TIME — PASS |
| System Health | PASS | NEAR REAL-TIME | PASS | 116 ms after the prepared result changed | NEAR REAL-TIME — PASS |
| System Logs | PASS | PASS | n/a (absolute times) | 48 ms; times now Philippine (fixed) | REAL-TIME — PASS |
| Advisories (farmer) | PASS | PASS | n/a (no stored-time label on the list) | — | REAL-TIME — PASS |

Fixes found by this pass:

- **IT dashboard was not really live.** A change made the open page re-read, but the prepared overview answered from
  its last copy and rebuilt after the response, and its data sections kept their own 5-minute copies. New figures
  waited for the 5-minute timer. Now the rebuilt copy is announced to IT dashboards only (`PreparedDashboards::announceNewItCopy`,
  no loop: the follow-up read finds the current copy) and data sections are keyed on the change token.
- **8-hour-future timestamps from MariaDB.** `ON UPDATE CURRENT_TIMESTAMP` columns are filled from the +08:00 session
  clock when an UPDATE leaves them out, while Laravel stores UTC: `external_weather_data` row 99 has
  `fetched_at` 23:57 vs `updated_at` 15:57 (2026-11-10), i.e. old weather looked 8 hours fresher. Eloquent updates of
  the six affected models now always write the column (`KeepsDatabaseClockColumns`); checked on the clone in a
  rolled-back transaction (left out → 20:17 (8 h ahead); guarded → unchanged). Existing rows were **not** changed.
- **Clock 1–2 s slow on slow pages.** The device/server offset used a second-precision server time from the start of
  the render; it now uses milliseconds and corrects only when the server time falls outside the request's own
  send/first-byte window (a real device clock error).
- **UTC shown as local time:** System Logs (list and date filter) and `iclimate:automation-status` now show Philippine time.

Not changed (reported only): see "Fourth pass" for the historical rows. (An earlier note here about a one-second
older Heat Map time during a refresh was a probe error: the probe also read the hidden Yield layer panel.)

## Fourth pass (2026-11-11, late evening)

**Active app (read-only):** the web server on :8000 runs (started by hand at 20:57, so the supervisor script lists it
as stopped) and serves exactly the current build (`public/build`, byte-identical, contains the timestamp code). ML
(:8010), Reverb (:8080), queue worker and scheduler are not running. No active data was written and no service started.

**Heat Map "temporary old time": no defect.** The visible weather stamp goes straight from the old to the new fetch
time. The 10:40 AM time came from the hidden Yield layer panel, whose time is the newest yield estimate.

**Yield estimate times were 8 hours ahead (fixed).** All 6,498 `yield_predictions.generated_at` rows were stored as
Manila wall clock (writers pass `now('Asia/Manila')` / `+08:00` ISO strings; Laravel saves a time's wall clock in its
own zone). `YieldPrediction` now stores `generated_at` as the same instant in UTC (`StoresInstantsInUtc`). Verified on the
clone: the full yield warm-up (46 s) rewrote 3,233 rows, all within 2 minutes of `updated_at`, none 8 h ahead; the open
Yield layer moved to the new time 63 ms after the run ended. The active rows correct themselves on the next scheduled
warm-up (every 3 h), which rewrites `generated_at`; no manual correction is needed.

**Timezone audit of the database-clock models:** a scan of every time column in the active database finds only two
skewed columns: `yield_predictions.generated_at` (above) and 11 `external_weather_data.fetched_at` rows. Direct writes
(`OpenMeteoService` upsert/insert, `RiceVarietyImporter` insert) set their time column explicitly.

**Historical rows (not modified; proposed corrections for approval):**

| Rows | Finding | Proposed correction |
|---|---|---|
| `external_weather_data` 17, 18, 35, 38, 39, 40, 41, 43, 99 | `fetched_at` exactly `updated_at` + 8 h (DB clock on update) | `fetched_at = fetched_at - INTERVAL 8 HOUR` (equals `updated_at`) |
| `external_weather_data` 22, 42 | `fetched_at` 23:45:00 / 14:00:00, Manila wall clock written by older code | `fetched_at = fetched_at - INTERVAL 8 HOUR` (15:45 / 06:00, just before `updated_at`) |
| `farmer_planting_schedules` (13 future now; 6,605 of 6,606 follow the pattern) | Import of "DS 2026-27 projection" set `created_at = planting_date` (not a timezone error) | Leave as is (no page shows these `created_at`), or set both to the import time if audits need it |

All 11 weather rows have past forecast dates (Aug 9 – Nov 10); no page shows them as current.

**ML side (clone + worker + ML service on :8011):** a queued MAO barangay yield request ran through the worker and
Python in ≤ 448 ms and the open Yield Forecast page showed the predicted tonnage 313 ms later (no reload); a Model
Registry metric change showed on the open IT page in 108 ms.

**Scheduled work (clone):** `iclimate:automation-run` queued 2 analytics runs, completed by the worker in 35–58 ms;
`refresh-weather-cache` 9 s (9 barangays); PAGASA 4 s (4 sources, 1 new). Duplicate guard: with the worker stopped,
a second run skipped the still-pending `advisory_ai`. Note: a run whose result is cached is recorded as completed
without a job, yet the command still prints "Queued …" (wording only). `automation-status` now prints PHT times.

**Final probe (11 pages, 3 roles):** all PASS; clocks 0 s from real Manila time; changes visible in 57–420 ms.

## Active environment activated (2026-11-11 23:26 Manila, owner-approved)

Backup first: `iclimate_2026-11-11_1526.backup` (verified, 69 entries). Then ML (:8010), queue worker and scheduler were
started one by one in the script's single-service mode; the hand-started Web (:8000) and Reverb (:8080) were kept.
**These three have no restart loop:** the worker exits after one hour (`--max-time=3600`) and nothing survives a
reboot. Move all five under `scripts/start-iclimate-local.sh start` (needs a ~10 s stop of Web and Reverb) for
unattended running.

First cycle (15:26–15:44 UTC), all scheduled tasks succeeded, 0 failed jobs, 0 failed runs:
analytics 2 runs completed in 3 s; barangay weather 9 of 9 (15:31, 15:41); weather cache every 3 min; PAGASA 4 sources,
2 advisories auto-published (information level, no notifications created); yield refresh rewrote the 3,227
current-season rows, all with correct UTC times. The 3,271 rows of the past season (season_start 2026-05-01) keep
their 8-hour-ahead `generated_at`: no page reads them and they were not modified.

Three signed-in dashboards kept open together for 13 minutes (read-only, no reload, Reverb connected throughout,
clocks 0 s from Manila time): Farmer weather stamp followed the 3-minute fetch 0.9–1.6 s after the stored time
(80 ms after the page's own re-read); MAO 9 updates 0.4–1.2 s after; IT 25 updates 0.03–1.4 s after.

Remaining: when a weather fetch returns unchanged values no signal is sent, so the farmer "Updated" time keeps the
previous fetch time until the next changed fetch or the 5-minute poll (seen: 15:33 fetch shown at 15:35; 15:42 fetch
not yet shown after 2 min). The risk stamp appeared 33 s after its rating time because the yield batch signals once
at its end.
