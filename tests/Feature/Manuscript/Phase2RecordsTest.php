<?php

namespace Tests\Feature\Manuscript;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 2: CSV import and validation of climate and production records. */
class Phase2RecordsTest extends TestCase
{
    use RefreshDatabase;

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('records.csv', $content);
    }

    public function test_climate_csv_imports_valid_rows_and_lists_skipped_ones(): void
    {
        ClimateRecord::query()->create(['record_date' => '2024-05-01', 'rainfall' => 100, 'season' => 'Wet', 'source' => 'PAGASA']);
        $file = $this->csv("\xEF\xBB\xBFDate,Rainfall (mm),Temperature,Humidity,Wind,Season,Source\n"
            ."2024-06-01,245.6,28.4,84,2.1,,PAGASA\n"           // season taken from the month
            ."2024-05-01,99,28,80,2,Wet,PAGASA\n"              // already recorded
            ."2024-07-01,9999,28,80,2,Wet,PAGASA\n"            // rainfall above the guardrail
            ."\n"
            ."2024-12-01,40,27,78,3,Dry,PAGASA\n");

        $this->actingAs($this->mao())->post(route('records.import.store', 'climate-records'), ['file' => $file])
            ->assertRedirect(route('records.import', 'climate-records'));

        $result = session('import_result');
        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['skipped_total']);
        $this->assertSame('Already in the records.', $result['skipped'][0]['reason']);
        $this->assertSame(4, $result['skipped'][1]['line']);
        $this->assertSame('Wet', ClimateRecord::query()->whereDate('record_date', '2024-06-01')->value('season'));
        $this->assertNull(ClimateRecord::query()->whereDate('record_date', '2024-06-01')->value('verified_at'));
        $this->assertDatabaseHas('system_logs', ['action' => 'Imported Climate Records CSV']);
    }

    public function test_production_csv_imports_with_area_harvested_and_official_barangay_names(): void
    {
        $file = $this->csv("year,season,barangay,irrigation,month,area_planted,area_harvested,production_mt,yield,remarks\n"
            ."2025,wet,balibago,irrigated,2025-10,120.5,118,560.4,4.75,MAO report\n"
            ."2025,Wet,Nowhere,Irrigated,2025-10,10,10,40,4,\n");

        $this->actingAs($this->mao())->post(route('records.import.store', 'rice-productions'), ['file' => $file]);

        $this->assertSame(1, session('import_result')['imported']);
        $record = RiceProduction::query()->sole();
        $this->assertSame('Balibago', $record->barangay);
        $this->assertSame('Irrigated', $record->irrigation_type);
        $this->assertEquals(118, (float) $record->area_harvested_hectares);
        $this->assertSame('2025-10-01', $record->production_month->toDateString());
    }

    public function test_a_file_without_the_required_columns_is_refused(): void
    {
        $this->actingAs($this->mao())->post(route('records.import.store', 'rice-productions'), ['file' => $this->csv("year,season\n2025,Wet\n")])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, RiceProduction::query()->count());
    }

    public function test_the_template_downloads_with_its_columns(): void
    {
        $response = $this->actingAs($this->mao())->get(route('records.import.template', 'climate-records'));
        $response->assertOk();
        $this->assertStringStartsWith('record_date,rainfall,temperature,humidity,wind_speed,season,source', $response->streamedContent());
    }

    public function test_mao_validates_and_unvalidates_a_record_and_the_list_shows_it(): void
    {
        $mao = $this->mao();
        $record = RiceProduction::query()->create(['barangay' => 'Balibago', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'year' => 2025, 'production_month' => '2025-10', 'yield_per_hectare' => 4.5]);

        $this->actingAs($mao)->get(route('rice-productions.index', ['verification' => 'Not validated']))->assertOk()->assertSee('Validate</button>', false)->assertSee('Import CSV');
        $this->actingAs($mao)->post(route('records.validate', ['type' => 'rice-productions', 'id' => $record->id]))->assertRedirect();
        $this->assertSame($mao->id, $record->fresh()->verified_by);
        $this->actingAs($mao)->get(route('rice-productions.index', ['verification' => 'Validated']))->assertSee('Undo</button>', false);

        $this->actingAs($mao)->post(route('records.unvalidate', ['type' => 'rice-productions', 'id' => $record->id]));
        $this->assertNull($record->fresh()->verified_at);
    }

    public function test_validate_all_marks_every_unvalidated_record(): void
    {
        ClimateRecord::query()->create(['record_date' => '2024-05-01', 'rainfall' => 100, 'season' => 'Wet', 'source' => 'Manual']);
        ClimateRecord::query()->create(['record_date' => '2024-06-01', 'rainfall' => 120, 'season' => 'Wet', 'source' => 'Manual']);

        $this->actingAs($this->mao())->post(route('records.validate-all', 'climate-records'))->assertRedirect();

        $this->assertSame(0, ClimateRecord::query()->whereNull('verified_at')->count());
    }

    public function test_climate_list_filters_by_year_and_validation(): void
    {
        ClimateRecord::query()->create(['record_date' => '2023-05-01', 'rainfall' => 111.1, 'season' => 'Wet', 'source' => 'Manual']);

        $html = $this->actingAs($this->mao())->get(route('climate-records.index', ['year' => '2023', 'verification' => 'Not validated']))->assertOk()->getContent();
        $this->assertStringContainsString('111.1 mm', $html);
        $this->assertStringContainsString('name="year"', $html);
        $this->assertStringContainsString('name="month"', $html);
    }

    public function test_only_mao_imports_or_validates(): void
    {
        $record = ClimateRecord::query()->create(['record_date' => '2024-05-01', 'rainfall' => 100, 'season' => 'Wet', 'source' => 'Manual']);

        foreach ([User::ROLE_FARMER, User::ROLE_IT_EXPERT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('records.validate', ['type' => 'climate-records', 'id' => $record->id]))->assertStatus(403);
            $this->actingAs($user)->post(route('records.import.store', 'climate-records'), ['file' => $this->csv("record_date,source\n")])->assertStatus(403);
        }
        $this->assertNull($record->fresh()->verified_at);
    }
}
