# iClimate Deep Usability QA — Round 2

**Audit date:** 2026-11-28  
**Branch / Target:** Latest `main` (`e647f7ae`) on an isolated seeded SQLite copy (`http://127.0.0.1:8132`, ML service offline to verify fallbacks).  
**Method:** Real-user Playwright journeys thinking aloud without training across 5 personas, `360×640` (Slow 3G `400kbps / 400ms RTT`), `375×740`, `1280×800`, `200%` zoom (`640×400`), and keyboard-only navigation. **No application code was modified.**

---

## 1. Summary per Persona

| Persona | Device / Network | Task Success | Avg Steps / Time | Top Frustrations | Keep Using It? |
|---|---|---:|---:|---|---|
| **1. Mang Romy** (58, Humayingan, low literacy, Filipino only) | `360×640` Slow 3G (`400kbps`) | 60% | 4 steps / **34.2s** per page on uncached Slow 3G | **1)** Page takes ~34s to load on Slow 3G (`app.css` 570KB + `app.js` 340KB). **2)** Logs in to English even though his account is set to Filipino (`preferred_language='fil'`). **3)** Topbar logos vanish at `360px`; Weather Alert modal pops up in English (`"Secure tall plants..."`). **4)** Technical/English words (`"t/ha"`, `"Low Risk"`, `"Rainfed"`, `"Advice"`/`"Aking Farm"`). | **No** — unless initial load is under 5s and his Filipino setting sticks on login. |
| **2. Aling Nena** (45, 3 farms in 2 barangays, association member, EN/FIL) | `375×740` Mobile | 80% | 3 steps / 1.1s | **1)** Switching active farm in More sheet doesn't update the `"Laki ng bukid"` KPI (`3 ha · 4 bukid` counts auto-created `Farm 1` + all farms instead of the selected `Bukid 2 (Prenza) 0.8 ha`). **2)** Bottom bar shows `"Mapa ng P..."` (truncated) and mixes `"Aking Farm"` with `"Aking Bukid"`. **3)** Bottom strip still says `"Farm 1 · 29°C"` instead of `"Bukid 2 (Prenza)"`. | **Maybe** — likes the Filipino translation, but confused by farm totals vs selected farm. |
| **3. Jun** (24, new farmer, 0 farms, tries wrong inputs) | `375×740` Mobile | 70% | 4 steps / 1.0s | **1)** Wrong password reloads `/` at the top hero instead of `#login`, saying `"These credentials do not match our records."` **2)** Empty Add Farm submit shows a toast covering the top of the red validation box and repeats errors twice. **3)** Planting form is long (10+ fields) with both `Expected harvest date` and `Harvest month` (`--------- ----`). | **Yes** — once past login/form friction, empty dashboard states are clear. |
| **4. Ma'am Liza** (MAO staff, 4 barangays, desktop + field phone, interrupted) | `1280×800` & `375×740` | 85% | 3 steps / 0.8s | **1)** Leaving `/planting-advisories/create` mid-draft discards unsaved inputs with no warning. **2)** On field phone (`375px`), `"Open Heat Map →"` (`32px`) and filter pills (`41px`) are slightly under `44px`. **3)** `"Find a farmer"` works well; 4-barangay scoping (`0 of 4`, `1 of 4`) is now clear. | **Yes** — scoping and needs-action flow are much clearer after Round 1 fixes. |
| **5. Sir Ben** (MAO Head, all 19 barangays, explains numbers to Mayor) | `1280×800` + `200%` zoom + Keyboard | 90% | 2 steps / 0.6s | **1)** Page title still says `"MAO Staff Dashboard"` even for MAO Head. **2)** Keyboard `Tab` hits `"Open Climora AI"` first before the topbar/skip link. **3)** `200%` zoom (`640px`) has 0 horizontal overflow, cleanly readable. | **Yes** — municipal figures and forecast vs actual charts are Mayor-ready. |

---

## 2. Top 15 Issues (Ranked by Farmer Impact First)

