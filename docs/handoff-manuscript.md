# Handoff: manuscript modules (Naicel → Kaye and John Luke), October 2026

The manuscript lists features for Farmer, MAO and IT that the system did not have. I added them in eight phases, one
commit each (`Manuscript Phase 1` … `Manuscript Phase 8`), so any phase can be reverted on its own. The plan and what
already existed are in [manuscript-phase-plan.md](manuscript-phase-plan.md).

This touched MAO and IT pages. Every line I added to a file that is yours is inside a block marked
`Manuscript – Naicel`. New pages have their own controllers, views and routes.

## After you pull

```
php artisan migrate
npm run build
start-services.bat restart
```

- Eight new migrations (`2026_10_08_000001` … `000008`). They only add tables and columns.
- `npm run build` is needed for the Heat Map layers (`heat-map.js`, `heat-map.css`).
- The restart loads the new queue jobs and the new scheduled command.

## What changes for people

| Change | Who notices |
|---|---|
| A farmer who signs up is **Pending** until the MAO approves them (Farmers → Farmer Approvals). Switch off with `ICLIMATE_FARMER_APPROVAL=false` or in System Settings. | Farmers, MAO |
| Records → **Import CSV**, a **Validated** column, and a third tab **Barangays**. | MAO |
| Heat Map → **Map layer**: Flood-prone, Drought-prone, Expected yield. | Everyone |
| Predictions has tabs: **Weather Analysis**, **Impact Analysis**, **Yield Forecast** (MAO); **Weather Analysis**, **Yield Prediction** (farmers). | MAO, farmers |
| Advisories → **Send Alert** (now or scheduled), with delivered and read counts. | MAO |
| Reports: four new types, **Draft / Published** with versions, **Download Excel** and **Download PDF**. | MAO |
| My Farm → **My Reports** (PDF, print). | Farmers |
| IT sidebar → **Settings**: System Settings, Security, Roles & Permissions, Backups, Maintenance Records, ML Models. System Logs → **Export CSV**. User list shows **Last Login**; a user's page has **Reset Password**. | IT |

## Where things are

| Area | Files |
|---|---|
| Routes | `routes/manuscript.php` (loaded from the last line of `routes/web.php`) |
| Tab rows that join a new page to an existing sidebar item | `resources/views/partials/manuscript-tabs.blade.php` |
| Settings IT can change | `App\Support\SystemSettings` (a JSON file in `storage/app`, so reading a setting adds no query) |
| Which role may open which module | `App\Support\ModuleAccess`, middleware `CheckModuleAccess` |
| Maintenance mode | middleware `SystemMaintenanceMode` (IT can always sign in) |
| Tests | `tests/Feature/Manuscript/` |

## Rules I kept

- **No page runs the yield model or calls a weather provider.** A yield prediction request is saved and the model runs
  after the response (`RunYieldPredictionRequest`). Backups, restore, model comparison and retraining run in the queue.
- **The MAO dashboard still makes no extra query.** Settings and permissions are read from a file.
- **Farmer pages are in English and Filipino** (`lang/en`, `lang/fil`: `weather_analysis`, `yield_request`,
  `my_reports`, and `farmer.heat_map.layers`).

## Changes inside your files

**John Luke**

- `Farm` is only read (`knownHectares()`, `modelArea()`, `ownedBy()`, `active()`), never changed.
- `resources/views/layouts/sidebar.blade.php`: your merge on 2026-10-08 left Records, Farmers and Field Reports twice
  in the MAO menu. I removed the three repeated lines.

**Kaye**

- `SystemLogController`: the filter code moved, unchanged, into a private `filtered()` method so the CSV export uses
  the same filters. `index()` shows the same page.
- `ReportController::generate()`: the four new report types are built in PHP and return early; the other types still
  go to the Python analytics.
- `AdvisoryPublishedNotifier::notify()`: each alert now stores which advisory it is about (`planting_advisory_id`), and
  IT can switch these alerts off in System Settings.
- `BarangayWeekRiskService`: the five limits (heavy rain, very heavy rain, strong wind, storm wind, hot day) can be
  changed in System Settings. With nothing saved, the constants are used, so ratings are the same as before.
- `LoginRequest`: lockout attempts and minutes come from Security settings (defaults 5 attempts, 1 minute).
- `AppServiceProvider`: the password rule and session timeout come from Security settings (same defaults as before).

## Things to know before the defense

- **Model comparison is honest about the data.** On the 60 months in the modeling dataset, Multiple Linear
  Regression, Random Forest and Gradient Boosting all have a negative R² on the 2025 hold-out. The page says so.
- **The deployed yield model still cannot be retrained** (its training data is not in the repository). Retrain makes a
  new candidate from the modeling dataset; IT validates and activates it on Model Registry.
- **Restore** works for MySQL/MariaDB backups only. It needs the word RESTORE and the IT password, and it makes a
  safety backup first.
- **Season calendar** is shown in System Settings but cannot be edited: it has to match how the yield model was trained.
