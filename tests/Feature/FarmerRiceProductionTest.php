<?php

namespace Tests\Feature;

use App\Http\Controllers\RiceProductionController;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Support\ProjectYieldDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The farmer Rice Production page shows every real rice record: the Lian Municipal Average months of the yield
 * dataset (ProjectYieldDataset, the series the rice-yield model and dashboards use) and the records the Municipal
 * Agriculture Office entered. Municipal rows are never given a barangay, and what the dataset does not hold
 * (production, area, irrigation) is "Not available", never 0.
 */
class FarmerRiceProductionTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $barangay = 'San Diego'): User
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => $barangay]);

        return $farmer;
    }

    /** @return Collection<int, array{year: int, month: int, yield: float, season: ?string}> newest first */
    private function dataset(): Collection
    {
        return app(ProjectYieldDataset::class)->rows()->sortByDesc(fn ($r) => $r['year'] * 100 + $r['month'])->values();
    }

    private function page(User $farmer, array $query = []): string
    {
        return (string) $this->actingAs($farmer)->get(route('rice-productions.index', $query))->assertOk()->getContent();
    }

    private function monthLabel(array $row): string
    {
        return Carbon::create($row['year'], $row['month'], 1)->format('M Y');
    }

    public function test_dataset_only_shows_every_real_month_with_honest_figures(): void
    {
        $rows = $this->dataset();
        $total = $rows->count();
        $this->assertGreaterThan(0, $total);
        $this->assertSame(0, RiceProduction::query()->count(), 'Nothing is copied into rice_productions.');

        $html = $this->page($this->farmer());

        $this->assertStringContainsString('<p class="frec-kpi-value">'.number_format($total).'</p>', $html);
        $this->assertStringContainsString(number_format($rows->avg('yield'), 2).'<small>t/ha</small>', $html);
        $this->assertStringContainsString('No production total is available in these records.', $html);
        $this->assertStringContainsString('No area record is available.', $html);
        $this->assertStringNotContainsString('<small>t</small>', $html, 'No production total is made up.');
        $this->assertStringNotContainsString('<small>ha</small>', $html, 'No area total is made up.');
        $this->assertStringContainsString("Showing 1–10 of {$total}", $html);
        $this->assertStringNotContainsString('No rice production records found.', $html);

        // Newest first, labelled as Lian's average, read-only, missing fields "Not available".
        $newest = $rows->first();
        $this->assertStringContainsString($this->monthLabel($newest), $html);
        $this->assertStringContainsString(RiceProductionController::DATASET_SCOPE, $html);
        $this->assertStringContainsString('iClimate record · Read-only', $html);
        $this->assertStringContainsString('Not available', $html);
        $this->assertStringNotContainsString(route('rice-productions.edit', 1), $html);
        $this->assertStringContainsString('Rice yield records for Lian, including available municipal records', $html);
    }

    public function test_all_dataset_months_are_reachable_through_the_pages_with_exact_values(): void
    {
        $rows = $this->dataset();
        $farmer = $this->farmer();
        $seen = collect();

        for ($page = 1; $page <= (int) ceil($rows->count() / 10); $page++) {
            $html = $this->page($farmer, ['page' => $page]);
            foreach ($rows->forPage($page, 10) as $row) {
                // Each month on its own page, in newest-first order, with its season and exact yield.
                $this->assertStringContainsString($this->monthLabel($row), $html);
                $this->assertStringContainsString(number_format($row['yield'], 2), $html);
                $seen->push($this->monthLabel($row));
            }
        }

        $this->assertSame($rows->count(), $seen->unique()->count(), 'Every dataset month is shown once.');
        $this->assertSame($rows->map(fn ($r) => $this->monthLabel($r))->all(), $seen->all());
    }

    public function test_entered_records_alone_appear_as_mao_records(): void
    {
        RiceProduction::query()->create(['barangay' => 'San Diego', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.1, 'area_hectares' => 2.5, 'total_production' => 10.25, 'year' => 2024, 'production_month' => '2024-10-01']);

        $html = $this->page($this->farmer(), ['search' => 'san diego']);

        $this->assertStringContainsString('Showing 1–1 of 1', $html);
        $this->assertStringContainsString('MAO Record', $html);
        $this->assertStringContainsString(number_format(10.25, 1).'<small>t</small>', $html);   // real production, so a real total
        $this->assertStringContainsString('2.5<small>ha</small>', $html);
        $this->assertStringContainsString('1 from your barangay (San Diego)', $html);
    }

    public function test_barangay_records_and_municipal_months_both_show_and_stay_distinct(): void
    {
        $total = $this->dataset()->count();
        RiceProduction::query()->create(['barangay' => 'San Diego', 'season' => 'Wet', 'irrigation_type' => 'Rainfed', 'yield_per_hectare' => 3.9, 'year' => 2024, 'production_month' => '2024-09-01']);
        RiceProduction::query()->create(['barangay' => 'Balibago', 'season' => 'Dry', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.4, 'year' => 2024, 'production_month' => '2024-03-01']);

        $html = $this->page($this->farmer('San Diego'), ['search' => '2024']);

        // The San Diego farmer still sees every municipal month; only the San Diego record is marked as theirs,
        // and no municipal row carries a barangay name. A barangay record and the municipal month are not duplicates.
        $this->assertStringContainsString('<p class="frec-kpi-value">'.($this->dataset()->where('year', 2024)->count() + 2).'</p>', $html);
        $page2 = $this->page($this->farmer('San Diego'), ['search' => '2024', 'page' => 2]);
        $this->assertSame(1, substr_count($html.$page2, '<tr class="is-mine">'), 'Only the San Diego record is marked as theirs.');
        $this->assertStringContainsString('Balibago', $html.$page2);
        $this->assertStringContainsString('1 from your barangay (San Diego)', $html);
        $this->assertStringNotContainsString('Lian Municipal Average</th>', $html);
        $this->assertStringContainsString('Sep 2024', $html);
        $all = $this->page($this->farmer('San Diego'));
        $this->assertStringContainsString('Showing 1–10 of '.($total + 2), $all);
    }

    public function test_season_and_irrigation_filters_use_both_sources_without_inventing_irrigation(): void
    {
        $rows = $this->dataset();
        RiceProduction::query()->create(['barangay' => 'San Diego', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.0, 'year' => 2024]);
        $farmer = $this->farmer();

        $wet = $rows->where('season', 'Wet')->count() + 1;
        $this->assertStringContainsString('<p class="frec-kpi-value">'.$wet.'</p>', $this->page($farmer, ['season' => 'Wet']));
        $this->assertStringContainsString('<p class="frec-kpi-value">'.$rows->where('season', 'Dry')->count().'</p>', $this->page($farmer, ['season' => 'Dry']));

        // A specific irrigation type shows only records that have it; municipal months have none.
        $irrigated = $this->page($farmer, ['irrigation_type' => 'Irrigated']);
        $this->assertStringContainsString('Showing 1–1 of 1', $irrigated);
        $this->assertStringNotContainsString('iClimate record · Read-only', $irrigated);
        $this->assertStringNotContainsString('<option value="Rainfed"', $irrigated, 'Only irrigation types records really have.');

        $none = $this->page($farmer, ['season' => 'Wet', 'irrigation_type' => 'Irrigated', 'search' => 'no-such-place']);
        $this->assertStringContainsString('No records match your filters.', $none);
        $this->assertStringNotContainsString('No rice production records found.', $none);
    }

    public function test_missing_production_and_area_are_never_zero(): void
    {
        RiceProduction::query()->create(['barangay' => 'San Diego', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.0, 'area_hectares' => null, 'total_production' => null, 'year' => 2024]);

        $html = $this->page($this->farmer());

        $this->assertStringContainsString('No production total is available in these records.', $html);
        $this->assertStringContainsString('No area record is available.', $html);
        $this->assertStringNotContainsString('0.0<small>t</small>', $html);
        $this->assertStringNotContainsString('0.0<small>ha</small>', $html);
        $this->assertStringNotContainsString('>0.00</td>', $html);
    }

    public function test_the_farmer_page_shows_the_same_monthly_yield_as_the_staff_list_and_the_dataset(): void
    {
        $newest = $this->dataset()->first();
        $farmerHtml = $this->page($this->farmer());
        $itHtml = (string) $this->actingAs(User::factory()->create(['role' => User::ROLE_IT_EXPERT]))->get(route('rice-productions.index'))->getContent();

        $this->assertStringContainsString(number_format($newest['yield'], 2), $farmerHtml);
        $this->assertStringContainsString(rtrim(rtrim(number_format($newest['yield'], 2, '.', ','), '0'), '.').' t/ha', $itHtml);
        $this->assertStringContainsString($this->monthLabel($newest), $itHtml);
    }
}
