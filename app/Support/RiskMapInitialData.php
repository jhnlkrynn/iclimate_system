<?php

namespace App\Support;

use App\Http\Controllers\Api\BarangayRiskForecastController;
use App\Http\Controllers\Api\BarangayRiskMapController;
use Illuminate\Http\Request;

/**
 * The heat map's first data, written into the page (partials/barangay-risk-map) so a map opens with one request
 * instead of three. Exactly what GET /api/lian/risk-map and /api/lian/risk-forecast answer; both only read stored
 * ratings and prepared forecasts. The map's later requests (Refresh, a barangay's details) still use the APIs.
 */
class RiskMapInitialData
{
    /** @return array{risk: ?array<string, mixed>, forecast: ?array<string, mixed>} */
    public static function payloads(bool $withForecast): array
    {
        return [
            'risk' => rescue(
                fn (): array => app(BarangayRiskMapController::class)->index(Request::create('/api/lian/risk-map'))->getData(true),
                null,
                false,
            ),
            'forecast' => $withForecast
                ? rescue(fn (): array => app()->call([app(BarangayRiskForecastController::class), '__invoke'])->getData(true), null, false)
                : null,
        ];
    }
}