1. **`QA2-001` [Blocker / Performance & Trust]** — **Slow 3G initial load takes ~34s (`34,588ms` Dashboard, `34,038ms` Heat Map, `33,879ms` Yield Prediction)** (`Mang Romy`, `360×640` Slow 3G, `romy-360-dashboard.png`).  
   *Quote:* *"Nag-hang na yata 'to, ang tagal magbukas!"*  
   *Fix:* Enable HTTP gzip/brotli compression in production/nginx, purge/split unused CSS in `app.css` (currently 570KB), and defer non-critical JS.
2. **`QA2-002` [Major / Consistency & Trust]** — **Farmer `preferred_language = 'fil'` is ignored after login** (`Mang Romy`, `360×640`, `romy-360-dashboard.png`).  
   *Quote:* *"Filipino ang pinili ko dati, bakit Ingles pa rin ang babala pagkabukas ko?"*  
   *Fix:* Sync session/request locale from `auth()->user()->preferred_language` on login (`AuthenticatedSessionController`) and in `FarmerLanguage::resolve()`.
3. **`QA2-003` [Major / Accessibility & Layout]** — **At `360×640`, topbar MAO & iClimate logos disappear when EN/FIL flags are shown** (`Mang Romy`, `360×640`, `romy-360-dashboard.png`).  
   *Quote:* *"Parang nawala yung tatak ng Munisipyo at iClimate sa taas."*  
   *Fix:* On `<= 380px`, keep a compact `34px` logo visible and move the `EN|FIL` flags inside the More sheet or use a single compact `FIL`/`EN` pill button (`36px` wide) in the topbar.
4. **`QA2-004` [Major / Misread Number]** — **Multi-farm Dashboard shows total `"3 ha · 4 bukid"` even after switching to `"Bukid 2 (Prenza)"` (`0.8 ha`, Rainfed), and counts auto-created `"Farm 1"` as an extra farm** (`Aling Nena`, `375×740`, `nena-375-dash-fil.png`).  
   *Quote:* *"Pinili ko yung 0.8 ha sa Prenza, bakit 3 ha at May patubig pa rin ang nakasulat? At bakit 4 ang bukid ko eh 3 lang ang nilagay ko?"*  
   *Fix:* Show the selected farm's own hectares & type on the KPI tile (with total in subtitle) and avoid showing an empty auto-created `"Farm 1"` alongside named farms.
5. **`QA2-005` [Major / Error Recovery]** — **Wrong password reloads `/` at the top hero instead of scrolling to `#login`, and shows `"These credentials do not match our records."`** (`Jun`, `375×740`, `jun-375-wrong-password.png`).  
   *Quote:* *"Ano raw 'credentials'? At nasaan na yung pinag-type-an ko ng password?"*  
   *Fix:* Redirect failed login back to `/#login` (preserving scroll to `#login`) with plain wording: *"Wrong email or password. Try again or tap Forgot password."* / *"Mali ang email o password. Subukan muli o pindutin ang Nakalimutan ang password."*
6. **`QA2-006` [Major / Error Message & Overload]** — **Add Farm validation toast covers the red error summary box and repeats errors twice** (`Jun`, `375×740`, `jun-375-empty-farm-err.png`).  
   *Quote:* *"Natakpan ng pulang kahon sa taas yung binabasa kong mali!"*  
   *Fix:* Suppress the floating `"Action failed"` toast when inline form validation summary is rendered on the page.
7. **`QA2-007` [Major / Consistency & Truncation]** — **Filipino bottom nav truncates `"Mapa ng P..."` and mixes `"Aking Farm"` with `"Aking Bukid"`** (`Aling Nena` & `Mang Romy`, `375×740`, `nena-375-dash-fil.png`, `nena-375-my-farm.png`).  
   *Quote:* *"Ano yung 'Mapa ng P...'? Bakit 'Aking Farm' sa baba tapos 'Aking Bukid' sa taas?"*  
   *Fix:* Use short tab labels: `"Mapa"` (Heat Map) and `"Bukid"` (My Farm) in Filipino mobile nav.
8. **`QA2-008` [Major / Confusion & Overload]** — **Planting schedule form shows both `Expected harvest date` AND `Harvest month` (`--------- ----`) among 10+ fields** (`Jun`, `375×740`, `jun-375-future-planting-err.png`).  
   *Quote:* *"Dalawa ba ang ilalagay kong petsa ng ani—eksaktong araw at buwan pa?"*  
   *Fix:* Hide `Harvest month` behind an optional `"Only know the month?"` toggle and auto-fill Expected harvest date from the selected rice variety.
