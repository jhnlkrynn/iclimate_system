<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\ActiveFarm;
use App\Support\BusinessClock;
use App\Support\FarmerLanguage;
use App\Support\SchemaCache;
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
        $estimates = $farms->map(fn (Farm $farm) => [
            'farm' => $farm->farm_name,
            'barangay' => $farm->barangay,
            'yield' => data_get(FarmerDashboardService::storedEstimate($user->id, $farm->getKey()), 'yield_tons_per_hectare')
                ?? data_get(FarmerDashboardService::storedEstimate($user->id, $farm->getKey()), 'predicted_yield'),
        ]);
        $barangay = $user->registeredBarangay();
        $since = now()->subDays(90);

        return [
            'user' => $user,
            'barangay' => $barangay,
            'office' => ReportExtrasController::office(),
            'printedAt' => now(BusinessClock::timezone()),
            'estimates' => $estimates,
            'requests' => SchemaCache::hasTable('yield_prediction_requests')
                ? YieldPredictionRequest::query()->where('user_id', $user->id)->where('status', YieldPredictionRequest::DONE)->latest()->limit(20)->get()
                : collect(),
            'harvests' => FarmerPlantingSchedule::query()->where('user_id', $user->id)
                ->where('actual_yield', '>', 0)->where('predicted_yield', '>', 0)->latest('harvested_on')->limit(20)
                ->get(['harvested_on', 'season', 'predicted_yield', 'actual_yield']),
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
