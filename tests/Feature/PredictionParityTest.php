<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictionParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_prediction_and_ai_assistant_use_identical_inputs_and_outputs(): void
    {
        foreach (range(1, 6) as $month) {
            ClimateRecord::query()->create([
                'record_date' => sprintf('2026-%02d-15', $month),
                'rainfall' => 80 + ($month * 8),
                'temperature' => 27 + ($month * 0.2),
                'humidity' => 70 + $month,
                'wind_speed' => 8 + ($month * 0.3),
                'season' => $month >= 5 ? ClimateRecord::SEASON_WET : ClimateRecord::SEASON_DRY,
                'source' => 'PAGASA',
            ]);
        }

        RiceProduction::query()->create([
            'barangay' => 'Balibago',
            'season' => 'Wet',
            'irrigation_type' => 'Rainfed',
            'yield_per_hectare' => 4.0,
            'area_hectares' => 2.5,
            'total_production' => 10,
            'year' => 2025,
        ]);

        $capturedPayloads = [];

        Http::fake(function ($request) use (&$capturedPayloads) {
            $capturedPayloads[] = $request->data();

            return Http::response([
                'rice_yield_prediction' => ['predicted_yield' => 3.36, 'unit' => 'tons/hectare'],
                'weather_prediction' => ['predicted_weather' => 'Rain'],
            ]);
        });

        $targetDate = now()->addMonthsNoOverflow(2)->startOfMonth();
        $targetDateString = $targetDate->format('Y-m-d');

        $systemUser = User::factory()->create(['role' => User::ROLE_MAO]);

        $systemResponse = $this->actingAs($systemUser)
            ->post(route('weather-predictions.predict'), [
                'prediction_date' => $targetDateString,
                'farm_type' => 'Rainfed',
            ]);

        $systemResponse->assertOk()->assertSee('Prediction Result');

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::query()->create([
            'user_id' => $farmer->id,
            'full_name' => $farmer->name,
            'farm_area' => 2.5,
            'farm_type' => 'Rainfed',
            'barangay' => 'Balibago',
        ]);

        $aiResponse = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => "Predict rice yield for {$targetDateString}.",
        ]);

        $aiResponse->assertOk();

        $this->assertCount(2, $capturedPayloads);

        $systemFeatures = $capturedPayloads[0]['features'];
        $aiFeatures = $capturedPayloads[1]['features'];

        foreach (['rainfall', 'temp_avg', 'temp_range', 'area', 'previous_rainfall', 'previous_temp', 'previous_humidity', 'previous_wind_speed', 'rainfall_6m', 'temp_3m', 'temp_6m', 'seasonal_rainfall', 'seasonal_temp', 'humidity', 'wind_speed', 'season', 'farm_type', 'month_num'] as $key) {
            $this->assertSame($systemFeatures[$key], $aiFeatures[$key], "Mismatch on feature [{$key}] between System Prediction and AI Assistant.");
        }

        $this->assertEquals(3.36, $aiResponse->json('chat.rice_yield_prediction.predicted_yield'));
        $this->assertEquals(3.36, $systemResponse->viewData('mlResult')['predicted_yield']);
    }
}
