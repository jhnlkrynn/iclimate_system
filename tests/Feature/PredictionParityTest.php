<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\RiceYieldPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictionParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.groq.enabled', false);
        Config::set('services.farming_ai.url', 'http://farming-ai.test');

        $this->seedClimateRecords();
        $this->seedProductionRecords();

        Http::fake([
            'farming-ai.test/predict' => function (Request $request) {
                $features = $request->data()['features'] ?? [];
                $area = (float) ($features['area'] ?? 0);
                $seasonBoost = ($features['season'] ?? '') === ClimateRecord::SEASON_WET ? 0.18 : -0.12;
                $farmTypeBoost = ($features['farm_type'] ?? '') === FarmerProfile::FARM_TYPE_IRRIGATED ? 0.09 : 0.0;
                $predicted = round(3.14 + ($area * 0.11) + $seasonBoost + $farmTypeBoost, 2);

                return Http::response([
                    'rice_yield_prediction' => [
                        'predicted_yield' => $predicted,
                        'unit' => 'tons/hectare',
                    ],
                    'confidence_score' => 88,
                ]);
            },
        ]);
    }

    public function test_shared_rice_yield_service_and_ai_assistant_return_identical_results_for_same_inputs(): void
    {
        $cases = [
            ['area' => 1.0, 'season' => ClimateRecord::SEASON_WET, 'farm_type' => FarmerProfile::FARM_TYPE_RAINFED],
            ['area' => 2.0, 'season' => ClimateRecord::SEASON_WET, 'farm_type' => FarmerProfile::FARM_TYPE_RAINFED],
            ['area' => 2.5, 'season' => ClimateRecord::SEASON_DRY, 'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED],
            ['area' => 5.0, 'season' => ClimateRecord::SEASON_WET, 'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED],
        ];

        $service = app(RiceYieldPredictionService::class);
        $targetDate = PredictionDateValidator::defaultTargetDate();

        foreach ($cases as $case) {
            $user = $this->farmer($case['area'], $case['farm_type']);
            $systemResult = $service->predictForUser(
                $targetDate,
                $case['farm_type'],
                $case['area'],
                $user,
                ['season' => $case['season']],
            );

            $question = sprintf(
                'Predict my rice yield for %.1f hectares during the %s season for %s.',
                $case['area'],
                strtolower($case['season']),
                $targetDate->toDateString(),
            );

            $aiResponse = $this->actingAs($user)->postJson(route('ai-chat.message'), [
                'question' => $question,
                'save_conversation' => false,
            ]);

            $aiResponse->assertOk();
            $chatYield = $aiResponse->json('chat.rice_yield_prediction');

            $this->assertSame($systemResult['predicted_yield_tons_per_hectare'], $chatYield['predicted_yield_tons_per_hectare'], 'Yield mismatch for '.json_encode($case));
            $this->assertSame($systemResult['estimated_total_production_tons'], $chatYield['estimated_total_production_tons'], 'Total production mismatch for '.json_encode($case));
            $this->assertSame($systemResult['season'], $chatYield['season'], 'Season mismatch for '.json_encode($case));
            $this->assertSame($systemResult['model_version'], $chatYield['model_version'], 'Model version mismatch for '.json_encode($case));
            $this->assertEquals($systemResult['features'], $chatYield['features'], 'Ordered feature mismatch for '.json_encode($case));
        }
    }

    public function test_prediction_page_consumes_same_standardized_result_shape(): void
    {
        $user = $this->farmer(2.5, FarmerProfile::FARM_TYPE_RAINFED);
        $targetDate = PredictionDateValidator::defaultTargetDate();

        $serviceResult = app(RiceYieldPredictionService::class)->predictForUser(
            $targetDate,
            FarmerProfile::FARM_TYPE_RAINFED,
            null,
            $user,
        );

        $pageResponse = $this->actingAs($user)->post(route('rice-yield-predictions.predict'), [
            'prediction_type' => 'rice_yield',
            'prediction_date' => $targetDate->toDateString(),
            'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            'area' => 2.5,
        ]);

        $pageResponse->assertOk()->assertSee('Prediction Result');
        $pageResult = $pageResponse->viewData('mlResult');

        $this->assertSame($serviceResult['predicted_yield_tons_per_hectare'], $pageResult['predicted_yield_tons_per_hectare']);
        $this->assertSame($serviceResult['estimated_total_production_tons'], $pageResult['estimated_total_production_tons']);
        $this->assertSame($serviceResult['model_version'], $pageResult['model_version']);
        $this->assertSame($serviceResult['features'], $pageResult['features']);
    }

    public function test_shared_service_exposes_exact_model_feature_order(): void
    {
        $this->assertSame([
            'RAINFALL',
            'TEMP_AVG',
            'TEMP_RANGE',
            'Area',
            'Previous_Rainfall',
            'Previous_Temp',
            'Rainfall_6M',
            'Temp_3M',
            'Temp_6M',
            'Seasonal_Rainfall',
            'Seasonal_Temp',
            'Season',
        ], array_values(RiceYieldPredictionService::INPUT_TO_MODEL_FEATURE));
    }

    private function farmer(float $area, string $farmType): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => 'Balibago',
        ]);

        FarmerProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'farm_area' => $area,
            'farm_type' => $farmType,
            'barangay' => 'Balibago',
        ]);

        return $user;
    }

    private function seedClimateRecords(): void
    {
        foreach (range(1, 8) as $month) {
            ClimateRecord::query()->create([
                'record_date' => sprintf('2026-%02d-15', $month),
                'rainfall' => 86 + ($month * 9),
                'temperature' => 26.8 + ($month * 0.25),
                'humidity' => 70 + $month,
                'wind_speed' => 7.5 + ($month * 0.35),
                'season' => $month >= 5 ? ClimateRecord::SEASON_WET : ClimateRecord::SEASON_DRY,
                'source' => 'PAGASA test fixture',
            ]);
        }
    }

    private function seedProductionRecords(): void
    {
        foreach ([ClimateRecord::SEASON_WET => 4.0, ClimateRecord::SEASON_DRY => 3.5] as $season => $yield) {
            RiceProduction::query()->create([
                'barangay' => 'Balibago',
                'season' => $season,
                'irrigation_type' => FarmerProfile::FARM_TYPE_RAINFED,
                'yield_per_hectare' => $yield,
                'area_hectares' => 2.5,
                'total_production' => $yield * 2.5,
                'year' => 2025,
            ]);
        }
    }
}
