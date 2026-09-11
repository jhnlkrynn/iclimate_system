# iClimate System Audit — Implementation Plan

Prepared: 2026-09-11
Companion to `docs/system_audit_fix_plan.md` (the phase overview / rationale). This document is the execution-level spec: exact files, exact current code, exact new code, exact commands, exact verification. Every code snippet below was read directly from the repository at the time of writing — line numbers are cited but **re-grep before editing**, since line numbers drift as the file changes; a few line numbers reported by an earlier reviewer pass turned out to be off by 400+ lines (see item 4.6), so this document only cites line numbers that were independently re-verified.

## How to execute this document

1. Work phase by phase, in order. Within a phase, items are independent of each other unless noted, so they can be parallelized across agents if needed — but Phase 0 must fully complete before Phase 1 starts (Phase 1 assumes the admin credential is no longer a live risk).
2. Before editing any file, `grep -n` for the anchor text given below to confirm it still matches — if it doesn't, STOP and report the mismatch instead of guessing.
3. After each item, run the listed verification command(s). Do not proceed to the next item on a failed verification.
4. Commit after each item (not each phase) with a message referencing the finding number from `docs/system_audit_fix_plan.md`'s tracking table, e.g. `git commit -m "Fix #3: CommunityFeedController::canManagePost ownership check"`.
5. If a preflight check in this document reveals unexpected state (e.g. more duplicate rows than documented here), STOP and report back rather than improvising a resolution not described in this plan — some of these decisions (see 1.5) are not safe for an agent to make unilaterally.

### Environment notes

- Working directory: `C:\xampp\htdocs\iclimate_system`.
- **PHP**: the default XAMPP `C:\xampp\php\php.exe` is 8.0.30 and cannot run this project (`composer.json` requires `>=8.2`, Laravel 12). Use `C:\xampp\php_broken_backup_20260715\php.exe` for every `php`/`artisan`/`composer` command in this plan, unless a proper PHP 8.2+ install has since replaced it — confirm with `php -v` first if unsure.
- **MySQL**: must be running (XAMPP `mysqld`, database `iclimate_laravel`, user `root`, no password) for anything touching the DB. Start via `C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone` if not already running (check with `tasklist | grep mysqld` first).
- **Python**: use `.venv-ml\Scripts\python.exe` for anything under `python_scripts/`.
- **Tests**: `php artisan test` is currently misleading in this environment (see 1.4) — until that's fixed, verify with `vendor/bin/phpunit` directly, called via the working PHP binary: `C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit`.
- Run `git status` before starting and after each item to confirm only the intended files changed.

---

## Phase 0 — Stop the bleeding

### 0.1 [CRIT] Rotate the live admin credential

**Files:** none required for the immediate fix (data-only change); optional hardening touches `database/seeders/DatabaseSeeder.php`.

**Preflight — confirm the exposure still exists:**
```
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT id,email,role,status FROM iclimate_laravel.users WHERE email IN ('admin@iclimate.com','mao@iclimate.com','farmer@iclimate.com');"
```
As of 2026-09-11 all three exist with `status=Active`, and `admin@iclimate.com` authenticates with password `iclimate2026` (the value of `ICLIMATE_DEFAULT_ACCOUNT_PASSWORD` in `.env:18`).

**Action — rotate the password** (do this rather than deleting the accounts, since other parts of the app/tests may reference them; changing the password is reversible and non-destructive):
```
C:\xampp\php_broken_backup_20260715\php.exe artisan tinker --execute="App\Models\User::where('email','admin@iclimate.com')->first()->update(['password' => Hash::make('<GENERATE-A-NEW-STRONG-PASSWORD-HERE>')]); App\Models\User::where('email','mao@iclimate.com')->first()->update(['password' => Hash::make('<DIFFERENT-NEW-PASSWORD>')]); App\Models\User::where('email','farmer@iclimate.com')->first()->update(['password' => Hash::make('<DIFFERENT-NEW-PASSWORD>')]);"
```
Generate three distinct, random passwords — do not reuse `iclimate2026` in any form. Record them somewhere outside the repo (a password manager, not a comment in code) if these accounts are still needed as demo logins; otherwise set `status` to inactive instead of rotating.

**Also update `.env`:** change `ICLIMATE_DEFAULT_ACCOUNT_PASSWORD=iclimate2026` (line 18) to a new random value, or remove the line entirely — see the seeder guard below, which makes the env var optional outside `local`/`testing`.

**Harden the seeder** so this can't silently reoccur after a future `db:seed` in a non-local environment. In `database/seeders/DatabaseSeeder.php`, the relevant line is:
```php
$defaultPassword = (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', Str::password(24));
```
Change to:
```php
if (! app()->environment(['local', 'testing']) && ! env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD')) {
    throw new \RuntimeException(
        'Refusing to seed default accounts outside local/testing without an explicit ICLIMATE_DEFAULT_ACCOUNT_PASSWORD.'
    );
}

$defaultPassword = (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', Str::password(24));
```
This doesn't remove the demo accounts (they may be intentional for grading/demo purposes) — it just stops a bare `php artisan db:seed` on a non-local box from ever silently (re)creating them with a fallback or forgotten weak password.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe artisan tinker --execute="echo Hash::check('iclimate2026', App\Models\User::where('email','admin@iclimate.com')->first()->password) ? 'STILL VULNERABLE' : 'rotated OK';"
```
Must print `rotated OK`.

### 0.2 [MOD] `SESSION_SECURE_COOKIE` under `APP_ENV=production`

**File:** `.env.example`

Find the `SESSION_SECURE_COOKIE` line and add a comment above it:
```
# Must be true whenever APP_URL uses https:// (i.e. any real deployment).
# Only false is safe for local http://127.0.0.1 development.
SESSION_SECURE_COOKIE=false
```
This is documentation-only for `.env.example` — do not change the live `.env` unless this deployment is actually moving to HTTPS, since flipping it to `true` on the current local `http://127.0.0.1:8000` setup would break session cookies entirely (browsers won't send `Secure` cookies over plain HTTP).

**Verification:** none needed beyond `git diff` showing only the comment added.

---

## Phase 1 — One-line / one-file code fixes

### 1.1 [MOD] Fix `CommunityFeedController::canManagePost` — missing ownership check

**File:** `app/Http/Controllers/CommunityFeedController.php`
**Anchor (confirm with `grep -n "private function canManagePost" app/Http/Controllers/CommunityFeedController.php` — currently line 147):**
```php
    private function canManagePost(User $user, FeedPost $post): bool
    {
        return $this->canPost($user);
    }
```
**Replace with:**
```php
    private function canManagePost(User $user, FeedPost $post): bool
    {
        return $this->canPost($user) && (int) $post->user_id === (int) $user->id;
    }
```
`App\Models\User` is already imported (line 7) and `FeedPost` (line 5) — no new `use` statements needed.

