<?php

namespace App\Http\Controllers;

use App\Models\HeatmapArea;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveForecastingController extends Controller
{
    public function index(Request $request, MonthlyWeatherRandomForest $weatherForest): View
    {
        $validated = $request->validate([
            'target_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $targetMonth = isset($validated['target_month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['target_month'].'-01')->startOfMonth()
            : CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();

        $latitude = 14.0389;
        $longitude = 120.6503;
        $zoom = 11;

        $windyUrl = 'https://embed.windy.com/embed2.html?'.http_build_query([
            'lat' => $latitude,
            'lon' => $longitude,
            'width' => 1200,
            'height' => 720,
            'zoom' => $zoom,
            'level' => 'surface',
            'overlay' => 'rain',
            'product' => 'ecmwf',
        ]);

        return view('live-forecasting.index', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'zoom' => $zoom,
            'barangays' => LianBarangays::all(),
            'barangayDetails' => $this->barangayDetails(),
            'targetMonth' => $targetMonth,
            'modelForecast' => $weatherForest->predict($targetMonth),
            'windyUrl' => $windyUrl,
        ]);
    }

    private function barangayDetails(): array
    {
        $areas = HeatmapArea::query()
            ->whereIn('barangay', LianBarangays::all())
            ->get()
            ->keyBy('barangay');

        return collect(LianBarangays::all())->mapWithKeys(function (string $barangay) use ($areas): array {
            $area = $areas->get($barangay);
            $coordinates = LianBarangays::coordinateFor($barangay);

            return [
                $barangay => [
                    'name' => $barangay,
                    'latitude' => $area?->latitude ?? ($coordinates[0] ?? null),
                    'longitude' => $area?->longitude ?? ($coordinates[1] ?? null),
                    'risk_level' => $area?->risk_level ?: 'For monitoring',
                    'risk_type' => $area?->risk_type ?: 'Live weather watch',
                    'rainfall_status' => $area?->rainfall_status ?: 'Check the Windy rain layer for the latest movement over this barangay.',
                    'planting_advisory' => $area?->planting_advisory ?: 'Use the Lian-only upcoming prediction before making planting decisions.',
                    'irrigation_recommendation' => $area?->irrigation_recommendation ?: 'Confirm field moisture locally and monitor rainfall movement on the live map.',
                    'description' => $area?->description ?: 'This barangay is included in the Lian forecast scope. Windy remains a universal live weather map, while iClimate prediction guidance is limited to Lian, Batangas.',
                ],
            ];
        })->all();
    }
}
