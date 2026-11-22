<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Models\User;
use App\Services\Farmer\YieldSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestTrainingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_harvest_saves_required_fields_and_mao_page_shows_progress_and_anonymized_csv(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->farmer()->create(['name' => 'Secret Farmer Name', 'status' => User::STATUS_ACTIVE]);
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Prenza',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.5,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);
        $variety = RiceVariety::query()->forceCreate([
            'import_key' => 'nsic-rc-222',
            'variety_name' => 'NSIC Rc 222',
        ]);
        $schedule = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'planting_date' => '2026-06-01',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => 'Prenza',
            'farm_area_hectares' => 2.5,
            'rice_variety_id' => $variety->id,
        ]);

        app(YieldSurveyService::class)->record($schedule, 4.75);
        $fresh = $schedule->fresh();
        $this->assertEquals(4.75, (float) $fresh->actual_yield);
        $this->assertNotNull($fresh->harvested_on);
        $this->assertSame($farm->id, $fresh->farm_id);
        $this->assertSame('Prenza', $fresh->barangay);
        $this->assertSame('Irrigated', $fresh->farm_type);
        $this->assertSame('Wet', $fresh->season);
        $this->assertSame($variety->id, $fresh->rice_variety_id);

        $this->actingAs($farmer)->get(route('analysis.harvest-training'))->assertForbidden();

        $this->actingAs($mao)->get(route('analysis.harvest-training'))
            ->assertOk()
            ->assertSee('Harvest records for model training')
            ->assertSee('Prenza irrigated: 1 of 15 harvests')
            ->assertSee('4.75 t/ha');

        $csv = $this->actingAs($mao)->get(route('analysis.harvest-training.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('farm_hash,barangay,farm_type,season,harvested_on,actual_yield_t_ha,rice_variety_id,rice_variety_name', $csv);
        $this->assertStringContainsString('Prenza,Irrigated,Wet', $csv);
        $this->assertStringContainsString('4.75', $csv);
        $this->assertStringContainsString('NSIC Rc 222', $csv);
        $this->assertStringNotContainsString('Secret Farmer Name', $csv);
        $this->assertStringNotContainsString((string) $farm->id.',Prenza', $csv);
    }
}
