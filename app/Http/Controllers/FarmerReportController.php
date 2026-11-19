<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\HistoricalRiceProduction;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\FarmerLanguage;
use App\Support\SchemaCache;
use App\Support\RiceSeason;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manuscript – Naicel (2026-10-08), Phase 7: My Reports (farmer, opened from My Farm). The farmer's own prediction
 * summary (this season's estimate per farm, their yield predictions, predicted vs actual harvests) and advisory summary
 * (advisories for their barangay and Lian in the last 90 days, alerts received and read). Download as PDF or print.
 */
class FarmerReportController extends Controller
{
    public function index(Request $request): View
    {
        app()->setLocale(FarmerLanguage::resolve($request));

        return view('farmer-records.my-reports', $this->data($request->user()) + ['print' => $request->boolean('print')]);
    }

    public function pdf(Request $request): Response
    {
        app()->setLocale(FarmerLanguage::resolve($request));
        $data = $this->data($request->user());

        return Pdf::loadView('farmer-records.my-reports-pdf', $data)->setPaper('a4')
            ->download(Str::slug(__('my_reports.title').' '.$request->user()->name).'.pdf');
    }

    private function data(User $user): array
    {
        $farms = rescue(fn () => ActiveFarm::available() ? Farm::query()->ownedBy($user)->active()->orderByDesc('is_primary')->get() : collect(), collect(), false);
        $estimates = $farms->map(function (Farm $farm) use ($user): array {
            $stored = FarmerDashboardService::storedEstimate($user->id, $farm->getKey());
            // The same gate as the Farmer dashboard card: no unverified model number on the page or in the PDF.
            $shown = $stored !== null && \App\Services\Prediction\YieldModelGate::shows(is_array($stored['model_input'] ?? null) ? $stored['model_input'] : null);

            return [
                'farm' => $farm->farm_name,
                'barangay' => $farm->barangay,
                'yield' => $shown ? (data_get($stored, 'yield_tons_per_hectare') ?? data_get($stored, 'predicted_yield')) : null,
            ];
        });
        $barangay = $user->registeredBarangay();
        $since = now()->subDays(90);

        return [
            'user' => $user,
            'barangay' => $barangay,
            'office' => ReportExtrasController::office(),
            'printedAt' => now(BusinessClock::timezone()),
            'estimates' => $estimates,
            'showStoredEstimates' => \App\Services\Prediction\YieldModelGate::showsStoredEstimate(),
            'requests' => SchemaCache::hasTable('yield_prediction_requests')
                ? YieldPredictionRequest::query()->where('user_id', $user->id)->where('status', YieldPredictionRequest::DONE)->latest()->limit(20)->get()
                : collect(),
            'harvests' => FarmerPlantingSchedule::query()->with('farm')->where('user_id', $user->id)
                ->where('actual_yield', '>', 0)->where('predicted_yield', '>', 0)->latest('harvested_on')->limit(20)
                ->get(['id', 'farm_id', 'barangay', 'planting_date', 'farm_area_hectares', 'harvested_on', 'season', 'predicted_yield', 'actual_yield'])
                ->map(function (FarmerPlantingSchedule $harvest): FarmerPlantingSchedule {
                    $window = $harvest->planting_date ? RiceSeason::window($harvest->planting_date) : null;
                    $barangay = $harvest->farm?->barangay ?: $harvest->barangay;
                    $harvest->historical_yield = $window && $barangay
                        ? HistoricalRiceProduction::query()->whereRaw('upper(barangay) = ?', [mb_strtoupper(trim($barangay))])
                            ->where('season', $window['season'] === RiceSeason::WET ? 'WS' : 'DS')->where('season_year', $window['start']->year)
                            ->value('average_yield_t_per_hectare')
                        : null;
                    $area = $harvest->farm?->knownHectares() ?? (is_numeric($harvest->farm_area_hectares) ? (float) $harvest->farm_area_hectares : null);
                    $harvest->actual_total_tons = $area !== null ? round((float) $harvest->actual_yield * $area, 2) : null;

                    return $harvest;
                }),
            'advisories' => PlantingAdvisory::query()->published()->forBarangay($barangay)
                ->where('published_at', '>=', $since)->latest('published_at')->limit(30)
                ->get(['id', 'title', 'severity', 'target_barangay', 'published_at', 'recommended_action']),
            'alerts' => [
                'count' => Notification::query()->where('user_id', $user->id)->where('type', 'Advisory')->where('created_at', '>=', $since)->count(),
                'read' => Notification::query()->where('user_id', $user->id)->where('type', 'Advisory')->where('created_at', '>=', $since)->where('is_read', true)->count(),
            ],
        ];
    }
}
