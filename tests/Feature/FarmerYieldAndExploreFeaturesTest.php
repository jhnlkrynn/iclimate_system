<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\AI\AIOrchestratorService;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Farmer\FarmResultsExplorationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FarmerYieldAndExploreFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $this->travelTo($this->now = CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));
    }

    private function farmer(string $barangay = 'Humayingan', string $emailPrefix = 'farmer'): User
    {
        return User::factory()->farmer()->create([
            'barangay' => $barangay,
            'status' => User::STATUS_ACTIVE,
            'email' => $emailPrefix.'+'.uniqid().'@real.ph',
        ]);
    }

    private function approvedFarm(User $farmer, array $overrides = []): Farm
    {
        return Farm::create(array_merge([
            'user_id' => $farmer->id,
            'farm_name' => 'Bukid '.uniqid(),
            'barangay' => $farmer->barangay,
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.5,
            'farm_size_method' => Farm::SIZE_EXACT,
            'verification_status' => Farm::VERIFICATION_APPROVED,
        ], $overrides));
    }

    public function test_expected_yield_prefills_from_last_harvest_then_variety_and_saves_with_insight(): void
    {
        $farmer = $this->farmer('Humayingan');
        $farm = $this->approvedFarm($farmer, ['farm_size_hectares' => 3.0]);

        $variety = RiceVariety::create([
            'import_key' => 'nsic_rc_222',
            'variety_name' => 'NSIC Rc 222',
            'yield_tpr_ws' => 5400,
            'yield_typical' => 5400,
            'maturity_typical' => 114,
            'source' => RiceVariety::SOURCE_IMPORT,
        ]);

        // 1) Prefill from Rice Variety when no last harvest or saved expected yield exists.
        FarmerPlantingSchedule::create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'rice_variety_id' => $variety->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'farm_area_hectares' => 3.0,
            'planting_date' => $this->now->subDays(40)->toDateString(),
            'status' => 'Planted',
        ]);

        $payload = app(FarmerDashboardService::class)->payload($farmer);
        $this->assertEquals(5.4, $payload['expected_yield']['expected']);
        $this->assertSame('variety', $payload['expected_yield']['source']);
        $this->assertStringContainsString('Rice Varieties', $payload['expected_yield']['source_label']);
        $this->assertNull($payload['expected_yield']['estimate']);
        $this->assertNotEmpty($payload['expected_yield']['insights']);

        // 2) Prefill from Last Harvest when a recorded harvest exists.
        FarmerPlantingSchedule::create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'rice_variety_id' => $variety->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'farm_area_hectares' => 3.0,
            'planting_date' => $this->now->subMonths(7)->toDateString(),
            'harvested_on' => $this->now->subMonths(3)->toDateString(),
            'actual_yield' => 5.5,
            'status' => 'Harvested',
        ]);

        $payloadWithHarvest = app(FarmerDashboardService::class)->payload($farmer);
        $this->assertEquals(5.5, $payloadWithHarvest['expected_yield']['expected']);
        $this->assertSame('last_harvest', $payloadWithHarvest['expected_yield']['source']);

        // 3) Save farmer's own expected yield and compare against iClimate YieldPrediction.
        YieldPrediction::create([
            'scope' => YieldPrediction::SCOPE_FARM,
            'subject_key' => 'farm:'.$farm->id,
            'farm_id' => $farm->id,
            'user_id' => $farmer->id,
            'barangay' => 'Humayingan',
            'predicted_yield' => 4.8,
            'classification' => 'Average',
            'basis' => 'model',
            'period' => '2026-10',
            'farms_used' => 10,
            'area_hectares' => 3.0,
            'season_start' => \App\Support\RiceSeason::window($this->now)['start']->toDateString(),
            'season' => 'Wet',
            'season_label' => 'Wet 2026',
            'target_month' => 11,
            'model_version' => 'v1',
            'generated_at' => $this->now,
        ]);
        Cache::flush();
        Cache::put(FarmerDashboardService::predictionCacheKey($farmer->id, $farm->id), [
            'predicted_yield' => 4.8,
            'target_month' => '2026-10',
            'ran_at' => $this->now->toIso8601String(),
            'model_input' => [
                'area' => 50.0,
                'rainfall_6m' => 150.0,
                'temp_range' => 7.0,
                'rainfall' => 150.0,
                'season' => 'Wet',
                'barangay' => 'Humayingan',
                'farm_type' => 'Irrigated',
            ],
        ], now()->addHour());

        $this->actingAs($farmer)
            ->patch(route('farmer.farms.expected-yield', $farm), [
                'expected_yield_t_ha' => 6.0,
            ])
            ->assertRedirect();

        config()->set('prediction.rice_yield.gate', \App\Services\Prediction\YieldModelGate::OFF);

        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('Expected yield this season');
        $dashboard->assertSee('Your expected yield (t/ha)');
        $dashboard->assertSee('6.00');
        $dashboard->assertSee('4.80');
        $dashboard->assertSee('-1.20 t/ha');
        $dashboard->assertSee('-20%');
        $dashboard->assertSee('below what you expect');
    }

    public function test_last_harvest_card_shows_tons_and_yield_with_area_and_tons_only_without_area(): void
    {
        $farmer = $this->farmer('Prenza');
        $farm = $this->approvedFarm($farmer, ['land_area' => 3.0]);

        FarmerPlantingSchedule::create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'rice_variety' => 'NSIC Rc 216',
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'farm_area_hectares' => 3.0,
            'planting_date' => $this->now->subMonths(6)->toDateString(),
            'harvested_on' => $this->now->subMonths(2)->toDateString(),
            'actual_yield' => 5.5,
            'status' => 'Harvested',
        ]);

        $response = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $response->assertOk();
        $response->assertSee('Tons harvested:');
        $response->assertSee('16.5 t');
        $response->assertSeeInOrder(['Yield:', '5.5 t/ha', '(from 3 ha)']);

        // Without farm area, show tons only and omit "(from ... ha)".
        $farmerNoArea = $this->farmer('Prenza');
        $farmNoArea = Farm::create([
            'user_id' => $farmerNoArea->id,
            'farm_name' => 'No Area Farm',
            'barangay' => 'Prenza',
            'farm_type' => 'Rainfed',
            'land_area' => null,
            'verification_status' => Farm::VERIFICATION_APPROVED,
        ]);

        FarmerPlantingSchedule::create([
            'user_id' => $farmerNoArea->id,
            'farm_id' => $farmNoArea->id,
            'rice_variety' => 'NSIC Rc 216',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'farm_area_hectares' => null,
            'planting_date' => $this->now->subMonths(6)->toDateString(),
            'harvested_on' => $this->now->subMonths(2)->toDateString(),
            'actual_yield' => 12.0,
            'status' => 'Harvested',
        ]);

        $noAreaPayload = app(FarmerDashboardService::class)->payload($farmerNoArea);
        $this->assertEquals(12.0, $noAreaPayload['expected_yield']['last_harvest']['tons']);
        $this->assertFalse($noAreaPayload['expected_yield']['last_harvest']['has_area']);

        $noAreaResponse = $this->actingAs($farmerNoArea)->get(route('farmer.dashboard'));
        $noAreaResponse->assertOk();
        $noAreaResponse->assertSee('12 t');
        $noAreaResponse->assertDontSee('(from');
    }

    public function test_explore_farm_results_shows_1_farm_excludes_demo_and_unapproved_and_handles_no_harvest_and_empty_state(): void
    {
        $viewer = $this->farmer('Humayingan');
        $variety = RiceVariety::create([
            'import_key' => 'nsic_rc_222_explore',
            'variety_name' => 'NSIC Rc 222',
            'yield_tpr_ws' => 5400,
            'maturity_typical' => 114,
            'source' => RiceVariety::SOURCE_IMPORT,
        ]);

        // Add 1 pending (unapproved) farm and 1 demo (@iclimate.local) farm — both must be excluded.
        $pendingOwner = $this->farmer('Humayingan', 'pending');
        $pendingFarm = $this->approvedFarm($pendingOwner, [
            'farm_type' => 'Rainfed',
            'verification_status' => Farm::VERIFICATION_PENDING,
        ]);
        FarmerPlantingSchedule::create([
            'user_id' => $pendingOwner->id,
            'farm_id' => $pendingFarm->id,
            'rice_variety_id' => $variety->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'planting_date' => '2026-06-15',
            'harvested_on' => '2026-09-25',
            'actual_yield' => 9.9,
            'status' => 'Harvested',
        ]);

        $demoOwner = User::factory()->farmer()->create([
            'barangay' => 'Humayingan',
            'status' => User::STATUS_ACTIVE,
            'email' => 'demo_rainfed@iclimate.local',
        ]);
        $demoFarm = $this->approvedFarm($demoOwner, ['farm_type' => 'Rainfed']);
        FarmerPlantingSchedule::create([
            'user_id' => $demoOwner->id,
            'farm_id' => $demoFarm->id,
            'rice_variety_id' => $variety->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'planting_date' => '2026-06-15',
            'harvested_on' => '2026-09-25',
            'actual_yield' => 8.8,
            'status' => 'Harvested',
        ]);

        // With 0 valid farms, page shows ONE empty message suggesting widening filters.
        $emptyPage = $this->actingAs($viewer)->get(route('farmer.explore-results.index', [
            'farm_type' => 'Rainfed',
            'barangay' => 'Humayingan',
        ]));
        $emptyPage->assertOk();
        $emptyPage->assertSee('No approved farms match these filters yet. Try All of Lian or another year.');
        $emptyPage->assertDontSee('Not enough farms yet');
        $emptyPage->assertDontSee('data-explore-insight');

        // 1 approved real farm with planting only (no harvest yet) -> shows count, area, variety, month + no-harvest note.
        $plantedOwner = $this->farmer('Humayingan', 'planted1');
        $plantedFarm = $this->approvedFarm($plantedOwner, [
            'farm_name' => 'Secret Private Farm 1',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 2.5,
        ]);
        FarmerPlantingSchedule::create([
            'user_id' => $plantedOwner->id,
            'farm_id' => $plantedFarm->id,
            'rice_variety_id' => $variety->id,
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'farm_area_hectares' => 2.5,
            'planting_date' => '2026-01-15',
            'harvested_on' => null,
            'actual_yield' => null,
        ]);

        $noHarvestPage = $this->actingAs($viewer)->get(route('farmer.explore-results.index', [
            'farm_type' => 'Rainfed',
            'barangay' => 'Humayingan',
            'season' => 'Dry',
        ]));
        $noHarvestPage->assertOk();
        $noHarvestPage->assertSee('Based on 1 farm');
        $noHarvestPage->assertSee('Few farms, so results may vary.');
        $noHarvestPage->assertSee('No harvest recorded yet for this season.');
        $noHarvestPage->assertSee('2.50 ha');
        $noHarvestPage->assertSee('NSIC Rc 222');
        $noHarvestPage->assertSee('January');
        $noHarvestPage->assertDontSee('Secret Private Farm 1');

        // 1 approved real farm with harvest -> shows full results with "Based on 1 farm" & "Few farms, so results may vary."
        $harvestOwner = $this->farmer('Humayingan', 'real1');
        $harvestFarm = $this->approvedFarm($harvestOwner, [
            'farm_name' => 'Secret Private Farm 2',
            'farm_type' => 'Rainfed',
        ]);
        FarmerPlantingSchedule::create([
            'user_id' => $harvestOwner->id,
            'farm_id' => $harvestFarm->id,
            'rice_variety_id' => $variety->id,
            'rice_variety' => 'NSIC Rc 222',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'planting_date' => '2026-06-15',
            'harvested_on' => '2026-09-25',
            'actual_yield' => 3.8,
            'status' => 'Harvested',
        ]);

        $oneFarmPage = $this->actingAs($viewer)->get(route('farmer.explore-results.index', [
            'farm_type' => 'Rainfed',
            'barangay' => 'Humayingan',
            'season' => 'Wet',
        ]));
        $oneFarmPage->assertOk();
        $oneFarmPage->assertSee('Rainfed farms in Humayingan averaged 3.8 t/ha');
        $oneFarmPage->assertSee('Based on 1 farm');
        $oneFarmPage->assertSee('Few farms, so results may vary.');
        $oneFarmPage->assertSee('NSIC Rc 222');
        $oneFarmPage->assertDontSee('Secret Private Farm 2');

        // Irrigated filter still has 0 farms -> empty state.
        $irrigatedResult = app(FarmResultsExplorationService::class)->explore([
            'farm_type' => 'Irrigated',
            'barangay' => 'Humayingan',
        ], 'en');
        $this->assertFalse($irrigatedResult['sufficient']);
    }

    public function test_climora_answers_how_irrigated_farms_do_in_my_barangay_using_exploration_service(): void
    {
        $farmer = $this->farmer('Humayingan');

        // Before any farms: Climora reports no matching approved farms yet.
        $replyInsufficient = app(AIOrchestratorService::class)->answer($farmer, 'How do irrigated farms do in my barangay?');
        $this->assertSame('farm_results_explore', $replyInsufficient['intent']);
        $this->assertStringContainsString('No approved farms match these filters yet', $replyInsufficient['answer']);

        $variety = RiceVariety::create([
            'import_key' => 'nsic_rc_222_climora',
            'variety_name' => 'NSIC Rc 222',
            'yield_tpr_ds' => 5400,
            'maturity_typical' => 114,
            'source' => RiceVariety::SOURCE_IMPORT,
        ]);

        // Add 5 approved Irrigated farms in Humayingan.
        foreach ([4.6, 4.8, 5.0, 5.2, 5.4] as $i => $yield) {
            $owner = $this->farmer('Humayingan', 'irr'.$i);
            $farm = $this->approvedFarm($owner, ['farm_type' => 'Irrigated']);
            FarmerPlantingSchedule::create([
                'user_id' => $owner->id,
                'farm_id' => $farm->id,
                'rice_variety_id' => $variety->id,
                'rice_variety' => 'NSIC Rc 222',
                'season' => 'Dry',
                'farm_type' => 'Irrigated',
                'planting_date' => '2026-01-10',
                'harvested_on' => '2026-04-28',
                'actual_yield' => $yield,
                'status' => 'Harvested',
            ]);
        }

        $replySufficient = app(AIOrchestratorService::class)->answer($farmer, 'How do irrigated farms do in my barangay?');
        $this->assertSame('farm_results_explore', $replySufficient['intent']);
        $this->assertStringContainsString('Irrigated farms in Humayingan averaged 5 t/ha', $replySufficient['answer']);
        $this->assertStringContainsString('Based on 5 farms', $replySufficient['answer']);
        $this->assertStringContainsString('4.6–5.4 t/ha', $replySufficient['answer']);
        $this->assertStringContainsString('NSIC Rc 222', $replySufficient['answer']);
    }

    public function test_rainy_days_card_and_modal_support_7_and_14_day_toggle_and_unavailable_state(): void
    {
        $farmer = $this->farmer('Humayingan');
        $this->approvedFarm($farmer);

        $makeDay = fn (int $offset, float $mm): array => [
            'date' => $this->now->addDays($offset)->toDateString(),
            'condition' => $mm > 0 ? 'Light Rain' : 'Mainly Clear',
            'rainfall' => $mm,
            'precipitation_probability' => $mm > 0 ? 70 : 10,
            'wind_speed_max' => 12,
        ];

        $sevenDays = array_map(fn (int $i) => $makeDay($i, $i % 2 === 0 ? 2.5 : 0.0), range(0, 6));
        $fourteenDays = array_map(fn (int $i) => $makeDay($i, $i % 3 === 0 ? 3.0 : 0.0), range(0, 13));

        // 1) Only 7 days stored -> 14 days is disabled with "Not available yet".
        $this->mock(\App\Services\Weather\FarmerDashboardWeatherService::class, function ($mock) use ($sevenDays): void {
            $mock->shouldReceive('current')->andReturn([
                'success' => true,
                'source' => 'Open-Meteo',
                'timestamps' => ['server_fetched_at' => $this->now->toIso8601String()],
                'current' => ['temperature' => 30.0, 'condition' => 'Mainly Clear', 'wind_speed' => 10, 'is_day' => true],
                'today' => ['rainfall' => 2.5, 'precipitation_probability' => 70],
                'forecast' => $sevenDays,
                'forecast_extended' => $sevenDays,
            ]);
            $mock->shouldReceive('lastFetchedAt')->andReturn($this->now);
        });

        $onlySevenHtml = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Rainy days, next 7 days', $onlySevenHtml);
        $this->assertStringContainsString('Not available yet', $onlySevenHtml);
        $this->assertStringContainsString('Forecast only — weather can still change.', $onlySevenHtml);

        // 2) 14 days stored -> 14 days toggle enabled, grouped This week / Next week, Less certain on days 8-14.
        Cache::flush();
        $this->mock(\App\Services\Weather\FarmerDashboardWeatherService::class, function ($mock) use ($sevenDays, $fourteenDays): void {
            $mock->shouldReceive('current')->andReturn([
                'success' => true,
                'source' => 'Open-Meteo',
                'timestamps' => ['server_fetched_at' => $this->now->toIso8601String()],
                'current' => ['temperature' => 30.0, 'condition' => 'Mainly Clear', 'wind_speed' => 10, 'is_day' => true],
                'today' => ['rainfall' => 2.5, 'precipitation_probability' => 70],
                'forecast' => $sevenDays,
                'forecast_extended' => $fourteenDays,
            ]);
            $mock->shouldReceive('lastFetchedAt')->andReturn($this->now);
        });

        $fourteenHtml = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-rain-panel="week"', $fourteenHtml);
        $this->assertStringContainsString('data-rain-panel="2weeks"', $fourteenHtml);
        $this->assertStringContainsString('This week', $fourteenHtml);
        $this->assertStringContainsString('Next week', $fourteenHtml);
        $this->assertStringContainsString('Less certain', $fourteenHtml);
        $this->assertStringContainsString('range=week', $fourteenHtml);
        $this->assertStringContainsString('range=2weeks', $fourteenHtml);
    }

    public function test_explore_results_page_loads_200_with_real_rice_varieties_schema_empty_and_populated(): void
    {
        $viewer = $this->farmer('Humayingan');
        $variety = RiceVariety::create([
            'import_key' => 'nsic_2016_rc_480',
            'nsic_reg_no' => 'NSIC 2016 Rc 480',
            'variety_name' => 'GSR 8',
            'yield_tpr_ws' => 5200,
            'maturity_typical' => 115,
            'source' => RiceVariety::SOURCE_IMPORT,
        ]);

        // Empty state (no farms/harvests yet) must return 200 and show variety option + single empty message.
        $empty = $this->actingAs($viewer)->get(route('farmer.explore-results.index'));
        $empty->assertOk();
        $empty->assertSee('NSIC Rc 480 (GSR 8)');
        $empty->assertSee('No approved farms match these filters yet. Try All of Lian or another year.');
        $empty->assertDontSee('Not enough farms yet');

        // Seed 5 approved farms with harvests for that variety.
        foreach ([4.2, 4.4, 4.6, 4.8, 5.0] as $i => $yield) {
            $owner = $this->farmer('Humayingan', 'schema'.$i);
            $farm = $this->approvedFarm($owner, ['farm_type' => 'Irrigated']);
            FarmerPlantingSchedule::create([
                'user_id' => $owner->id,
                'farm_id' => $farm->id,
                'rice_variety_id' => $variety->id,
                'season' => 'Wet',
                'farm_type' => 'Irrigated',
                'planting_date' => '2026-06-10',
                'harvested_on' => '2026-09-25',
                'actual_yield' => $yield,
            ]);
        }

        $populated = $this->actingAs($viewer)->get(route('farmer.explore-results.index', [
            'farm_type' => 'Irrigated',
            'barangay' => 'Humayingan',
        ]));
        $populated->assertOk();
        $populated->assertSee('Irrigated farms in Humayingan averaged 4.6 t/ha');
        $populated->assertSee('NSIC Rc 480 (GSR 8)');
    }
}
