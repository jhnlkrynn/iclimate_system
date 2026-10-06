<?php

namespace Tests\Feature;

use App\Http\Controllers\ClimateRecordController;
use App\Models\ClimateRecord;
use App\Models\User;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The farmer Climate Records page lists the stored climate history (the Ambulong monthly record plus entered
 * climate_records rows) and works out every figure from exactly the records listed.
 */
class ClimateRecordsFarmerTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION\n";

    private function useAmbulongCsv(string $rows): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ambulong').'.csv';
        file_put_contents($path, self::HEADER.$rows);
        $this->app->instance(AmbulongHistoricalClimateDataset::class, new AmbulongHistoricalClimateDataset($path));
    }

    private function farmer(): User
    {
        return User::factory()->create(['role' => User::ROLE_FARMER]);
    }

    public function test_farmers_see_stored_station_and_entered_records_with_figures_from_the_same_records(): void
    {
        // Jan 2024: mean 25.0; Feb 2024 carries the dataset's -999 "missing" code and is not a record; Jun 2024: mean 28.0.
        $this->useAmbulongCsv("2024,1,10,30,20,80,2,40\n2024,2,-999,31,21,79,2,40\n2024,6,200,32,24,90,2,40\n");
        ClimateRecord::query()->create(['record_date' => '2024-03-05', 'rainfall' => 5, 'temperature' => 30, 'humidity' => null, 'season' => 'Dry', 'source' => 'MAO field entry']);

        $html = $this->actingAs($this->farmer())->get(route('climate-records.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<p class="frec-kpi-value">3</p>', $html);
        $this->assertStringContainsString('27.7<small>°C</small>', $html);        // (25 + 28 + 30) / 3
        $this->assertStringContainsString('Highest 30.0°C · Lowest 25.0°C', $html);
        $this->assertStringContainsString('215.0<small>mm</small>', $html);        // 10 + 200 + 5
        $this->assertStringContainsString('85<small>%</small>', $html);            // (80 + 90) / 2: the missing humidity is skipped, not 0
        $this->assertStringContainsString('Jan 2024 – Jun 2024', $html);            // oldest to newest record (Jun 2024 is the newest)
        $this->assertStringContainsString('Other records come from MAO field entry.', $html);
        $this->assertStringContainsString('<option value="MAO field entry"', $html); // source choices come from the data
        $this->assertStringContainsString('<option value="Wet"', $html);
        // Only the entered record has its own page; station months do not.
        $this->assertSame(1, substr_count($html, 'aria-label="View record for'));
        $this->assertStringNotContainsString('Wind Speed*', $html);
    }

    public function test_station_rows_are_presented_for_lian_while_the_real_source_stays_visible_and_filterable(): void
    {
        $this->useAmbulongCsv("2024,1,10,30,20,80,2,40\n2024,6,200,32,24,90,2,40\n");

        $html = $this->actingAs($this->farmer())->get(route('climate-records.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Climate Records for Lian, Batangas</h1>', $html);
        $this->assertStringContainsString('Past weather records iClimate uses for Lian, Batangas.', $html);
        $this->assertStringContainsString('Lian, Batangas Climate Records', $html);              // data reference label
        $this->assertStringContainsString('Source: PAGASA Ambulong Station, Tanauan, Batangas.', $html);
        $this->assertStringContainsString('>'.ClimateRecordController::AMBULONG_ROW_LABEL.'<', $html);
        // The filter shows the Lian label but still submits the real stored source.
        $this->assertMatchesRegularExpression('/<option value="'.preg_quote(e(ClimateRecordController::AMBULONG_SOURCE), '/').'"\s*>'.ClimateRecordController::AMBULONG_FILTER_LABEL.'<\/option>/', $html);
        $this->assertStringNotContainsString('Recorded in Lian', $html);
        $this->assertStringNotContainsString('Lian Weather Station', $html);

        $filtered = $this->actingAs($this->farmer())->get(route('climate-records.index', ['source' => ClimateRecordController::AMBULONG_SOURCE]))->getContent();
        $this->assertStringContainsString('<p class="frec-kpi-value">2</p>', $filtered);
    }

    public function test_filters_search_and_no_match_state(): void
    {
        $this->useAmbulongCsv("2024,1,10,30,20,80,2,40\n2024,6,200,32,24,90,2,40\n");

        $wet = $this->actingAs($this->farmer())->get(route('climate-records.index', ['season' => 'Wet']))->getContent();
        $this->assertStringContainsString('<p class="frec-kpi-value">1</p>', $wet);
        $this->assertStringContainsString('200.0<small>mm</small>', $wet);

        $search = $this->actingAs($this->farmer())->get(route('climate-records.index', ['search' => 'jan 2024']))->getContent();
        $this->assertStringContainsString('10.0<small>mm</small>', $search);

        $none = $this->actingAs($this->farmer())->get(route('climate-records.index', ['search' => 'no-such-record']))->getContent();
        $this->assertStringContainsString('No records match your current filters.', $none);
        $this->assertStringNotContainsString('No climate records found.', $none);
    }

    public function test_an_unreadable_station_record_is_reported_as_unavailable_not_as_no_records(): void
    {
        $this->app->instance(AmbulongHistoricalClimateDataset::class, new AmbulongHistoricalClimateDataset('/nonexistent/ambulong.csv'));

        $html = $this->actingAs($this->farmer())->get(route('climate-records.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Climate records are temporarily unavailable.', $html);
        $this->assertStringNotContainsString('No climate records found.', $html);
    }

    public function test_nothing_stored_shows_no_climate_records_found(): void
    {
        $this->app->instance(AmbulongHistoricalClimateDataset::class, new class extends AmbulongHistoricalClimateDataset
        {
            public function load(): array
            {
                return ['available' => true, 'message' => null, 'observations' => collect(), 'excluded_sentinel_count' => 0, 'invalid_row_count' => 0, 'duplicate_count' => 0];
            }
        });

        $this->actingAs($this->farmer())->get(route('climate-records.index'))
            ->assertOk()->assertSee('No climate records found.')->assertDontSee('temporarily unavailable');
    }

    public function test_pages_keep_the_filters(): void
    {
        // Four years: the dry season (Jan–Apr, Oct–Dec) gives 28 records, three more than a page (25) holds.
        $this->useAmbulongCsv(collect([2021, 2022, 2023, 2024])->flatMap(fn (int $y) => collect(range(1, 12))->map(fn (int $m) => "{$y},{$m},10,30,20,80,2,40"))->implode("\n")."\n");

        $first = $this->actingAs($this->farmer())->get(route('climate-records.index', ['season' => 'Dry']))->getContent();
        $this->assertStringContainsString('Showing 1–25 of 28', $first);
        $this->assertStringContainsString('season=Dry&amp;page=2', $first);                 // page links keep the filter

        $second = $this->actingAs($this->farmer())->get(route('climate-records.index', ['season' => 'Dry', 'page' => 2]))->getContent();
        $this->assertStringContainsString('Showing 26–28 of 28', $second);
        $this->assertStringContainsString('<th scope="row">Jan 2021</th>', $second);           // newest first, so the oldest is last
        $this->assertStringContainsString('<p class="frec-kpi-value">28</p>', $second);       // figures cover all matches, not one page
    }
}
