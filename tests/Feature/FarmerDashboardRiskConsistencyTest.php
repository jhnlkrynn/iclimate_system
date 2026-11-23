<?php

namespace Tests\Feature;

use App\Services\Analytics\FarmerDashboardService;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\Weather\FarmerDashboardWeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class FarmerDashboardRiskConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_message_never_shows_bad_weather_when_barangay_risk_is_low(): void
    {
        $service = app(FarmerDashboardService::class);
        $method = new ReflectionMethod($service, 'message');
        $method->setAccessible(true);

        $weekWithMismatchedHeavyRain = [
            'days' => [
                ['is_today' => true, 'heavy' => true, 'strong_wind' => false],
                ['is_today' => false, 'heavy' => false, 'strong_wind' => false],
            ],
        ];
        $lowRiskWithCautionPlanting = [
            'state' => 'ok',
            'key' => 'low',
            'planting_key' => 'wait_rain',
        ];

        $message = $method->invoke($service, $weekWithMismatchedHeavyRain, $lowRiskWithCautionPlanting, null, ['cautions' => false]);

        $this->assertNotSame('watch', $message['tone']);
        $this->assertNotSame('danger', $message['tone']);
        $this->assertSame('plant_possible', $message['key']);
    }
}
