<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\RiceVariety;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Prediction\PredictionReadMode;
use App\Services\Prediction\YieldPredictionStore;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\ActiveFarm;
use App\Support\FarmerLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClimoraLianFarmerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.groq.enabled', false);
        config()->set('ai.prediction_result_cache_seconds', 0);
    }

    private function farmer(array $attrs = []): User
    {
        return User::factory()->create([
            'role' => User::ROLE_FARMER,
            'status' => User::STATUS_ACTIVE,
            ...$attrs,
        ]);
    }

    private function mockWeather(array $overrides = []): void
    {
        $payload = array_replace_recursive([
            'success' => true,
            'source' => 'Open-Meteo',
            'stale' => false,
            'timestamps' => ['server_fetched_at' => now()->toIso8601String()],
            'location' => ['name' => 'Lian', 'province' => 'Batangas'],
            'current' => [
                'condition' => 'Partly Cloudy',
                'temperature' => 30.5,
                'humidity' => 75,
                'wind_speed' => 12,
            ],
            'today' => ['rainfall' => 2.0],
            'forecast' => [[
                'day' => 'Tomorrow',
                'date' => now()->addDay()->toDateString(),
                'condition' => 'Light Rain',
                'rainfall' => 5.0,
                'precipitation_probability' => 40,
                'temperature_max' => 31.0,
            ]],
        ], $overrides);

        $this->mock(FarmerDashboardWeatherService::class, function ($mock) use ($payload): void {
            $mock->shouldReceive('current')->andReturn($payload);
        });
    }

    public function test_no_farm_incomplete_profile_answers_general_weather_with_complete_profile_link(): void
    {
        $this->mockWeather();
        $farmer = $this->farmer(['barangay' => '—']);

        $response = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'Weather this week',
        ]);

        $response->assertOk();
        $answer = (string) $response->json('chat.answer');
        $this->assertStringContainsString('What to do:', $answer);
        $this->assertStringContainsString('/farmer/complete-profile', $answer);
        $this->assertStringContainsString('Updated', $answer);
    }

    public function test_multi_farm_switch_and_farm_2_and_barangay_override(): void
    {
        $this->mockWeather();
        $this->mock(LianBarangayWeatherService::class, function ($mock): void {
            $mock->shouldReceive('getCurrentWeather')->andReturn([
                'status' => 'ok',
                'condition' => 'Clear',
                'temperature_c' => 29.0,
                'humidity_percent' => 70,
                'rainfall_24h_mm' => 0.0,
                'rain_probability_percent' => 10,
                'wind_speed_kmh' => 8,
                'fetched_at' => now()->toIso8601String(),
                'stale' => false,
            ]);
            $mock->shouldReceive('refreshIfDue')->andReturnFalse();
        });

        $farmer = $this->farmer(['barangay' => 'Binubusan']);
        $variety1 = RiceVariety::query()->create(['import_key' => 'NSIC-222', 'variety_name' => 'NSIC Rc 222', 'maturity_tpr_ws' => 114, 'is_active' => true]);
        $variety2 = RiceVariety::query()->create(['import_key' => 'NSIC-160', 'variety_name' => 'NSIC Rc 160', 'maturity_tpr_ws' => 107, 'is_active' => true]);

        $farm1 = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
        ]);
        $farm2 = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 2',
            'barangay' => 'Malaruhatan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 1.5,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => false,
        ]);

        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm1->id,
            'rice_variety_id' => $variety1->id,
            'planting_date' => now()->subDays(3)->toDateString(),
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => 'Binubusan',
            'farm_area_hectares' => 2.0,
        ]);
        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm2->id,
            'rice_variety_id' => $variety2->id,
            'planting_date' => now()->subDays(70)->toDateString(),
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'barangay' => 'Malaruhatan',
            'farm_area_hectares' => 1.5,
        ]);

        // Active farm is Farm 1 (NSIC Rc 222)
        $r1 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'When to harvest?',
        ]);
        $r1->assertOk();
        $this->assertStringContainsString('NSIC Rc 222', (string) $r1->json('chat.answer'));
        $this->assertStringContainsString('seedling', (string) $r1->json('chat.answer'));

        // Explicit "Farm 2" in question overrides active farm
        $r2 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'When to harvest for Farm 2?',
        ]);
        $r2->assertOk();
        $this->assertStringContainsString('NSIC Rc 160', (string) $r2->json('chat.answer'));
        $this->assertStringContainsString('flowering', (string) $r2->json('chat.answer'));

        // Switching session active farm to Farm 2
        $this->actingAs($farmer)->post(route('farmer.farms.select'), ['farm_id' => $farm2->id]);
        $r3 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'When to harvest?',
        ]);
        $r3->assertOk();
        $this->assertStringContainsString('Farm 2', (string) $r3->json('chat.answer'));

        // Barangay name in question overrides active farm barangay
        $r4 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'What is the weather in Matabungkay?',
        ]);
        $r4->assertOk();
        $this->assertNull($r4->json('chat.prediction_result.error'));
        $this->assertStringContainsString('Matabungkay', (string) $r4->json('chat.answer'));
    }

    public function test_stored_yield_prediction_uses_farm_context_and_logs_warning_when_ml_down(): void
    {
        $farmer = $this->farmer(['barangay' => 'Binubusan']);
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Bukid Uno',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
            'verification_status' => Farm::VERIFICATION_APPROVED,
        ]);

        config()->set('prediction.rice_yield.gate', 'label');
        $store = app(YieldPredictionStore::class);
        $store->saveFarm($farm, [
            'predicted_yield' => 4.25,
            'target_month' => now()->format('Y-m'),
            'model_version' => 'v1',
            'model_input' => ['area' => 2.0, 'farm_type' => 'Irrigated', 'rainfall' => 150, 'temp_avg' => 28],
            'ran_at' => now()->toIso8601String(),
        ]);

        $r1 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'My expected yield',
        ]);
        $r1->assertOk();
        $this->assertStringContainsString('4.25', (string) $r1->json('chat.answer'));
        $this->assertStringContainsString('Updated', (string) $r1->json('chat.answer'));
        $this->assertFalse(app(PredictionReadMode::class)->active());

        // Now test ML down (no stored yield, no prepared forecast) -> logs warning and mentions 3-hour refresh
        YieldPrediction::query()->delete();
        Log::spy();

        $r2 = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'My expected yield',
        ]);
        $r2->assertOk();
        $this->assertStringContainsString('every 3 hours', (string) $r2->json('chat.answer'));
    }

    public function test_typhoon_priority_comes_first_in_what_to_do(): void
    {
        $this->mockWeather();
        $farmer = $this->farmer(['barangay' => 'Binubusan']);
        Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Bukid',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 1.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
        ]);

        PlantingAdvisory::query()->create([
            'title' => 'Typhoon Signal No. 2',
            'type' => 'typhoon',
            'severity' => 'severe',
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'posted_by' => $farmer->id,
            'summary' => 'Strong winds and heavy rain expected.',
            'content' => 'Strong winds and heavy rain expected.',
            'recommended_action' => 'Clear drainage canals immediately.',
            'published_at' => now(),
            'valid_until' => now()->addDay(),
        ]);

        $response = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'Typhoon coming?',
        ]);

        $response->assertOk();
        $answer = (string) $response->json('chat.answer');
        $this->assertStringContainsString('Typhoon Signal No. 2', $answer);
        $this->assertStringContainsString('What to do:', $answer);
        $whatToDo = substr($answer, strpos($answer, 'What to do:'));
        $this->assertStringContainsString('Typhoon alert:', $whatToDo);
    }

    public function test_filipino_reply_and_quick_reply_chips_follow_header_flags(): void
    {
        $this->mockWeather();
        $farmer = $this->farmer(['barangay' => 'Binubusan', 'preferred_language' => 'fil']);
        Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Bukid',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 1.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
        ]);

        $widget = $this->actingAs($farmer)->get(route('ai-chat.widget'));
        $widget->assertOk();
        $widget->assertSee('Panahon ngayong linggo');
        $widget->assertSee('Puwede na ba magtanim?');
        $widget->assertSee('Kailan ang ani?');
        $widget->assertSee('May bagyo ba?');
        $widget->assertSee('Inaasahang ani ko');
        $widget->assertSee('Baha/tagtuyot sa barangay ko');

        $reply = $this->actingAs($farmer)->postJson(route('ai-chat.message'), [
            'question' => 'Panahon ngayong linggo',
            'language' => 'tl',
        ]);
        $reply->assertOk();
        $this->assertStringContainsString('Ano ang dapat gawin:', (string) $reply->json('chat.answer'));
    }

    public function test_feedback_saved_and_mao_page_filtered_by_mao_scope(): void
    {
        $this->mockWeather();
        $farmerA = $this->farmer(['name' => 'Farmer Binubusan', 'barangay' => 'Binubusan']);
        $farmerB = $this->farmer(['name' => 'Farmer Cumba', 'barangay' => 'Cumba']);

        $chatA = AIChat::query()->create([
            'user_id' => $farmerA->id,
            'question' => 'Weather this week',
            'answer' => 'Sample answer A',
            'intent' => 'weather_forecast',
        ]);
        $chatB = AIChat::query()->create([
            'user_id' => $farmerB->id,
            'question' => 'My expected yield',
            'answer' => 'Sample answer B',
            'intent' => 'rice_yield_prediction',
            'helpful' => false,
            'feedback_reason' => 'Not accurate',
            'feedback_at' => now(),
        ]);

        // Farmer A submits thumbs down with reason
        $this->actingAs($farmerA)
            ->postJson(route('ai-chat.feedback', $chatA), [
                'helpful' => false,
                'reason' => 'Too short',
            ])
            ->assertOk()
            ->assertJsonPath('helpful', false)
            ->assertJsonPath('feedback_reason', 'Too short');

        $this->assertDatabaseHas('a_i_chats', [
            'id' => $chatA->id,
            'helpful' => false,
            'feedback_reason' => 'Too short',
        ]);

        // Farmer B cannot rate Farmer A's chat
        $this->actingAs($farmerB)
            ->postJson(route('ai-chat.feedback', $chatA), ['helpful' => true])
            ->assertForbidden();

        // MAO Staff assigned to Binubusan only sees Farmer Binubusan
        $maoStaff = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Agricultural Technologist',
        ]);
        \App\Models\MaoUserBarangay::query()->create([
            'user_id' => $maoStaff->id,
            'barangay' => 'Binubusan',
        ]);
        User::forgetMaoPersonnel();

        $staffPage = $this->actingAs($maoStaff)->get(route('mao.climora-feedback.index'));
        $staffPage->assertOk();
        $staffPage->assertSee('Farmer Binubusan');
        $staffPage->assertDontSee('Farmer Cumba');

        // MAO Head sees all
        $maoHead = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        $headPage = $this->actingAs($maoHead)->get(route('mao.climora-feedback.index'));
        $headPage->assertOk();
        $headPage->assertSee('Farmer Binubusan');
        $headPage->assertSee('Farmer Cumba');
    }
}