9. **`QA2-009` [Major / Accessibility]** — **Keyboard `Tab` order hits `"Open Climora AI"` first before `"Skip to main content"` and `"Open menu"`** (`Sir Ben`, `1280×800`).  
   *Quote:* *"Pag pindot ko ng Tab, sa chat button agad napunta imbes na sa menu."*  
   *Fix:* Place `<x-ai-chat-widget />` at the end of `<body>` DOM order (with `tabindex="0"` natural flow after `<main>`).
10. **`QA2-010` [Major / Consistency]** — **MAO Head dashboard still reads `"MAO Staff Dashboard"`** (`Sir Ben`, `1280×800`, `ben-640-zoom200.png`).  
    *Quote:* *"Head ako ng MAO, pero 'MAO Staff Dashboard' pa rin ang pamagat."*  
    *Fix:* Show `"MAO Head Dashboard"` (`"Dashboard ng Pinuno ng MAO"`) when `auth()->user()->isMaoHead()`.
11. **`QA2-011` [Minor / Accessibility]** — **Topbar flag buttons (`32px`), Bell/Messages (`40px`), Dashboard `7 days`/`14 days` (`24px`), `Edit farm` (`28px`), and phone link `09171110002` (`24px`) are `< 44px` on `360px`/`375px` mobile** (`Mang Romy`, `Aling Nena`, `Ma'am Liza`).  
    *Quote:* *"Maliliit pindutin gamit ang malaking daliri."*  
    *Fix:* Increase touch target height to `44px` via padding on `.ic-lang button`, `.ic-icon-btn`, `.fh-range button`, and `.fh-link`.
12. **`QA2-012` [Minor / Trust & Interruption]** — **Leaving `/planting-advisories/create` mid-draft discards unsaved text with no confirmation** (`Ma'am Liza`, `1280×800`).  
    *Quote:* *"Tinawag lang ako sa radyo, pagbalik ko nawala na lahat ng tinype ko."*  
    *Fix:* Add `beforeunload` / dirty-form prompt or localStorage draft restore on advisory create.
13. **`QA2-013` [Minor / Misread Number]** — **`"t/ha"` (tons per hectare) vs `"tons"` (total harvest) without plain explanation on Yield cards** (`Mang Romy`, `360×640`).  
    *Quote:* *"Ano yung 4.5 t/ha? 4.5 sako ba o tonelada?"*  
    *Fix:* Show `"4.5 t/ha (4,500 kg per hectare)"` or plain Filipino `"4.5 tonelada bawat ektarya"`.
14. **`QA2-014` [Minor / Accessibility]** — **MAO field phone (`375px`) `"Open Heat Map →"` (`32px`) and `Year`/`Season` (`41px`) slightly below `44px`** (`Ma'am Liza`, `375×740`).  
    *Fix:* Set `min-height: 44px` on `.mhd` mobile filter selects and `.hmx-open-link`.
15. **`QA2-015` [Minor / Trust]** — **Auto-created `"Farm 1"` persists in bottom context strip (`"Farm 1 · 29°C"`) even when farmer has named farms** (`Aling Nena`, `375×740`, `nena-375-dash-fil.png`).  
    *Fix:* Default active farm to the farmer's primary named farm (`is_primary = true`) or hide `"Farm 1"` once a named farm is added.

---

## 3. Glossary Check (Terms Farmers Didn't Understand)

