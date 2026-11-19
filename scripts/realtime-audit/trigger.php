<?php
// Usage: RT_APP_DIR=<isolated app copy> php scripts/realtime-audit/trigger.php <scenario> <token>
// Makes ONE controlled change in a disposable clone database and prints {"t0":…, "t":<ms after commit>, …} as JSON.
// Guard: refuses to run unless the app's database name contains "rt_audit" (see README.md).

$appDir = getenv('RT_APP_DIR') ?: dirname(__DIR__, 2);
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{PlantingAdvisory, Notification, FeedPost, ClimateRecord, RiceProduction, FarmerPlantingSchedule, Farm, FieldReport, User, YieldPredictionRequest};
use Illuminate\Support\Facades\DB;

if (! str_contains((string) DB::connection()->getDatabaseName(), 'rt_audit')) {
    fwrite(STDERR, "refusing: not the disposable clone\n");
    exit(2);
}

[$_, $scenario, $tok] = $argv + [null, null, 'RT'];
$farmer = User::findOrFail((int) (getenv('RT_FARMER_ID') ?: 1));   // a farmer with a barangay and farm RT_FARM_ID
$mao = User::findOrFail((int) (getenv('RT_MAO_ID') ?: 2));
$now = now();
$out = [];

$run = function () use ($scenario, $tok, $farmer, $mao, $now, &$out) {
    switch ($scenario) {
        case 'advisory':      // published advisory for the farmer's barangay
            $a = PlantingAdvisory::query()->create([
                'title' => "Advisory {$tok}", 'content' => "Check fields {$tok}.", 'message' => "Check fields {$tok}.",
                'summary' => "Check fields {$tok}.", 'recommended_action' => "Do the thing {$tok}.",
                'type' => 'Planting', 'advisory_type' => 'planting', 'severity' => 'high',
                'target_scope' => 'barangay', 'target_barangay' => $farmer->registeredBarangay(),
                'source' => 'MAO-Reviewed Advisory', 'status' => 'published',
                'valid_from' => $now->copy()->subHour(), 'valid_until' => $now->copy()->addDays(2), 'posted_by' => $mao->id,
            ]);
            $out['id'] = $a->id;
            break;
        case 'publish-notify':   // what PlantingAdvisoryController does after publishing: notify the barangay's farmers
            $a = PlantingAdvisory::query()->create([
                'title' => "Published {$tok}", 'content' => "Published {$tok}.", 'message' => "Published {$tok}.", 'summary' => "Published {$tok}.",
                'recommended_action' => "Act {$tok}.", 'type' => 'Planting', 'advisory_type' => 'planting', 'severity' => 'high',
                'target_scope' => 'barangay', 'target_barangay' => $farmer->registeredBarangay(), 'source' => 'MAO-Reviewed Advisory',
                'status' => 'published', 'posted_by' => $mao->id, 'valid_from' => $now->copy()->subHour(), 'valid_until' => $now->copy()->addDays(2),
            ]);
            DB::afterCommit(function () use ($a, &$out) { $out['notified'] = app(\App\Services\Advisories\AdvisoryPublishedNotifier::class)->notify($a); });
            $out['id'] = $a->id;
            break;
        case 'advisory-pending':
            $a = PlantingAdvisory::query()->create([
                'title' => "Pending {$tok}", 'content' => "Pending {$tok}.", 'message' => "Pending {$tok}.", 'summary' => "Pending {$tok}.",
                'recommended_action' => "Review {$tok}.", 'type' => 'Planting', 'advisory_type' => 'planting', 'severity' => 'moderate',
                'target_scope' => 'barangay', 'target_barangay' => $farmer->registeredBarangay(), 'source' => 'iClimate Local Guidance',
                'status' => 'pending_review', 'posted_by' => $mao->id, 'valid_from' => $now->copy()->subHour(), 'valid_until' => $now->copy()->addDays(2),
            ]);
            $out['id'] = $a->id;
            break;
        case 'notification':
            $n = Notification::query()->create(['user_id' => $farmer->id, 'title' => "Notice {$tok}", 'message' => "Hello {$tok}", 'type' => 'Announcement', 'category' => 'system', 'is_read' => false]);
            $out['id'] = $n->id;
            break;
        case 'post':          // MAO announcement
            $p = FeedPost::query()->create(['user_id' => $mao->id, 'title' => "Post {$tok}", 'body' => "Body {$tok}", 'category' => 'Announcement', 'visibility' => 'All Users']);
            $out['id'] = $p->id;
            break;
        case 'climate':
            ClimateRecord::query()->whereDate('record_date', $now->toDateString())->where('source', 'like', 'RT %')->delete();
            $c = ClimateRecord::query()->create(['record_date' => $now->toDateString(), 'rainfall' => 12.3, 'temperature' => 29.1, 'humidity' => 81, 'wind_speed' => 9, 'season' => 'Wet', 'source' => "RT {$tok}"]);
            $out['id'] = $c->id;
            break;
        case 'rice':
            RiceProduction::query()->where('remarks', 'like', 'RT %')->delete();
            $r = RiceProduction::query()->create(['barangay' => $farmer->registeredBarangay(), 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => $y = round(3 + mt_rand(0, 3000) / 1000, 3), 'area_hectares' => 10, 'total_production' => round($y * 10, 2), 'year' => (int) $now->year, 'production_month' => $now->format('Y-m-01'), 'remarks' => "RT {$tok}"]);
            $out['id'] = $r->id;
            break;
        case 'schedule':
            $s = FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'farm_id' => (int) (getenv('RT_FARM_ID') ?: 1), 'planting_date' => $now->copy()->addDays(3)->toDateString(), 'season' => 'Dry', 'farm_type' => 'Irrigated', 'barangay' => $farmer->registeredBarangay(), 'farm_area_hectares' => 1, 'notes' => "RT {$tok}", 'progress_status' => 'planned']);
            $out['id'] = $s->id;
            break;
        case 'farm':
            $f = Farm::query()->findOrFail((int) (getenv('RT_FARM_ID') ?: 1));
            $out['before'] = $f->farm_name;
            $f->update(['farm_name' => "Farm {$tok}"]);
            $out['id'] = $f->id;
            break;
        case 'field-report':
            $r = FieldReport::query()->create(['title' => "Report {$tok}", 'barangay' => $farmer->registeredBarangay(), 'issue_type' => 'Pest/Disease', 'description' => "RT {$tok}", 'status' => 'Pending', 'reported_by' => $mao->id]);
            $out['id'] = $r->id;
            break;
        case 'user':          // a new farmer account (registration)
            $u = User::query()->create(['name' => "Farmer {$tok}", 'email' => strtolower($tok).'@rt-audit.invalid', 'password' => bcrypt(bin2hex(random_bytes(8))), 'role' => User::ROLE_FARMER, 'status' => 'Active']);
            $out['id'] = $u->id;
            break;
        case 'user-pending':  // a registration waiting for MAO approval
            $u = User::query()->create(['name' => "Farmer {$tok}", 'email' => strtolower($tok).'@rt-audit.invalid', 'password' => bcrypt(bin2hex(random_bytes(8))), 'role' => User::ROLE_FARMER, 'status' => User::STATUS_PENDING]);
            $out['id'] = $u->id;
            break;
        // Added 2026-11-11 for the remaining pages (docs/realtime-status.md).
        case 'barangay':      // MAO edits a barangay profile (Barangays page; Heat Map flood layer)
            $b = \App\Models\BarangayProfile::query()->firstOrNew(['barangay' => 'Kapito']);
            $out['before'] = [(bool) $b->flood_prone, $b->farm_area_hectares];
            $b->fill(['flood_prone' => ! $b->flood_prone, 'farm_area_hectares' => mt_rand(100, 999) + 0.25])->save();
            $out['id'] = $b->id;
            break;
        case 'planner':
            $a = \App\Models\MaoActivity::query()->create(['user_id' => $mao->id, 'activity_type' => 'training', 'barangay' => null, 'starts_on' => $now->copy()->addDays(2)->toDateString(), 'title' => "Training {$tok}", 'notes' => "RT {$tok}"]);
            $out['id'] = $a->id;
            break;
        case 'activity-log':
            $l = \App\Models\MaoActivityLog::query()->create(['user_id' => $mao->id, 'user_name' => $mao->name, 'action' => \App\Models\MaoActivityLog::ACTIONS[0], 'description' => "RT {$tok}", 'record_type' => 'Test', 'record_label' => "Record {$tok}", 'created_at' => $now]);
            $out['id'] = $l->id;
            break;
        case 'alert':         // MAO sends an alert (Send Alert history; farmers get the bell notice)
            $r = app(\App\Services\Advisories\AdvisoryAlertService::class)->create(['title' => "Alert {$tok}", 'type' => 'general', 'severity' => 'moderate', 'message' => "RT {$tok}", 'recommended_action' => null, 'barangays' => [$farmer->registeredBarangay()], 'scheduled_for' => null], $mao);
            $out['id'] = $r['advisories']->pluck('id')->all();
            break;
        case 'alert-read':    // a farmer opens the newest MAO alert (its Read count)
            $n = Notification::query()->whereNotNull('planting_advisory_id')->where('user_id', $farmer->id)->where('is_read', false)->latest('id')->firstOrFail();
            $n->update(['is_read' => true, 'read_at' => $now]);
            $out['id'] = $n->id;
            break;
        case 'comment':
            $post = FeedPost::query()->latest('id')->firstOrFail();
            $c = \App\Models\FeedComment::query()->create(['feed_post_id' => $post->id, 'user_id' => $farmer->id, 'body' => "Comment {$tok}"]);
            $out['id'] = $c->id;
            break;
        case 'maintenance':
            $m = \App\Models\MaintenanceRecord::query()->create(['title' => "Maintenance {$tok}", 'category' => 'Server', 'description' => "RT {$tok}", 'status' => 'Completed', 'performed_at' => $now, 'performed_by' => $mao->id]);
            $out['id'] = $m->id;
            break;
        case 'system-log':
            // The list shows the action label (details open in a dialog): a new action name is what appears.
            $l = \App\Models\SystemLog::query()->create(['user_id' => $mao->id, 'action' => 'rt_audit_'.strtolower($tok), 'details' => "RT {$tok}"]);
            $out['id'] = $l->id;
            break;
        case 'impact':
            $w = \App\Models\WeatherImpactAnalysis::query()->create(['title' => "Analysis {$tok}", 'filters' => [], 'results' => [], 'sample_size' => 12, 'strongest_variable' => 'rainfall', 'impact_level' => 'Moderate', 'notes' => "RT {$tok}", 'created_by' => $mao->id]);
            $out['id'] = $w->id;
            break;
        case 'forecast-request':   // a barangay forecast another MAO user ran (Yield Forecast history)
            $y = YieldPredictionRequest::query()->create(['user_id' => $mao->id, 'scope' => YieldPredictionRequest::SCOPE_BARANGAY, 'barangay' => 'Kapito', 'season' => 'Dry', 'farm_type' => 'Irrigated', 'area_hectares' => 123.45, 'target_month' => $now->copy()->addMonth()->format('Y-m-01'), 'status' => 'done', 'predicted_yield' => 4.321, 'total_tons' => 533.4, 'completed_at' => $now]);
            $out['id'] = $y->id;
            break;
        case 'survey':
            $f = \App\Models\FarmerSurveyResponse::query()->create(['user_id' => $farmer->id, 'subject' => 'prediction', 'category' => \App\Models\FarmerSurveyResponse::CATEGORY_MODEL, 'barangay' => $farmer->registeredBarangay(), 'useful' => false, 'accurate' => false, 'helpful' => false, 'comment' => "Survey {$tok}", 'answered_on' => $now->toDateString()]);
            $out['id'] = $f->id;
            break;
        case 'health':        // a new failed job: the prepared System Health result changes (scheduler warm, every 2 min)
            DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'rt-audit', 'payload' => json_encode(['displayName' => "RT {$tok}"]), 'exception' => "RT {$tok}", 'failed_at' => $now]);
            DB::afterCommit(fn () => app(\App\Http\Controllers\SystemHealthController::class)->warm(app(\App\Services\SystemHealthService::class)));
            break;
        case 'variety':
            $v = \App\Models\RiceVariety::query()->catalog([])->firstOrFail();   // the first row the list shows
            $out['before'] = [$v->variety_name, $v->yield_typical];
            // A unique name: a new yield figure alone often matches another row's (6.1 t/ha), so it would not stand out.
            $v->update(['variety_name' => "NSIC {$tok}", 'yield_typical' => mt_rand(4000, 7999)]);
            $out['id'] = $v->id;
            break;
        case 'farmer-profile':    // a new farmer with a profile (Farmer Profiles list)
            $u = User::query()->create(['name' => "Farmer {$tok}", 'email' => strtolower($tok).'@rt-audit.invalid', 'password' => bcrypt(bin2hex(random_bytes(8))), 'role' => User::ROLE_FARMER, 'status' => 'Active']);
            $p = \App\Models\FarmerProfile::query()->create(['user_id' => $u->id, 'full_name' => "Farmer {$tok}", 'barangay' => 'Kapito', 'farm_area' => 1.5, 'farm_type' => 'Irrigated', 'address' => 'Kapito, Lian']);
            $out['id'] = $p->id;
            break;
        case 'association-request':   // a farmer's "my association isn't listed" answer (Farm Associations review)
            $f = Farm::query()->findOrFail((int) (getenv('RT_FARM_ID') ?: 1));
            $f->forceFill(['association_status' => Farm::ASSOCIATION_PENDING, 'association_other_name' => "Samahan {$tok}", 'farmer_organization_id' => null])->save();
            $out['id'] = $f->id;
            break;
        case 'historical-rice':   // one imported season row (Historical Rice list; farmer home benchmark)
            $h = \App\Models\HistoricalRiceProduction::query()->create(['barangay' => 'Kapito', 'season' => 'DS', 'season_year' => 2031, 'season_label' => "DS {$tok}", 'area_planted_hectares' => 12.5, 'average_yield_t_per_hectare' => 4.44, 'total_production_tons' => 55.5, 'source_filename' => "rt-{$tok}.xlsx", 'source_sheet' => 'RT', 'source_row' => mt_rand(1, 99999), 'source_units' => 't/ha']);
            $out['id'] = $h->id;
            break;
        default:
            throw new RuntimeException("unknown scenario {$scenario}");
    }
};

$out["t0"] = (int) round(microtime(true) * 1000);
DB::transaction($run);
$out['t'] = (int) round(microtime(true) * 1000);   // after commit: after-commit broadcasts have been sent
echo json_encode($out), "\n";
