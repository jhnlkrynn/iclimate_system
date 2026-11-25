<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\YieldModelGate;
use App\Support\BusinessClock;
use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeasonYieldComparisonTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private string $seasonType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-25 10:00:00', 'Asia/Manila');
        $this->travelTo($this->now);
        $this->seasonType = RiceSeason::window($this->now)['season']; // Dry

        config([
            'iclimate.python_binary' => '/nonexistent/python',
            'services.groq.enabled' => false,
            'prediction.rice_yield.gate' => YieldModelGate::OFF,
        ]);
        Http::fake(['*' => Http::response([], 503)]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function farmerWithFarm(string $barangay = 'Matabungkay', string $farmType = 'Irrigated'): array
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => $barangay,
        ]);
        FarmerProfile::factory()->create([
            'user_id' => $farmer->id,
            'barangay' => $barangay,
            'farm_area' => 2.0,
            'farm_type' => $farmType,
        ]);
        $farm = Farm::query()->where('user_id', $farmer->id)->first();
        if ($farm === null) {
            $farm = new Farm([
                'user_id' => $farmer->id,
                'farm_name' => 'Test Farm',
                'is_primary' => true,
            ]);
        }
        $farm->forceFill([
            'barangay' => $barangay,
            'farm_type' => $farmType,
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'verification_status' => Farm::VERIFICATION_APPROVED,
        ])->save();

        return [$farmer, $farm];
    }

    private function seedEstimate(User $farmer, Farm $farm, float $yield): void
    {
        YieldPrediction::create([
            'scope' => YieldPrediction::SCOPE_FARM,
            'subject_key' => 'farm:'.$farm->id,
            'farm_id' => $farm->id,
            'user_id' => $farmer->id,
            'barangay' => $farm->barangay,
            'predicted_yield' => $yield,
            'classification' => 'Average',
            'basis' => 'model',
            'period' => '2026-11',
            'farms_used' => 10,
            'area_hectares' => 2.0,
            'season_start' => RiceSeason::window($this->now)['start']->toDateString(),
            'season' => $this->seasonType,
            'season_label' => 'Dry season 2026–2027',
            'target_month' => 2,
            'model_version' => 'v1',
            'generated_at' => $this->now,
        ]);
        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id, $farm->id), [
            'predicted_yield' => $yield,
            'target_month' => RiceSeason::harvestMonth($this->now)->format('Y-m'),
            'ran_at' => $this->now->toIso8601String(),
            'model_input' => [
                'area' => 50.0,
                'rainfall_6m' => 150.0,
                'temp_range' => 7.0,
                'rainfall' => 150.0,
                'season' => $this->seasonType,
                'barangay' => $farm->barangay,
                'farm_type' => $farm->farm_type,
            ],
        ], now()->addHour());
    }

    public function test_dashboard_and_my_farm_compare_estimate_with_same_season_farm_history(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm();
        $this->seedEstimate($farmer, $farm, 4.07);

        // 3 past Dry season harvests (avg = 3.80 t/ha)
        foreach ([
            ['2024-03-15', 3.60],
            ['2025-03-15', 3.80],
            ['2026-03-15', 4.00],
        ] as [$harvestedOn, $actualYield]) {
            FarmerPlantingSchedule::create([
                'user_id' => $farmer->id,
                'farm_id' => $farm->id,
                'rice_variety' => 'NSIC Rc 222',
                'season' => 'Dry',
                'farm_type' => 'Irrigated',
                'farm_area_hectares' => 2.0,
                'planting_date' => CarbonImmutable::parse($harvestedOn)->subDays(110)->toDateString(),
                'harvested_on' => $harvestedOn,
                'actual_yield' => $actualYield,
                'status' => 'Harvested',
            ]);
        }

        // Wet season harvest should be excluded from Dry vs Dry comparison.
        FarmerPlantingSchedule::create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'farm_area_hectares' => 2.0,
            'planting_date' => '2025-06-15',
            'harvested_on' => '2025-10-10',
            'actual_yield' => 9.00,
            'status' => 'Harvested',
        ]);

        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Estimate 4.07 t/ha vs your usual 3.80 t/ha (avg of last 3 dry seasons)');
        $dashboard->assertSee('+7%');
        $dashboard->assertSee('Higher than usual');
        $dashboard->assertSee('Higher than your usual dry season harvest.');

        $myFarm = $this->actingAs($farmer)->get(route('farmer.my-farm'));
        $myFarm->assertOk();
        $myFarm->assertSee('Season Yield Comparison');
        $myFarm->assertSee('Estimate (iClimate)');
        $myFarm->assertSee('Actual harvest');
        $myFarm->assertSee('4.07 t/ha');
        $myFarm->assertSee('3.80 t/ha');
        $myFarm->assertSee('4.00 t/ha');
        $myFarm->assertSee('3.60 t/ha');
    }

    public function test_falls_back_to_approved_barangay_same_season_average_when_no_farm_history(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm('Balibago', 'Irrigated');
        $this->seedEstimate($farmer, $farm, 4.00);

        // Another approved Irrigated farm in Balibago with a Dry harvest.
        [$otherFarmer, $otherFarm] = $this->farmerWithFarm('Balibago', 'Irrigated');
        FarmerPlantingSchedule::create([
            'user_id' => $otherFarmer->id,
            'farm_id' => $otherFarm->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'farm_area_hectares' => 1.5,
            'planting_date' => '2025-11-20',
            'harvested_on' => '2026-03-10',
            'actual_yield' => 3.90,
            'status' => 'Harvested',
        ]);

        // Unapproved farm should be excluded.
        $pendingFarmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);
        $pendingFarm = Farm::query()->create([
            'user_id' => $pendingFarmer->id,
            'farm_name' => 'Pending Farm',
            'barangay' => 'Balibago',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 1.0,
            'verification_status' => Farm::VERIFICATION_PENDING,
        ]);
        FarmerPlantingSchedule::create([
            'user_id' => $pendingFarmer->id,
            'farm_id' => $pendingFarm->id,
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'planting_date' => '2025-11-20',
            'harvested_on' => '2026-03-10',
            'actual_yield' => 8.00,
            'status' => 'Harvested',
        ]);

        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Estimate 4.00 t/ha vs Barangay average (1 farm) 3.90 t/ha');
        $dashboard->assertSee('+3%');
        $dashboard->assertSee('About the same as usual');
    }

    public function test_shows_empty_comparison_message_when_no_farm_or_barangay_history(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm('Prenza', 'Rainfed');
        $this->seedEstimate($farmer, $farm, 3.75);

        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('No past harvests yet. Record your harvest to compare next season.');

        $myFarm = $this->actingAs($farmer)->get(route('farmer.my-farm'));
        $myFarm->assertOk();
        $myFarm->assertSee('No past harvests yet. Record your harvest to compare next season.');
    }
}
