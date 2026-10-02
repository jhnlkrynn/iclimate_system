Paste this to Claude in the ORIGINAL project, after unzipping this bundle to ./_incoming
(the `files/` folder mirrors the repo structure).

---

I'm porting UI and dashboard work from another copy of this system (iClimate, Laravel)
into this project. The source files are in ./_incoming/files (same structure as this
repo), plus ./_incoming/iclimate-changes.patch (diff against the old base) and
./_incoming/file-list.txt.

Do NOT overwrite files blindly. Work role by role, and for each role:
1. List every file the dashboard depends on: Blade views/partials, CSS/JS, controller
   methods, Analytics services, Support classes, config, migrations, routes, tests.
2. Diff each against my current file. Flag any file where my current version has changes
   NOT in the incoming copy, and ask me before merging.
3. Copy/merge in dependency order: migrations, models, Support classes, services,
   controllers, routes, views, CSS/JS, then tests.
4. Run php artisan migrate, npm run build, and related tests; report failures.

SHARED (do first)
- components/kpi-strip, components/dashboard-overview
- resources/css: glass-system, chart-explainer, ui-refresh, app; js: chart-explainer, app
- layouts/app, sidebar, navigation
- Services/Analytics: DashboardOverviewService, DashboardKpiService, DataProvenanceService
- Support/: SampleData, SampleDataDetector, DashboardData, ProjectYieldDataset,
  SchemaCache, BusinessClock, LianLocation, WeatherConditionLabel
- config/iclimate.php, config/services.php, DashboardController
- migration 2026_09_30_000001_add_category_to_notifications_table
- public/images/rice-roles-terrace.jpg
- routes/web.php and routes/console.php, AppServiceProvider (MERGE BY HAND)

FARMER
- views/dashboards/farmer.blade.php, dashboards/farmer/*, dashboards/partials/farmer-*
- farmer-dashboard.css/js
- FarmerDashboardService, FarmerCropPlanService, FarmerDashboardWeatherService,
  FarmerPlantingScheduleController
- migration 2026_09_30_000002_add_harvest_record_to_farmer_planting_schedules_table
- tests: FarmerDashboard*, FarmerCropPlanServiceTest, FarmerPlantingHarvestCalendarTest

MAO
- dashboards/mao.blade.php, dashboards/mao/*, mao-operations.blade.php
- MaoDashboardService, BarangayRiskMapService
- tests: MaoDashboard*

IT EXPERT / ADMIN
- dashboards/it-expert.blade.php, dashboards/it/*
- ItDashboardService, SystemHealthService
- tests: ItDashboardTest, AdminDashboardOrganizationChartTest

Start with SHARED, then FARMER, then stop and show me what changed before MAO and IT.
