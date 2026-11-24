<?php

namespace App\Http\Controllers;

use App\Services\Farmer\FarmResultsExplorationService;
use App\Support\ActiveFarm;
use App\Support\FarmerLanguage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmerExploreResultsController extends Controller
{
    public function __invoke(Request $request, FarmResultsExplorationService $service): View
    {
        $user = $request->user();
        $locale = FarmerLanguage::resolve($request);
        $activeFarm = ActiveFarm::available() ? ActiveFarm::current($user) : null;

        $defaultBarangay = $user->registeredBarangay() ?? $activeFarm?->barangay;
        $defaultType = $activeFarm?->farm_type ?: 'Irrigated';

        $filters = [
            'farm_type' => $request->query('farm_type', $defaultType),
            'barangay' => $request->has('barangay') ? ($request->query('barangay') ?: null) : $defaultBarangay,
            'season' => $request->query('season') ?: null,
            'year' => $request->query('year') ?: null,
            'variety_id' => $request->query('variety_id') ?: null,
        ];

        return view('farmer.explore-results', [
            'result' => $service->explore($filters, $locale),
            'options' => $service->filterOptions(),
            'locale' => $locale,
        ]);
    }
}