If IT-Expert should be able to moderate/delete any post regardless of authorship (a product decision, not a code one — check with whoever owns this feature before assuming), use instead:
```php
    private function canManagePost(User $user, FeedPost $post): bool
    {
        return $this->canPost($user)
            && ((int) $post->user_id === (int) $user->id || $user->role === User::ROLE_IT_EXPERT);
    }
```
Default to the first version (strict ownership) unless told otherwise — it's the safer default and matches how `MessagingController::destroyMessage` already behaves elsewhere in this codebase.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=CommunityFeed
```
No existing test currently exercises this specific cross-user case, so this only confirms nothing broke. Optionally add a regression test — see the template at the end of this item.

**New test (optional but recommended):** create `tests/Feature/CommunityFeedAuthorizationTest.php`. Note: `FeedPost` has **no factory** in this codebase (`database/factories/` has no `FeedPostFactory.php`) — the existing test convention (see `tests/Feature/CommunicationPlatformTest.php:44-50`) is `FeedPost::query()->create([...])` with explicit fields, and `User::factory()` has a named state `maoPersonnel()` — use both, matching the codebase's actual pattern rather than assuming Laravel factory conventions that don't exist here. The route is `PATCH community-feed/{post}` named `community-feed.update` (`routes/web.php:148-150`), and `CommunityFeedController::validatePost()` (lines 152-164) requires `title`, `body`, `category` (one of `Update|Program|Activity|Training|Advisory|Announcement`), and `visibility` (one of `All Farmers|All Users`) — a request missing `category`/`visibility` will 422 before it even reaches the authorization check, so the test must include them:
```php
<?php

