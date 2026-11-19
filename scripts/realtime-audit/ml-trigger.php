<?php
// Usage: RT_APP_DIR=<isolated copy> php scripts/realtime-audit/ml-trigger.php yield-request|registry <token>
// One real ML-side change in the disposable clone only (same guard as trigger.php). Prints JSON.
$appDir = getenv('RT_APP_DIR') ?: dirname(__DIR__, 2);
require $appDir.'/vendor/autoload.php';
$app = require $appDir.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\RunYieldPredictionRequest;
use App\Models\{MlModel, User, YieldPredictionRequest};
use Illuminate\Support\Facades\DB;

if (! str_contains((string) DB::connection()->getDatabaseName(), 'rt_audit')) {
    fwrite(STDERR, "refusing: not the disposable clone\n");
    exit(2);
}
[$_, $scenario, $tok] = $argv + [null, null, 'RT'];
$mao = User::findOrFail((int) (getenv('RT_MAO_ID') ?: 2));
$out = ['t0' => microtime(true)];
switch ($scenario) {
    case 'yield-request':   // what YieldPredictionController::queue does for a MAO barangay forecast
        $season = App\Support\RiceSeason::forMonth((int) now(App\Support\BusinessClock::timezone())->month);
        $r = YieldPredictionRequest::query()->create(['user_id' => $mao->id, 'scope' => YieldPredictionRequest::SCOPE_BARANGAY, 'barangay' => 'Kapito',
            'season' => $season, 'farm_type' => 'Irrigated', 'area_hectares' => 100 + random_int(1, 99) / 100,
            'target_month' => YieldPredictionRequest::targetMonthFor($season)->toDateString(), 'status' => YieldPredictionRequest::QUEUED]);
        RunYieldPredictionRequest::start($r);
        // Wait (max 30 s) for the worker + Python result, so the probe can look for the predicted tonnage itself.
        for ($i = 0; $i < 300 && ($r->refresh()->status === YieldPredictionRequest::QUEUED || $r->status === 'running'); $i++) usleep(100000);
        $out['status'] = $r->status;
        $out['total_tons'] = $r->total_tons === null ? null : number_format((float) $r->total_tons, 2);
        $out['done_after_ms'] = $r->completed_at ? (int) round((microtime(true) - $out['t0']) * 1000) : null;
        $out['id'] = $r->id;
        $out['area'] = (float) $r->area_hectares;
        break;
    case 'registry':        // the candidate model re-evaluated: new metrics in the registry
        $m = MlModel::query()->where('status', 'candidate')->firstOrFail();
        $m->forceFill(['r2' => round(0.5 + random_int(1, 400) / 1000, 3), 'mae' => round(0.3 + random_int(1, 300) / 1000, 3)])->save();
        $out['id'] = $m->id; $out['r2'] = (float) $m->r2; $out['mae'] = (float) $m->mae;
        break;
    default:
        fwrite(STDERR, "unknown scenario\n");
        exit(1);
}
$out['t'] = microtime(true);
echo json_encode($out), "\n";
