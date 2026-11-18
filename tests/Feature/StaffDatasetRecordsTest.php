<?php

namespace Tests\Feature;

use App\Http\Controllers\ClimateRecordController;
use App\Http\Controllers\RiceProductionController;
use App\Models\ClimateRecord;
use App\Models\HistoricalRiceProduction;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\Climate\ClimateRecordHistory;
use App\Support\ProjectYieldDataset;
use App\Support\LianFarmTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * MAO and IT record lists show the real datasets iClimate already uses (the PAGASA Ambulong monthly record and the
 * modeling dataset's municipal average yield) together with entered records. Nothing is copied into the tables,
 * dataset rows are read-only, missing values stay missing, and every value matches the shared dataset service.
 */
class StaffDatasetRecordsTest extends TestCase
{
    use RefreshDatabase;

    /** The reader of the lists. These were also IT pages until 2026-10; IT is now sent to its dashboard. */
    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'is_mao_head' => true]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function climate(): Collection
    {
        return collect(app(AmbulongHistoricalClimateDataset::class)->load()['observations']);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }

    public function test_it_climate_list_shows_the_ambulong_record_with_real_counts_and_values(): void
    {
        $climate = $this->climate();
        $total = $climate->count();
        $this->assertGreaterThan(100, $total, 'The Ambulong dataset is part of the repository.');
        $this->assertSame(0, ClimateRecord::query()->count(), 'Nothing is copied into climate_records.');

        $newest = $climate->sortByDesc(fn ($o) => $o['year'] * 100 + $o['month'])->first();
        $oldest = $climate->sortBy(fn ($o) => $o['year'] * 100 + $o['month'])->first();
        $label = fn ($o) => Carbon::create($o['year'], $o['month'], 1)->format('M Y');

        $html = $this->actingAs($this->it())->get(route('climate-records.index'))->assertOk()
            ->assertSee("Showing 1-25 of {$total}")
            ->assertDontSee('No records found')
            ->assertSee(ClimateRecordHistory::AMBULONG_DISPLAY_LABEL)
            ->assertSee(ClimateRecordHistory::AMBULONG_FILTER_LABEL)
            ->assertDontSee('Actions')
            ->assertDontSee('Dataset · read-only')
            ->getContent();

        // Newest first; values exactly as the shared loader (and so the forecast model) reads them.
        $first = strpos($html, $label($newest));
        $this->assertNotFalse($first);
        $this->assertStringContainsString($this->number($newest['rainfall']).' mm', $html);
        $this->assertStringContainsString($this->number($newest['temperature']).' °C', $html);
        $this->assertStringContainsString($this->number($newest['humidity']).'%', $html);

        $this->actingAs($this->it())->get(route('climate-records.index', ['sort' => 'oldest']))->assertOk()->assertSee($label($oldest));
        $this->actingAs($this->it())->get(route('climate-records.index', ['page' => 2]))->assertOk()->assertSee("Showing 26-50 of {$total}");
    }

    public function test_climate_search_season_and_source_filters_use_the_combined_records(): void
    {
        $climate = $this->climate();
        $it = $this->it();
        ClimateRecord::query()->create(['record_date' => '2024-03-10', 'rainfall' => 12.5, 'temperature' => 28, 'humidity' => null, 'season' => 'Dry', 'source' => 'MAO field log']);

        $year = $climate->where('year', 2020)->count();
        $this->actingAs($it)->get(route('climate-records.index', ['search' => '2020']))->assertOk()->assertSee('of '.$year);

        $wet = $climate->where('season', 'Wet')->count();
        $this->actingAs($it)->get(route('climate-records.index', ['season' => 'Wet']))->assertOk()->assertSee('of '.$wet);
        $this->actingAs($it)->get(route('climate-records.index', ['season' => 'Dry']))->assertOk()->assertSee('of '.($climate->where('season', 'Dry')->count() + 1));

        $this->actingAs($it)->get(route('climate-records.index', ['source' => 'MAO field log']))->assertOk()
            ->assertSee('Showing 1-1 of 1')
            ->assertSee('Not set')                                                    // the missing humidity stays missing
            ->assertDontSee('>0%', false);
        $this->actingAs($it)->get(route('climate-records.index', ['source' => ClimateRecordController::AMBULONG_SOURCE]))->assertOk()->assertSee('of '.$climate->count());

        // The source filter offers only sources that have records.
        $this->actingAs($it)->get(route('climate-records.index'))->assertOk()
            ->assertSee('<option value="MAO field log"', false)
            ->assertDontSee('iClimate Seed Data (synthetic)');
    }



    public function test_dataset_rows_are_read_only_and_wind_speed_is_not_in_the_table(): void
    {
        $it = $this->actingAs($this->it())->get(route('climate-records.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('/edit"', $it, 'No dataset row has an Edit link.');
        $this->assertStringNotContainsString('Wind Speed', $it);
        $this->assertStringNotContainsString('Unit not confirmed', $it);

        $mao = $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('climate-records.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Wind Speed', $mao);
        $this->assertStringNotContainsString('Unit not confirmed', $mao);
    }

    public function test_it_rice_list_shows_the_yield_dataset_without_inventing_fields(): void
    {
        $rows = app(ProjectYieldDataset::class)->rows();
        $total = $rows->count();
        $historical = HistoricalRiceProduction::query()->count();
        $this->assertGreaterThan(0, $total);
        $this->assertSame(0, RiceProduction::query()->count(), 'Nothing is copied into rice_productions.');

        $newest = $rows->sortByDesc(fn ($r) => $r['year'] * 100 + $r['month'])->first();
        $html = $this->actingAs($this->it())->get(route('rice-productions.index'))->assertOk()
            ->assertSee("Showing 1-25 of ".($total + $historical))
            ->assertDontSee('No records found')
            ->assertSee(RiceProductionController::DATASET_SCOPE)
            ->assertSee(Carbon::create($newest['year'], $newest['month'], 1)->format('M Y'))
            ->assertSee($this->number($newest['yield']).' t/ha')                        // the same value the dashboards use
            ->assertSee('Not available')                                              // no area, production or irrigation in the dataset
            ->assertDontSee(' 0 t<', false)
            ->getContent();
        $this->assertStringNotContainsString('/edit"', $html, 'Dataset rows are read-only.');

        // Season comes from the file itself.
        $this->assertSame($rows->count(), $rows->whereIn('season', ['Wet', 'Dry'])->count());
    }

    public function test_rice_filters_work_on_the_combined_records_and_offer_only_real_values(): void
    {
        $rows = app(ProjectYieldDataset::class)->rows();
        $it = $this->it();

        // Without entered records, no irrigation type is offered: the dataset does not record one.
        $this->actingAs($it)->get(route('rice-productions.index'))->assertOk()->assertDontSee('<option value="Irrigated"', false);

        RiceProduction::query()->create(['barangay' => 'Binubusan', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.1, 'area_hectares' => 2, 'total_production' => 8.2, 'year' => 2024]);

        $this->actingAs($it)->get(route('rice-productions.index', ['season' => 'Wet']))->assertOk()->assertSee('of '.($rows->where('season', 'Wet')->count() + 1));
        $this->actingAs($it)->get(route('rice-productions.index', ['irrigation_type' => 'Irrigated']))->assertOk()
            ->assertSee('Showing 1-1 of 1')
            ->assertSee('Binubusan')
            ->assertSee('8.2 t');
        $binubusanRows = HistoricalRiceProduction::query()->where('barangay', 'BINUBUSAN')->count() + 1;
        $this->actingAs($it)->get(route('rice-productions.index', ['search' => 'binubusan']))->assertOk()->assertSee('Showing 1-'.min(25, $binubusanRows).' of '.$binubusanRows);
        $this->actingAs($it)->get(route('rice-productions.index', ['search' => '2021']))->assertOk()->assertSee('of '.$rows->where('year', 2021)->count());
        $this->actingAs($it)->get(route('rice-productions.index'))->assertOk()
            ->assertSee('<option value="Irrigated"', false)
            ->assertDontSee('<option value="Rainfed"', false)
            ->assertSee('Showing 1-25 of '.($rows->count() + HistoricalRiceProduction::query()->count() + 1));
    }

    public function test_climate_records_and_rice_production_are_staff_pages_and_farmers_go_home(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);

        // Production Records are staff-only; farmers are sent back to their dashboard.
        $this->actingAs($farmer)->get(route('climate-records.index'))->assertRedirect(route('farmer.dashboard'));
        $this->actingAs($farmer)->get(route('rice-productions.index'))->assertRedirect(route('farmer.dashboard'));
    }

    public function test_mao_can_leave_irrigation_unknown_without_overwriting_it(): void
    {
        $mao = $this->it();

        $this->actingAs($mao)->post(route('rice-productions.store'), [
            'barangay' => 'Binubusan', 'season' => 'Wet', 'irrigation_type' => '',
            'yield_per_hectare' => '3.2', 'area_hectares' => '1.5', 'total_production' => '4.8',
            'total_production_unit' => 'tons', 'year' => 2025, 'production_month' => '2025-10',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rice_productions', ['barangay' => 'Binubusan', 'irrigation_type' => null]);
    }

    public function test_federation_land_area_reference_keeps_the_published_totals(): void
    {
        $summary = LianFarmTypes::federationSummary();

        $this->assertSame(636.98, $summary['irrigated_area_hectares']);
        $this->assertSame(90.59, $summary['rainfed_area_hectares']);
        $this->assertSame(727.57, $summary['total_area_hectares']);
        $this->assertCount(16, $summary['associations']);
    }
}