namespace Tests\Feature;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author): FeedPost
    {
        return FeedPost::query()->create([
            'user_id' => $author->id,
            'title' => 'Original title',
            'body' => 'Original body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);
    }

    public function test_a_mao_user_cannot_update_another_mao_users_post(): void
    {
        $author = User::factory()->maoPersonnel()->create();
        $other = User::factory()->maoPersonnel()->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($other)->patch(route('community-feed.update', $post), [
            'title' => 'Hijacked title',
            'body' => 'Hijacked body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $response->assertForbidden();
    }

    public function test_a_mao_user_can_update_their_own_post(): void
    {
        $author = User::factory()->maoPersonnel()->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($author)->patch(route('community-feed.update', $post), [
            'title' => 'My updated title',
            'body' => 'My updated body',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        $response->assertRedirect();
    }
}
```
Confirm `User::factory()` actually has a `maoPersonnel()` state (`grep -n "function maoPersonnel" database/factories/UserFactory.php`) before relying on it verbatim — it's used this way in `tests/Feature/CommunicationPlatformTest.php:42`, but re-verify since factory states can be renamed.

---

### 1.2 [MOD] Throttle the web registration route

**File:** `routes/auth.php`
**Anchor (currently line 21):**
```php
    Route::post('register', [RegisteredUserController::class, 'store']);
```
**Replace with:**
```php
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,10');
```
This matches the mobile API's pattern at `routes/api.php:21-22` (`throttle:3,10` on `MobileAuthController::register`), just slightly looser since the web form is the primary registration path.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe artisan route:list --path=register
```
Confirm the `throttle:5,10` middleware shows in the output. Then run the auth test suite:
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=Registration
```

---

### 1.3 [MOD] Defensive error boundary around the heatmap's per-barangay ML calls

**Context:** `PredictionEngine::requestYieldFromModel()` already catches `Throwable` internally and never itself throws — so this is not a proven live bug, it's removing a fragile implicit dependency. The more valuable fix is not just wrapping the ML call (already safe) but wrapping the **whole per-barangay loop body** in `HeatmapRiskService::refresh()`, so that if *anything* in that loop throws in the future (a DB write failure, a `DecisionSupportService`/`AgriculturalRiskScorer` edge case, etc.), one bad barangay is skipped and logged instead of aborting the entire refresh and 500-ing the heatmap page for every visitor.

**File:** `app/Services/HeatmapRiskService.php`

Add the import (file currently has no `Log` import — confirm with `grep -n "^use " app/Services/HeatmapRiskService.php`):
```php
use Illuminate\Support\Facades\Log;
```
Add it alongside the existing `use` block (currently lines 5-12, alphabetical order — insert after `use Illuminate\Support\Collection;`).

**Anchor — the `refresh()` method's `foreach` body (currently lines 57-115):**
```php
        foreach ($barangays as $barangay) {
            $production = RiceProduction::query()
                ->where('barangay', $barangay)
                ->latest('year')
                ->latest('id')
                ->first();

            $averageYield = $this->averageYieldForBarangay($barangay);

            if (! $weather['has_weather'] && ! $production && $averageYield === null) {
                continue;
            }

            $storedYield = $production?->yield_per_hectare !== null
                ? (float) $production->yield_per_hectare
                : $averageYield;

            $rainfall = $weather['rainfall'];
            $temperature = $weather['temperature'];
            $season = $weather['season'] ?? $production?->season ?? ClimateRecord::SEASON_WET;
            $farmType = $this->farmTypeFor($production?->irrigation_type);
            $modelYield = $this->predictedYieldForBarangay($weather, $production, $farmType, $season);
            $yield = $modelYield ?? $storedYield;
            $yieldSource = $modelYield !== null
                ? 'trained rice yield model estimate'
                : ($storedYield !== null ? 'stored barangay production record' : 'no yield estimate available');

            $decision = $this->decisionSupport->evaluate([
                'barangay' => $barangay,
                'farm_type' => $farmType,
                'rainfall' => $rainfall,
                'temperature' => $temperature,
                'wind_speed' => $weather['wind_speed'],
                'humidity' => $weather['humidity'],
                'season' => $season,
                'predicted_yield' => $yield,
            ]);

            $updates = [
                'risk_level' => $decision['risk']['level'],
                'risk_type' => $this->riskTypeFromDecision($decision, $temperature),
                'risk_score' => $decision['risk']['score'],
                'predicted_yield' => $yield,
                'rainfall_status' => $this->rainfallStatus($rainfall),
                'planting_advisory' => $decision['planting']['recommendation'],
                'irrigation_recommendation' => $decision['irrigation']['recommendation'],
                'description' => $weather['description'].' Yield source: '.$yieldSource.'. Barangay exposure: '.$this->riskScorer->exposureLabel($barangay).'. Recommendation confidence: '.$decision['confidence']['label'].'. Decision support score: '.$decision['score']['value'].' ('.$decision['score']['interpretation'].').',
            ];

            if ($this->defaultLatitude($barangay) !== null && $this->defaultLongitude($barangay) !== null) {
                $updates['latitude'] = $this->defaultLatitude($barangay);
                $updates['longitude'] = $this->defaultLongitude($barangay);
            }

            HeatmapArea::query()->updateOrCreate(
                ['barangay' => $barangay],
                $updates
            );
        }
```
**Replace with** (same body, wrapped):
```php
        foreach ($barangays as $barangay) {
            try {
                $production = RiceProduction::query()
                    ->where('barangay', $barangay)
                    ->latest('year')
                    ->latest('id')
                    ->first();

                $averageYield = $this->averageYieldForBarangay($barangay);

                if (! $weather['has_weather'] && ! $production && $averageYield === null) {
                    continue;
                }

                $storedYield = $production?->yield_per_hectare !== null
                    ? (float) $production->yield_per_hectare
                    : $averageYield;

                $rainfall = $weather['rainfall'];
                $temperature = $weather['temperature'];
                $season = $weather['season'] ?? $production?->season ?? ClimateRecord::SEASON_WET;
                $farmType = $this->farmTypeFor($production?->irrigation_type);
                $modelYield = $this->predictedYieldForBarangay($weather, $production, $farmType, $season);
                $yield = $modelYield ?? $storedYield;
                $yieldSource = $modelYield !== null
                    ? 'trained rice yield model estimate'
                    : ($storedYield !== null ? 'stored barangay production record' : 'no yield estimate available');

                $decision = $this->decisionSupport->evaluate([
                    'barangay' => $barangay,
                    'farm_type' => $farmType,
                    'rainfall' => $rainfall,
                    'temperature' => $temperature,
                    'wind_speed' => $weather['wind_speed'],
                    'humidity' => $weather['humidity'],
                    'season' => $season,
                    'predicted_yield' => $yield,
                ]);

                $updates = [
                    'risk_level' => $decision['risk']['level'],
                    'risk_type' => $this->riskTypeFromDecision($decision, $temperature),
                    'risk_score' => $decision['risk']['score'],
                    'predicted_yield' => $yield,
                    'rainfall_status' => $this->rainfallStatus($rainfall),
                    'planting_advisory' => $decision['planting']['recommendation'],
                    'irrigation_recommendation' => $decision['irrigation']['recommendation'],
                    'description' => $weather['description'].' Yield source: '.$yieldSource.'. Barangay exposure: '.$this->riskScorer->exposureLabel($barangay).'. Recommendation confidence: '.$decision['confidence']['label'].'. Decision support score: '.$decision['score']['value'].' ('.$decision['score']['interpretation'].').',
                ];

                if ($this->defaultLatitude($barangay) !== null && $this->defaultLongitude($barangay) !== null) {
                    $updates['latitude'] = $this->defaultLatitude($barangay);
                    $updates['longitude'] = $this->defaultLongitude($barangay);
                }

                HeatmapArea::query()->updateOrCreate(
                    ['barangay' => $barangay],
                    $updates
                );
            } catch (\Throwable $exception) {
                Log::error('Heatmap risk refresh failed for one barangay; skipping it.', [
                    'barangay' => $barangay,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }
        }
```
(Only the `foreach` opening line, the closing brace, and the new `catch` block are new — everything in between is unchanged, just re-indented one level. Use your editor's reindent rather than retyping to avoid introducing a diff-noise mistake.)

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=Heatmap
```
Then manually visit `/heatmap-areas?refresh=1` while logged in as MAO/IT-Expert and confirm the page still renders correctly (no behavior change expected — this is purely defensive).

---

### 1.4 [MIN] Fix the misleading `artisan test` failure readout

**File:** `C:\xampp\php_broken_backup_20260715\php.ini`
**Anchor (confirmed at line 935):**
```ini
;extension=pdo_sqlite
```
**Change to:**
```ini
extension=pdo_sqlite
```
(Remove the leading `;`. Leave line 932's `extension=pdo_mysql` as-is — it's already enabled.)

**Why:** `phpunit.xml:26-27` configures tests to run against `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. `artisan test` spawns `vendor/bin/phpunit` as a subprocess that doesn't inherit any CLI-only extension flags, and with `pdo_sqlite` disabled in `php.ini`, every test that touches the database fails with "could not find driver." Enabling it here fixes `artisan test` for good, not just for one invocation.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe -m | grep -i sqlite
C:\xampp\php_broken_backup_20260715\php.exe artisan test 2>&1 | tail -20
```
Should show `pdo_sqlite` in the module list, and `artisan test` should now report the true result (182 tests / 888 assertions passing as of this writing — the exact count will drift as tests are added, but it should no longer show mass "could not find driver" failures).

---

### 1.5 [MIN] Add unique constraints to prevent duplicate historical rows

**⚠️ Preflight check — do this before writing the migration, not after:**
```
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT barangay, season, year, COUNT(*) c FROM iclimate_laravel.rice_productions GROUP BY barangay, season, year, irrigation_type HAVING c > 1;"
```
As of 2026-09-11 this returns **one existing duplicate**: `Lumaniag / Dry / 2021 / Rainfed`, appearing as two rows (`id=1`: yield 5.46 t/ha on 23.42 ha; `id=3`: yield 3.26 t/ha on 3.17 ha), both created at the identical timestamp `2026-07-15 13:56:13` — almost certainly a double-seed, but the two rows disagree on yield and area enough that **an agent should not silently pick one and delete the other**. If this duplicate is still present when you run this step:
1. Do not delete either row automatically.
2. Report both rows (as printed by the query above) and ask which one is correct, or whether both should be kept under a slightly different key (e.g. if they represent genuinely different fields/farms that happen to share barangay/season/year/irrigation_type — check `remarks` on both rows for a clue: `SELECT id, remarks FROM rice_productions WHERE id IN (1,3);`).
3. Only proceed with the migration below once the duplicate is resolved (either row deleted, or the unique key redefined to include whatever distinguishes them).

Run the equivalent check for `climate_records` (no duplicates found as of 2026-09-11, but re-verify — data changes):
```
C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT record_date, COUNT(*) c FROM iclimate_laravel.climate_records GROUP BY record_date HAVING c > 1;"
```

**File:** new migration. Generate it rather than hand-naming it, so the timestamp is correct:
```
C:\xampp\php_broken_backup_20260715\php.exe artisan make:migration add_unique_constraints_to_rice_productions_and_climate_records
```
This creates `database/migrations/<timestamp>_add_unique_constraints_to_rice_productions_and_climate_records.php`. Replace its contents with:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->unique(['barangay', 'season', 'year', 'irrigation_type'], 'rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->unique('record_date', 'climate_records_unique_date');
        });
    }

    public function down(): void
    {
        Schema::table('rice_productions', function (Blueprint $table) {
            $table->dropUnique('rice_productions_unique_record');
        });

        Schema::table('climate_records', function (Blueprint $table) {
            $table->dropUnique('climate_records_unique_date');
        });
    }
};
```
Note on the `climate_records` constraint: this assumes exactly one climate record per calendar date is the intended data model (consistent with how `PredictionEngine::buildModelInput()` and `MonthlyWeatherRandomForest` both treat it — `latest('record_date')`, one row expected per date). If it later turns out multiple weather stations legitimately report on the same date, this constraint is wrong and should be relaxed to include a station/source-identifying column instead — but no such column exists distinctly today (`source` defaults to a generic descriptive string, not a station ID), so don't invent one speculatively.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe artisan migrate
```
Should apply cleanly (it will fail loudly with a duplicate-key error if the preflight check above wasn't actually resolved — that's the intended safety net, not a bug in the migration). Then:
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit
```
Full suite should still pass — check specifically that no seeder/factory relies on creating duplicate rows (`RiceProductionFactory`, `ClimateRecordFactory`, and any seeder calling them in a loop without unique inputs).

---

### 1.6 [MOD] Remove the dead Flask ML service

**Preflight — confirm nothing still calls it:**
```
grep -rn "farming_assistant_api" --include="*.php" --include="*.py" --include="*.yml" app config routes python_scripts docker-compose.yml
```
Should return nothing except (if not yet cleaned up) the stale comment inside `main.py` itself and `.env.example`. As of 2026-09-11, `docker-compose.yml`/`python_scripts/Dockerfile` run `uvicorn main:app` (FastAPI) on port 8010, and `app/Services/PythonService.php:18` posts to `config('services.farming_ai.url')` which resolves to that same FastAPI service — confirming Flask is unused in the live path.

**Delete:** `python_scripts/farming_assistant_api.py`

**File:** `python_scripts/requirements.txt` — remove these lines (Flask and its Flask-only dependencies):
```
blinker==1.9.0
Flask==3.1.3
itsdangerous==2.2.0
Jinja2==3.1.6
MarkupSafe==3.0.3
Werkzeug==3.1.8
```
**Do NOT remove `click`** — it looks like a Flask dependency but `uvicorn` also requires it (confirmed via `pip show uvicorn` → `Requires: click, h11`). Removing it would break the FastAPI service.

After editing, reinstall into `.venv-ml` to confirm nothing else pulls Flask back in transitively:
```
.venv-ml\Scripts\python.exe -m pip install -r python_scripts\requirements.txt
.venv-ml\Scripts\python.exe -m pip show flask
```
The last command should report "Package(s) not found" — if something still depends on Flask, stop and report which package before removing it from `requirements.txt`.

**File:** `python_scripts/main.py` — fix the stale docstring.
**Anchor (currently lines 19-20):**
```python
Not yet wired to PHP (Phase 3) — this stands alongside the existing Flask
app/local script/rules fallback until it's verified here first.
```
**Replace with:**
```python
This is the live ML backend — app/Services/PythonService.php posts to this
service's /predict route. The legacy Flask app has been removed.
```

**Verification:**
```
.venv-ml\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8010 --app-dir python_scripts
```
(Ctrl+C after confirming it boots — or run in background and `curl http://127.0.0.1:8000` wait, use `curl http://127.0.0.1:8010/health`.) Should return `{"status":"ok","models_loaded":["rice","weather"]}` or similar. Then run the PHP prediction tests, which exercise this contract via `Http::fake` and don't need the real service running:
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=Prediction
```

---

## Phase 2 — Honesty fixes (labeling/disclosure only, no ML changes)

### 2.1 [CRIT] Surface the DEPLOYED/TARGET schema mismatch on the Model Evaluation page

**File:** `app/Services/MachineLearning/ModelEvaluationService.php`
**Anchor — `buildReadyEvaluation()` (currently lines 54-101), specifically the returned array (currently lines 83-100):**
```php
        return [
            'ready' => true,
            'message' => $message,
            'sample_count' => $metrics['sample_count'] ?? $this->samples()->count(),
            'training_count' => null,
            'testing_count' => null,
            'features' => $this->featureLabels(),
            'rows' => $rows,
            'best_algorithm' => $best,
            'source' => [
                'name' => 'iClimate ML service (python_scripts/evaluation_service.py)',
                'type' => 'Live cross-validated evaluation',
                'dataset_path' => $metrics['source']['dataset_path'] ?? null,
                'computed_live' => true,
                'deployed_model' => storage_path('models/rice_yield_model_final.pkl'),
            ],
            'generated_at' => $metrics['generated_at'] ?? now(),
        ];
```
**Replace with** (adds one key, `schema_caveat`, nothing else changes):
```php
        return [
            'ready' => true,
            'message' => $message,
            'sample_count' => $metrics['sample_count'] ?? $this->samples()->count(),
            'training_count' => null,
            'testing_count' => null,
            'features' => $this->featureLabels(),
            'rows' => $rows,
            'best_algorithm' => $best,
            'schema_caveat' => 'These metrics were computed on a candidate feature set (see '
                .'app/Services/Prediction/FeatureCatalog.php::SCHEMA_TARGET). The currently '
                .'deployed model, rice_yield_model_final.pkl, was trained on a different, older '
                .'feature set and has not yet been evaluated against this data. Do not treat '
                .'these numbers as the deployed model\'s real-world accuracy until it is '
                .'retrained on this schema (see docs/system_audit_fix_plan.md, Phase 3).',
            'source' => [
                'name' => 'iClimate ML service (python_scripts/evaluation_service.py)',
                'type' => 'Live cross-validated evaluation',
                'dataset_path' => $metrics['source']['dataset_path'] ?? null,
                'computed_live' => true,
                'deployed_model' => storage_path('models/rice_yield_model_final.pkl'),
            ],
            'generated_at' => $metrics['generated_at'] ?? now(),
        ];
```

**File:** `resources/views/model-evaluation/index.blade.php`
**Anchor (currently lines 432-458, the "Source Evidence" panel):**
```php
        <div class="col-12">
            <div class="me-panel">
                <div class="me-panel-header">
                    <h2 class="h5 fw-bold mb-1">Source Evidence</h2>
                    <div class="small text-muted">Where the displayed model comparison values come from.</div>
                </div>
```
**Insert immediately before this `<div class="col-12">` block** (still inside the same `.row`, so it doesn't need its own `.col-12` wrapper removed — just add a new one above it):
```php
        @if($evaluation['ready'] ?? false)
            @if(! empty($evaluation['schema_caveat']))
                <div class="col-12">
                    <div class="alert alert-warning mb-0">
                        <strong>Schema notice:</strong> {{ $evaluation['schema_caveat'] }}
                    </div>
                </div>
            @endif
        @endif
        <div class="col-12">
            <div class="me-panel">
                <div class="me-panel-header">
                    <h2 class="h5 fw-bold mb-1">Source Evidence</h2>
                    <div class="small text-muted">Where the displayed model comparison values come from.</div>
                </div>
```
Confirm `.alert.alert-warning` renders correctly with this app's Bootstrap theme (it's the same class used for session-flash warnings elsewhere in the app, e.g. `resources/views/layouts/app.blade.php`) — no new CSS should be needed.

**Verification:** since `/metrics` currently returns `ready: false` (no real export CSV exists yet — only `SMOKETEST_rice_yield_training_data.csv`), this caveat won't be visible until real data exists. To verify it renders correctly without waiting for real data, temporarily point the evaluation at the smoketest CSV:
```
C:\xampp\php_broken_backup_20260715\php.exe artisan tinker --execute="
\$svc = app(App\Services\MachineLearning\ModelEvaluationService::class);
// won't reflect until the FastAPI service is running with SMOKETEST wired to DEFAULT_CSV — simplest verification is instead:
"
```
Simplest real verification: start the FastAPI service (`.venv-ml\Scripts\python.exe -m uvicorn main:app --host 127.0.0.1 --port 8010 --app-dir python_scripts`), temporarily copy `storage/app/private/ml/SMOKETEST_rice_yield_training_data.csv` to `storage/app/private/ml/rice_yield_training_data.csv` (the non-SMOKETEST name `evaluation_service.py` actually reads by default), visit `/model-evaluation` in the browser, confirm the yellow warning box appears above "Source Evidence", then **delete the copied file** (`rice_yield_training_data.csv`) afterward so it doesn't get mistaken for real data later.

---

### 2.2 [MOD] Add a methodology caveat to the heat map's risk scoring

**File:** `app/Services/Risk/AgriculturalRiskScorer.php`
**Anchor (currently lines 1-6):**
```php
<?php

namespace App\Services\Risk;

final class AgriculturalRiskScorer
{
```
**Replace with:**
```php
<?php

namespace App\Services\Risk;

/**
 * NOTE ON METHODOLOGY: BARANGAY_EXPOSURE below and the risk-weighting formula
 * in score() are engineering-judgment placeholder values, not derived from
 * cited hazard/flood-zone/elevation data. See docs/system_audit_fix_plan.md
 * (Phase 3/4) for the plan to replace these with real MDRRMO/LGU hazard data
 * once available. Do not present this as empirically-derived risk analysis
 * without disclosing that caveat to whoever is viewing it.
 */
final class AgriculturalRiskScorer
{
```

**File:** `resources/views/heatmap-areas/index.blade.php`
**Anchor (currently lines 581-595, the page hero):**
```php
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Barangay Agricultural Risk Map</div>
                <h1 class="h2 fw-bold mb-2">Heat Map Areas</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">API-rendered Lian barangay heatmap, with PAGASA used as the official weather reference source.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-primary" href="{{ $pagasaMapUrl }}" target="_blank" rel="noopener">View PAGASA Map</a>
                @if ($canManage)
                    <a class="btn btn-light" href="{{ route('heatmap-areas.create') }}">Create Risk Area</a>
                @endif
            </div>
        </div>
    </section>
```
**Insert immediately after the closing `</section>`** (before the `@php $activeFilterCount = ...` block that follows):
```php

    <div class="alert alert-warning">
        <strong>Methodology note:</strong> Per-barangay risk exposure values on this map are
        engineering estimates, not derived from official hazard, flood-zone, or elevation data.
        Treat relative comparisons between barangays with caution until sourced hazard data is
        incorporated.
    </div>
```

**Verification:** visit `/heatmap-areas` logged in as any role that can view it; confirm the warning banner renders between the hero and the filters panel, and that `AgriculturalRiskScorer`'s existing tests (if any — `grep -rl AgriculturalRiskScorer tests/`) still pass, since only a docblock changed there, not behavior.

---

### 2.3 [MOD] Stop presenting fabricated heuristic scores as model-derived "confidence"

**Do not rename any JSON field names or PHP array keys** — `confidence_score`, `$result['confidence']`, `$mlResult['api_confidence']`, etc. are read by `tests/Feature/PredictionParityTest.php` (which asserts `'confidence_score' => 88` in its `Http::fake` mock) and by JS in `resources/views/ai-chat/index.blade.php:370`. Renaming the field is a bigger, riskier change than this problem needs — the actual issue is what a human reads next to the number, not what the variable is called internally. Fix the copy only.

There are **three independent, hand-tuned "confidence" heuristics** in this system — make sure whoever reads the diff understands these are three separate things, not one:
1. `python_scripts/rice_yield_service.py::confidence_for()` (lines 107-119) — rice yield prediction confidence.
2. `python_scripts/monthly_weather_random_forest.py::confidence()` (lines 147-173) — monthly weather forecast confidence.
3. `app/Services/DecisionSupportService.php::confidenceLevel()` (lines 242-256) — heatmap/decision-support "Recommendation confidence."

None of these should be touched functionally. For each, find where its output is displayed to a human and add one clarifying sentence or tooltip. Confirmed display locations as of 2026-09-11 (re-grep `confidence` in each file before editing, since exact line numbers will drift):

- `resources/views/weather-predictions/index.blade.php:33` — `$weatherConfidence = $result['confidence'] ?? [...]`, later rendered somewhere in that view (grep `$weatherConfidence` in the same file to find the render point). Add adjacent small text: `Confidence is a heuristic based on data recency and variability, not a statistical prediction interval.`
- `resources/views/weather-predictions/index.blade.php:336` — `Confidence {{ $mlResult['api_confidence'] }}%` inline in the panel header. Change the surrounding string to make clear it's heuristic, e.g. `' | Estimated confidence '.$mlResult['api_confidence'].'%'` (the word "Estimated" is the whole fix here — small, low-risk).
- `resources/views/heatmap-areas/index.blade.php` around lines 1175-1210 — JS that extracts `"Recommendation confidence: X"` out of a free-text `description` string built server-side in `HeatmapRiskService::refresh()` (see 1.3's anchor, the `$updates['description']` line). Simplest fix: in that same description string in `HeatmapRiskService.php`, change `'Recommendation confidence: '` to `'Estimated recommendation confidence (heuristic, not statistical): '`. This flows through to the JS automatically since it just extracts from that string.
- `resources/views/ai-chat/index.blade.php` — confirm whether `confidence_score` is ever rendered as visible text to the user (line 370 is a JS object literal for an *error* fallback message, `confidence_score: 0`, which may not be the only usage — grep the full file for `confidence` before concluding there's nothing to change here).

**Do not touch** `python_scripts/rice_yield_service.py`, `monthly_weather_random_forest.py`, or `DecisionSupportService.php` themselves in this item — this is a Blade/copy-only pass. (A future, separate effort could replace these heuristics with real statistics; that's out of scope here per the "prefer the simpler fix" principle in `docs/system_audit_fix_plan.md`.)

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=Prediction
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit --filter=WeatherPrediction
```
Both should still pass unchanged (this item touches no logic, only literal strings) — if either fails, you accidentally changed a value the tests assert on exactly; revert and redo as copy-only.

---

### 2.4 [MOD] Keep the duplicated weather-label thresholds from silently drifting

**Files:** `app/Services/Prediction/WeatherLabeler.php` and `python_scripts/rice_yield_service.py`

Both currently hardcode the same four thresholds independently:
```php
// WeatherLabeler.php:9-14
$rainfall >= 300 => 'Heavy Rain',
$rainfall >= 120 => 'Rain',
$rainfall <= 70 => 'Dry',
default => 'Cloudy',
```
```python
# rice_yield_service.py weather_label(), lines 98-104
if rainfall >= 300: return "Heavy Rain"
if rainfall >= 120: return "Rain"
if rainfall <= 70: return "Dry"
return "Cloudy"
```
Add a cross-reference comment to each pointing at the other, and a regression test on each side that pins the exact numbers, so a future one-sided edit fails CI instead of silently drifting.

**`WeatherLabeler.php`** — add above the `fromRainfall` method:
```php
    /**
     * Keep these thresholds identical to python_scripts/rice_yield_service.py's
     * weather_label() function — there is no shared config between the two
     * languages, so a change here without the matching Python change will
     * silently make PHP and the ML service disagree on weather labels.
     */
    public static function fromRainfall(float $rainfall): string
```

**New test file** `tests/Unit/WeatherLabelerTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Services\Prediction\WeatherLabeler;
use PHPUnit\Framework\TestCase;

class WeatherLabelerTest extends TestCase
{
    /**
     * Pins the exact thresholds that must match python_scripts/rice_yield_service.py's
     * weather_label(). If this test needs to change, the Python function must change too.
     */
    public function test_rainfall_thresholds(): void
    {
        $this->assertSame('Dry', WeatherLabeler::fromRainfall(70));
        $this->assertSame('Cloudy', WeatherLabeler::fromRainfall(71));
        $this->assertSame('Cloudy', WeatherLabeler::fromRainfall(119));
        $this->assertSame('Rain', WeatherLabeler::fromRainfall(120));
        $this->assertSame('Rain', WeatherLabeler::fromRainfall(299));
        $this->assertSame('Heavy Rain', WeatherLabeler::fromRainfall(300));
    }
}
```

**`rice_yield_service.py`** — add a docstring/comment above `weather_label()`:
```python
def weather_label(raw_value) -> str:
    """Keep these thresholds identical to WeatherLabeler::fromRainfall() in
    app/Services/Prediction/WeatherLabeler.php — see tests/Unit/WeatherLabelerTest.php."""
```

Python has no test runner configured in this repo (see Phase 4.2) — until that exists, this comment is the only guardrail on the Python side. Do not add a standalone `pytest` install just for this one test; it'll be covered once 4.2 sets up the Python test scaffolding.

**Verification:**
```
C:\xampp\php_broken_backup_20260715\php.exe vendor/bin/phpunit tests/Unit/WeatherLabelerTest.php
```

---

## Phase 3 — Data-dependent ML fixes (blocked on real MAO/PAGASA data)

These steps cannot run today — they're blocked on real historical climate/rice-production data that doesn't exist in this repo. This section documents the exact mechanical procedure so whoever imports the real data can follow it without re-deriving the pipeline.

1. Import real historical records into `climate_records` and `rice_productions` (however that import happens — CSV import command, manual entry, etc. — not specified here since the data source itself is the blocker).
2. Export training data:
   ```
   C:\xampp\php_broken_backup_20260715\php.exe artisan iclimate:export-yield-data
   ```
   Confirm the output path is `storage/app/private/ml/rice_yield_training_data.csv` (not the `SMOKETEST_`-prefixed one) and that it has **at least 30 rows** — `evaluation_service.py`'s `RELIABILITY_FLOOR` constant (line 48) — before treating any metrics from it as reliable.
3. Run the feature comparison to confirm the Extended feature set still wins on real data (it was only validated on synthetic SMOKETEST data before):
   ```
   .venv-ml\Scripts\python.exe python_scripts\compare_yield_features.py storage\app\private\ml\rice_yield_training_data.csv
   ```
4. Train the new model:
   ```
   .venv-ml\Scripts\python.exe python_scripts\train_rice_yield_model.py storage\app\private\ml\rice_yield_training_data.csv storage\models\rice_yield_model_final.pkl
   ```
   This overwrites the deployed model file directly — make sure `git status` shows this as a modified binary file before committing, and back up the old `.pkl` (e.g. copy to `storage/models/rice_yield_model_final.pkl.pre-retrain-backup`) first in case the new model needs to be rolled back.
5. **Cut over the serving schema at the same time** — this is the step that actually closes finding #14 (two incompatible schemas). Update:
   - `python_scripts/predict.py`: replace `FEATURE_MAP` (lines 18-31) with the TARGET schema column names (`rainfall`, `temperature`, `area_hectares`, `humidity`, `wind_speed`, `season`, `irrigation_type` — see `NUMERIC_FEATURES`/`CATEGORICAL_FEATURES` at the top of `evaluation_service.py` for the exact names/order to match).
   - `python_scripts/rice_yield_service.py::rice_features()` (lines 75-89): replace the returned column dict with the same TARGET schema.
   - `app/Services/Prediction/RiceYieldPredictionService.php::INPUT_TO_MODEL_FEATURE` (lines 15-28): update to the TARGET schema's PHP-side input names.
   - `app/Services/Prediction/PredictionEngine.php::buildModelInput()` (lines 63-125): adjust to build the TARGET schema's inputs instead of the DEPLOYED schema's (drop the lag/rolling-window fields like `previous_rainfall`, `rainfall_6m`, etc. that TARGET doesn't use; add `irrigation_type` if not already present, sourced from `$user?->farmerProfile?->farm_type` or the equivalent).
   - `tests/Feature/PredictionParityTest.php::test_shared_service_exposes_exact_model_feature_order` (lines 123-139): update the expected feature array to match — this test exists specifically to catch exactly this kind of schema drift, so it **should** fail until you update it, confirming the test was doing its job.
6. Remove the now-obsolete DEPLOYED-schema code entirely rather than leaving it as a dead alternate path — once step 5 is done, there should be exactly one schema referenced across PHP and Python, not two.
7. Update `app/Services/MachineLearning/ModelEvaluationService.php` to remove the `schema_caveat` key added in Phase 2.1 (and its blade rendering) — it should no longer be true.
8. Retrain `weather_model.pkl` using the same real data if a documented, reproducible pipeline for it can be reconstructed; if not, that's a separate decision to flag back to whoever owns this project (do not fabricate a training script for a model whose original methodology is unknown — that would just create a *third* unaudited model).

---

## Phase 4 — Background hardening (opportunistic, not blocking)

### 4.1 [MOD] Make `rice_yield_service.py` fail loudly on missing input, like `predict.py` already does

**File:** `python_scripts/rice_yield_service.py`
**Anchor (currently lines 60-61):**
```python
def _feature(features: dict, key: str, default=0):
    return features.get(key, default)
```
This is used throughout `weather_features()` and `rice_features()` to silently substitute a plausible-looking default (e.g. `rainfall` → 180) when a field is missing, instead of raising — inconsistent with `predict.py::build_features()` (lines 58-62), which correctly raises `ValueError` on any missing required field for the *same* model.

**Replace with:**
```python
_REQUIRED_WEATHER_FEATURES = {"previous_rainfall", "previous_temp", "previous_humidity", "previous_wind_speed", "month_num", "season"}
_REQUIRED_RICE_FEATURES = {
    "rainfall", "temp_avg", "temp_range", "area", "previous_rainfall", "previous_temp",
    "rainfall_6m", "temp_3m", "temp_6m", "seasonal_rainfall", "seasonal_temp", "season",
}


def _feature(features: dict, key: str, default=0, *, required: bool = False):
    if required and key not in features:
        raise ValueError(f"Missing required feature: {key}")
    return features.get(key, default)
```
This keeps `_feature()`'s signature backward-compatible (default is still optional/lenient by default) but gives `weather_features()`/`rice_features()` a way to opt into strict validation. Then update those two functions to pass `required=True` for every field currently listed in `_REQUIRED_WEATHER_FEATURES`/`_REQUIRED_RICE_FEATURES` — e.g.:
```python
def rice_features(features: dict) -> pd.DataFrame:
    return pd.DataFrame([{
        "RAINFALL": float(_feature(features, "rainfall", required=True)),
        "TEMP_AVG": float(_feature(features, "temp_avg", required=True)),
        "TEMP_RANGE": float(_feature(features, "temp_range", required=True)),
        "Area": float(_feature(features, "area", required=True)),
        "Previous_Rainfall": float(_feature(features, "previous_rainfall", required=True)),
        "Previous_Temp": float(_feature(features, "previous_temp", required=True)),
        "Rainfall_6M": float(_feature(features, "rainfall_6m", required=True)),
        "Temp_3M": float(_feature(features, "temp_3m", required=True)),
        "Temp_6M": float(_feature(features, "temp_6m", required=True)),
        "Seasonal_Rainfall": float(_feature(features, "seasonal_rainfall", required=True)),
        "Seasonal_Temp": float(_feature(features, "seasonal_temp", required=True)),
        "Season": str(_feature(features, "season", required=True)),
    }])
```
Apply the same pattern to `weather_features()`. Note this makes `main.py`'s `/predict` route start returning errors (via the `except Exception` → the FastAPI layer, check `main.py`'s `predict()` handler currently has no explicit try/except around `rice_yield_service.predict()` — add one if missing, returning `HTTPException(422, ...)` on `ValueError`) for any request missing a field it previously silently defaulted. **This is a behavior change, not just a safety net** — confirm with whoever owns `PredictionEngine::buildModelInput()` that it genuinely always sends every field before deploying this (it appears to, based on the PHP code reviewed in this audit, but re-confirm since a false assumption here would turn every prediction into a 422 instead of a wrong-but-working answer).

**Verification:** write a quick manual check before wiring it into `main.py`'s error handling:
```
.venv-ml\Scripts\python.exe -c "
import sys; sys.path.insert(0, 'python_scripts')
import rice_yield_service as s
try:
    s.rice_features({})
    print('FAIL: should have raised')
except ValueError as e:
    print('OK:', e)
"
```

### 4.2 [MOD] Minimal Python smoke tests

**New files:**
```
python_scripts/tests/__init__.py           (empty)
python_scripts/tests/test_smoke.py
```
Add `pytest==8.3.4` (or current stable) to `python_scripts/requirements.txt`.

`python_scripts/tests/test_smoke.py`:
```python
"""Minimal smoke tests — not exhaustive coverage. Confirms the service boots
and the core contracts hold; does not validate model accuracy (see
docs/system_audit_fix_plan.md Phase 3 for that)."""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from fastapi.testclient import TestClient

import evaluation_service
import main

client = TestClient(main.app)


def test_health_endpoint_responds():
    response = client.get("/health")
    assert response.status_code == 200
    assert "status" in response.json()


def test_predict_rice_yield_returns_expected_shape():
    payload = {
        "prediction_type": "rice_yield",
        "features": {
            "rainfall": 180, "temp_avg": 29, "temp_range": 8, "area": 1,
            "previous_rainfall": 180, "previous_temp": 29,
            "rainfall_6m": 900, "temp_3m": 29, "temp_6m": 29,
            "seasonal_rainfall": 900, "seasonal_temp": 29, "season": "Wet",
        },
    }
    response = client.post("/predict", json=payload)
    assert response.status_code == 200
    body = response.json()
    assert "rice_yield_prediction" in body
    assert "predicted_yield" in body["rice_yield_prediction"]


def test_predict_rejects_unknown_prediction_type():
    response = client.post("/predict", json={"prediction_type": "nonsense", "features": {}})
    assert response.status_code == 422


def test_evaluate_reports_not_ready_when_csv_missing():
    result = evaluation_service.evaluate("storage/app/private/ml/__does_not_exist__.csv")
    assert result["ready"] is False
    assert "message" in result
```
Note: `test_predict_rice_yield_returns_expected_shape` will only pass once storage/models/rice_yield_model_final.pkl and weather_model.pkl are loadable in the test environment — since they're already committed to the repo, this should work out of the box, but confirm the sklearn-compatibility shims (`install_sklearn_compatibility_shims()`) are still working in whatever environment runs this test.

**Verification:**
```
.venv-ml\Scripts\python.exe -m pip install pytest
.venv-ml\Scripts\python.exe -m pytest python_scripts/tests/ -v
```
All four should pass.

### 4.3 [MIN] Give the monthly weather Random Forest a real (if crude) validation signal

**File:** `python_scripts/monthly_weather_random_forest.py`

Full k-fold CV isn't meaningful at 4-24 samples, but leave-one-out (LOO) is cheap at this scale and gives a real number instead of zero validation. Add a helper and call it inside `predict()`:

**Anchor — inside the `for target in TARGETS:` loop in `predict()` (currently lines 251-270):**
```python
    for target in TARGETS:
        y_train = np.array([s[target] for s in samples])

        forest = RandomForestRegressor(
            n_estimators=45,
            max_depth=7,
            min_samples_split=2,
            max_features=max_features,
            random_state=zlib.crc32(target.encode()),
        )
        forest.fit(x_train, y_train)
        predicted_value = float(forest.predict(x_target)[0])

        predictions[target] = round(predicted_value, 2)
        training[target] = {
            "trees": forest.n_estimators,
            "samples": len(samples),
            "features": len(feature_order),
            "stability": target_stability(samples, target),
        }
```
**Replace with** (adds a `loo_mae` field to each target's `training` block; only computes LOO when there are enough samples to make it meaningful):
```python
    for target in TARGETS:
        y_train = np.array([s[target] for s in samples])

        forest = RandomForestRegressor(
            n_estimators=45,
            max_depth=7,
            min_samples_split=2,
            max_features=max_features,
            random_state=zlib.crc32(target.encode()),
        )
        forest.fit(x_train, y_train)
        predicted_value = float(forest.predict(x_target)[0])

        loo_mae = _leave_one_out_mae(x_train, y_train, max_features, target) if len(y_train) >= 5 else None

        predictions[target] = round(predicted_value, 2)
        training[target] = {
            "trees": forest.n_estimators,
            "samples": len(samples),
            "features": len(feature_order),
            "stability": target_stability(samples, target),
            "loo_mae": round(loo_mae, 2) if loo_mae is not None else None,
        }
```
Add the helper function above `predict()` (near `target_stability`):
```python
def _leave_one_out_mae(x_train: np.ndarray, y_train: np.ndarray, max_features: int, target_seed_key: str) -> float:
    """Cheap real validation signal for small samples — not a replacement for
    proper k-fold CV, but strictly better than reporting no accuracy measure
    at all. Only called when there are >=5 samples (see caller)."""
    errors = []

    for i in range(len(y_train)):
        train_idx = [j for j in range(len(y_train)) if j != i]
        forest = RandomForestRegressor(
            n_estimators=45,
            max_depth=7,
            min_samples_split=2,
            max_features=max_features,
            random_state=zlib.crc32(f"{target_seed_key}-loo-{i}".encode()),
        )
        forest.fit(x_train[train_idx], y_train[train_idx])
        prediction = forest.predict(x_train[[i]])[0]
        errors.append(abs(prediction - y_train[i]))

    return float(mean(errors))
```
`mean` is already imported from `statistics` at the top of the file (line 32) — no new import needed.

This does NOT touch the existing `confidence()` heuristic — that's still the fabricated-but-labeled-per-2.3 number. `loo_mae` is a genuinely computed, additional, honest number sitting alongside it. Whether to eventually replace `confidence()` with something derived from `loo_mae` is a future decision, not part of this item.

**Verification:**
```
.venv-ml\Scripts\python.exe -c "
import sys; sys.path.insert(0, 'python_scripts')
import json
from datetime import date
import monthly_weather_random_forest as m
records = [{'record_date': f'2026-0{i}-15', 'rainfall': 100+i*10, 'temperature': 27+i*0.2, 'humidity': 75, 'wind_speed': 10} for i in range(1, 9)]
result = m.predict(date(2026, 12, 1), records)
print(json.dumps(result['training']['rainfall'], indent=2))
"
```
Should show a `loo_mae` key with a numeric value (8 monthly records in this synthetic example is enough to trigger it, since `len(samples)` will be 7, ≥5).

### 4.4 [MIN] Audit barangay write paths for validation bypass

Not a fixed diff — an audit task. Grep every place a `barangay` (or `target_barangay`) column gets written and confirm it goes through the existing `LianBarangays` validation, not a raw string:
```
grep -rn "barangay" database/seeders database/factories app/Console/Commands --include="*.php" -l
```
For each file found, check whether the barangay value being written is validated against `LianBarangays::all()` (or a subset of it) or is arbitrary/free-typed. Seeders and factories are the most likely bypass (they don't go through HTTP validation at all). Fix any that write a barangay name not present in `LianBarangays::all()` — either by using `fake()->randomElement(LianBarangays::all())` (factories) or a hardcoded value confirmed to be in that list (seeders).

**Do not** build a `barangays` lookup table or add foreign keys as part of this item — per `docs/system_audit_fix_plan.md`, that's explicitly deferred unless this audit shows the validation-consistency fix isn't enough.

**Verification:** after any fixes, re-run the heatmap and rerun `LianHeatmapController::normalizeBarangay()`'s fuzzy-matching mentally against the now-consistent data — or more concretely, check that `SELECT DISTINCT barangay FROM rice_productions` (and the other tables) only returns values also present in `LianBarangays::all()`:
```
C:\xampp\php_broken_backup_20260715\php.exe artisan tinker --execute="
\$all = App\Support\LianBarangays::all();
foreach (['rice_productions','climate_records','farmer_profiles','heatmap_areas'] as \$table) {
    \$bad = DB::table(\$table)->whereNotIn('barangay', \$all)->distinct()->pluck('barangay');
    echo \$table.': '.(\$bad->isEmpty() ? 'OK' : 'MISMATCHES: '.\$bad->implode(', '))."\n";
}
"
```
(Confirm the actual class name/namespace for `LianBarangays` first — `grep -rn "class LianBarangays" app/` — the audit referenced it but this plan hasn't independently verified its exact location/namespace, so re-check before running the tinker command above.)

### 4.5 [MIN] Decide whether `area_hectares` belongs in the TARGET schema

Not a code change by itself — a decision to make **before** Phase 3 step 5, since changing the schema after retraining means retraining again. Two options:
- Drop `area_hectares` from `NUMERIC_FEATURES` in `train_rice_yield_model.py`, `compare_yield_features.py`, and `evaluation_service.py` if there's no defensible agronomic reason for farm size to predict a size-normalized yield rate.
- Keep it, but add one sentence to `app/Services/Prediction/FeatureCatalog.php`'s `area_hectares` entry explaining the justification (e.g. economies-of-scale access to better inputs/mechanization on larger farms).

Whoever owns the ML methodology should make this call — this plan doesn't decide it, it just flags that it needs deciding before Phase 3 locks in a schema.

### 4.6 [MIN] `ReportController` payload validation

**File:** `app/Http/Controllers/ReportController.php`
**Anchor (currently lines 206-223 — note: an earlier review pass cited lines 610-618 for this, which was wrong; the file is 380 lines total, re-confirm with `grep -n "payload" app/Http/Controllers/ReportController.php` before editing):**
```php
    protected function prepareData(Request $request, array $data): array
    {
        $data['generated_by'] = $request->user()->id;
        if (isset($data['payload']) && is_string($data['payload'])) {
            $data['payload'] = json_decode($data['payload'], true) ?: ['notes' => $data['payload']];
        }

        return $data;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'report_type' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['nullable', 'string'],
        ];
    }
```
This is already reasonably safe — nullable string, safely decoded with a fallback that never throws. The audit flagged it as low-risk MIN specifically because it's IT-Expert/MAO-only (not farmer-facing) and doesn't currently cause any known problem. **Recommendation: skip this item unless someone is already editing this controller for another reason** — adding real schema validation here means designing what a valid report payload actually looks like across every `report_type`, which is more design work than this one-line finding justifies. If picked up later, the minimal version is just a size cap:
```php
            'payload' => ['nullable', 'string', 'max:10000'],
```
to prevent an unbounded blob, without attempting full schema validation.

### 4.7 [MIN] Literature/citation gaps

Not a code task — track separately in the manuscript, not in this repo's issue tracker. `docs/capstone/ICLIMATE_CHAPTERS_1_TO_3_MASTER.md` has 15 `[LITERATURE SOURCE REQUIRED]` markers as of 2026-09-11; the domain thresholds throughout `app/Services/DecisionSupportService.php`, `app/Services/Risk/AgriculturalRiskScorer.php`, and `python_scripts/rice_yield_service.py`/`predict.py` all trace to engineering judgment, not cited agronomy sources. No file-level fix applies here — this is a manuscript-writing task for whoever owns Chapter II/III.

---

## Summary checklist for the executing agent

Work through in order: **0.1 → 0.2 → 1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 1.6 → 2.1 → 2.2 → 2.3 → 2.4 → (Phase 3 blocked, skip until data arrives) → 4.1 → 4.2 → 4.3 → 4.4 → 4.5 → 4.6 (optional/skip) → 4.7 (not a code task)**.

After each item: verify per that item's instructions, `git diff` to confirm only intended files changed, commit with a message referencing the finding number, move to the next item. If any preflight check in this document (0.1, 1.5 especially) reveals different state than documented here, stop and report rather than improvising.