| Term Seen in UI | Where | Simple English Replacement | Simple Filipino Replacement |
|---|---|---|---|
| `These credentials do not match our records.` | Login error | `Wrong email or password.` | `Mali ang email o password.` |
| `t/ha` | Dashboard, Yield Prediction | `tons per hectare` | `tonelada bawat ektarya` |
| `Rainfed` / `Irrigated` (when unlocalized) | Farm cards, Explore | `Rain-fed (no canal)` / `With irrigation` | `Sahod-ulan` / `May patubig` |
| `Mapa ng P...` (truncated `Mapa ng Panganib`) | Mobile bottom bar | `Risk Map` | `Mapa` |
| `Aking Farm` | Mobile bottom bar | `My Farm` | `Aking Bukid` |
| `Preliminary` / `Unvalidated estimate` | Yield cards | `Early estimate (not final)` | `Paunang tantiya (hindi pa pinal)` |
| `RSBSA No.` | Profile / Registration | `RSBSA Number (if you have one)` | `Numero sa RSBSA (kung mayroon)` |
| `Harvest month` (`--------- ----`) | Planting form | `Expected harvest month` | `Buwan ng anihan` |

---

## 4. Full Issue List by Page

- **Landing / Login (`/#login`, `/login`)**: `QA2-005` (wrong password scrolls to top hero + `"credentials"` jargon).
- **App Shell / Topbar / Mobile Nav (All pages)**: `QA2-002` (`preferred_language` ignored on login), `QA2-003` (logos vanish at `360px`), `QA2-007` (`"Mapa ng P..."` + `"Aking Farm"`), `QA2-009` (Climora first in `Tab` order), `QA2-011` (`32px–40px` mobile buttons), `QA2-015` (`"Farm 1"` in bottom strip).
- **Farmer Dashboard (`/farmer/dashboard`)**: `QA2-001` (`34.5s` Slow 3G load), `QA2-004` (`3 ha · 4 bukid` when `0.8 ha` Prenza farm is selected), `QA2-013` (`t/ha` without plain unit note).
- **Add Farm (`/farmer/farms/create`)**: `QA2-006` (toast covers red validation summary box).
- **Add Planting (`/farmer/planting-schedules/create`)**: `QA2-008` (dual `Expected harvest date` + `Harvest month` fields on long form).
- **Heat Map (`/heat-map`)**: `QA2-001` (`34.0s` Slow 3G load).
- **Yield Prediction (`/analysis/yield`)**: `QA2-001` (`33.8s` Slow 3G load), `QA2-011` (`Edit farm` `28px`).
- **MAO Dashboard (`/mao/dashboard`)**: `QA2-010` (`"MAO Staff Dashboard"` shown to MAO Head), `QA2-014` (`Open Heat Map →` `32px` on mobile).
- **Create Advisory (`/planting-advisories/create`)**: `QA2-012` (no unsaved-draft warning on interruption).

---

## 5. Proposed Fix Phases (Awaiting Your Approval)

| Phase | Scope | Issue IDs | Effort | Risk |
|---|---|---|---:|---|
| **P1 — Blockers, Performance & Misread Numbers** | Enable gzip/asset compression + trim `app.css` bloat for Slow 3G; sync `preferred_language` on login; show selected farm's own ha/type on Dashboard KPI & bottom strip (hide phantom `Farm 1`). | `QA2-001`, `QA2-002`, `QA2-004`, `QA2-015` | Medium (3–4h) | Low |
| **P2 — Farmer Words, Mobile Layout & Filipino** | Keep logos visible at `360px` (compact single lang pill or More-sheet toggle); shorten FIL nav tabs to `"Mapa"` & `"Bukid"`; add plain `"tons per hectare"` / `"tonelada bawat ektarya"` helper; simplify Planting date/month toggle. | `QA2-003`, `QA2-007`, `QA2-008`, `QA2-013` | Small (2h) | Low |
| **P3 — Error Messages & Recovery** | Scroll failed login back to `#login` with plain EN/FIL error copy; hide floating `"Action failed"` toast when inline form error box is visible. | `QA2-005`, `QA2-006` | Small (1h) | Low |
| **P4 — MAO Workflow & Trust** | Show `"MAO Head Dashboard"` for MAO Head; add dirty-form warning / draft preserve on advisory create. | `QA2-010`, `QA2-012` | Small (1h) | Low |
| **P5 — Accessibility & Touch-Target Polish** | Move Climora widget after `<main>` in DOM for keyboard `Tab` order; bump remaining mobile topbar/pill/link targets (`32px–41px`) to `>= 44px`. | `QA2-009`, `QA2-011`, `QA2-014` | Small (1–2h) | Low |

**Waiting for your approval before making any code fixes.**
