<?php

namespace Tests\Feature;

use App\Http\Controllers\ClimateRecordController;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\Report;
use App\Models\User;
use App\Services\Analytics\AnalyticsPayloadFactory;
use App\Services\Analytics\AnalyticsResultPersister;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\Climate\ClimateRecordHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reports use the same climate records as Climate Records — the historical PAGASA Ambulong months and the entered
 * climate_records rows — and keep the two apart: each has its own count and figures, and a month's rainfall total
 * is never averaged with one day's reading.
 */
class ClimateReportSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    /** @return Collection<int, array<string, mixed>> the station months of a year, from the shared loader */
    private function stationYear(int $year): Collection
    {
        return collect(app(AmbulongHistoricalClimateDataset::class)->load()['observations'])->where('year', $year)->values();
    }

    /** The rows the climate report sends for processing, as the payload factory builds them. */
    private function sourceRows(array $filters = []): Collection
    {
        $run = AnalyticsRun::query()->create([
            'uuid' => (string) Str::uuid(), 'type' => 'report_analytics', 'status' => AnalyticsRun::STATUS_PROCESSING,
            'requested_by' => $this->mao()->id, 'input' => ['report_type' => 'climate_records', 'filters' => $filters],
        ]);

        return collect(app(AnalyticsPayloadFactory::class)->make($run)['source_rows']);
    }

    /** Saves a climate report with the given rows the way a completed processing run does, and returns it. */
    private function saveReport(array $records, array $filters = []): Report
    {
        $mao = $this->mao();
        $run = AnalyticsRun::query()->create([
            'uuid' => (string) Str::uuid(), 'type' => 'report_analytics', 'status' => AnalyticsRun::STATUS_PROCESSING,
            'requested_by' => $mao->id, 'input' => ['report_type' => 'climate_records', 'filters' => $filters],
        ]);
        $result = app(AnalyticsResultPersister::class)->persist($run, [
            'report_type' => 'climate_records', 'filters' => $filters,
            'columns' => ['record_type' => 'Record Type', 'record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature', 'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source'],
            'records' => $records, 'row_count' => count($records), 'processor' => 'python',
        ]);
        $run->update(['status' => AnalyticsRun::STATUS_COMPLETED, 'result' => $result]);

        return Report::query()->latest('id')->firstOrFail();
    }

    /** @return array<string, string> label => value for one summary group */
    private function group(Report $report, string $group): array
    {
        return collect($report->summary())->where('group', $group)->filter(fn ($i) => isset($i['label']))->pluck('value', 'label')->all();
    }

    public function test_the_report_gets_the_historical_months_while_climate_records_is_empty(): void
    {
        $this->assertSame(0, ClimateRecord::query()->count());
        $rows = $this->sourceRows();
        $total = collect(app(AmbulongHistoricalClimateDataset::class)->load()['observations'])->count();

        $this->assertCount($total, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r['record_type'] === Report::CLIMATE_HISTORICAL && strlen($r['record_date']) === 7));
        $this->assertTrue($rows->every(fn ($r) => $r['source'] === ClimateRecordController::AMBULONG_SOURCE));
        // The station wind has no confirmed unit, so the report never gives it as a number.
        $this->assertTrue($rows->every(fn ($r) => $r['wind_speed'] === null || $r['wind_speed'] === 'Unit not confirmed'));
        $this->assertSame(0, ClimateRecord::query()->count(), 'Nothing is copied into climate_records.');
    }

    public function test_historical_and_entered_figures_are_summarised_separately(): void
    {
        $months = $this->stationYear(2024);
        $this->assertCount(12, $months);
        $records = $months->map(fn ($m) => [
            'record_type' => Report::CLIMATE_HISTORICAL, 'record_date' => sprintf('%d-%02d', $m['year'], $m['month']),
            'rainfall' => $m['rainfall'], 'temperature' => $m['temperature'], 'humidity' => $m['humidity'], 'season' => $m['season'], 'source' => ClimateRecordController::AMBULONG_SOURCE,
        ])->all();
        $records[] = ['record_type' => Report::CLIMATE_ENTERED, 'record_date' => '2024-03-05', 'rainfall' => 10.0, 'temperature' => 30.0, 'humidity' => 80.0, 'season' => 'Dry', 'source' => 'MAO field log'];
        $records[] = ['record_type' => Report::CLIMATE_ENTERED, 'record_date' => '2024-03-07', 'rainfall' => 20.0, 'temperature' => 31.0, 'humidity' => null, 'season' => 'Dry', 'source' => 'MAO field log'];
        $report = $this->saveReport($records, ['date_from' => '2024-01-01', 'date_to' => '2024-12-31']);

        $historical = $this->group($report, 'Historical Weather');
        $this->assertSame('12 (Jan 2024 – Dec 2024)', $historical['Months Included']);
        $this->assertSame(number_format($months->sum('rainfall'), 1).' mm', $historical['Total Rainfall']);
        $this->assertSame(number_format($months->avg('rainfall'), 1).' mm', $historical['Average Monthly Rainfall']);
        $this->assertSame(number_format($months->avg('temperature'), 1).'°C', $historical['Average Temperature']);

        $entered = $this->group($report, 'Entered Records');
        $this->assertSame('2 (Mar 5, 2024 – Mar 7, 2024)', $entered['Records']);
        $this->assertSame('15.0 mm', $entered['Average Rainfall per Record']);
        $this->assertSame('80.0%', $entered['Average Humidity'], 'The missing humidity is left out (as 0 it would give 40.0%).');

        // Nothing averages monthly totals with daily readings.
        $mixed = number_format((array_sum(array_column($records, 'rainfall'))) / count($records), 1).' mm';
        $this->assertNotContains($mixed, collect($report->summary())->pluck('value')->all());
        $this->assertSame([], collect($report->summary())->filter(fn ($i) => ! isset($i['group']))->all(), 'Every figure belongs to one group.');
    }

    public function test_the_page_shows_historical_weather_with_honest_source_wording_when_nothing_was_entered(): void
    {
        $months = $this->stationYear(2024);
        $report = $this->saveReport($months->map(fn ($m) => [
            'record_type' => Report::CLIMATE_HISTORICAL, 'record_date' => sprintf('%d-%02d', $m['year'], $m['month']),
            'rainfall' => $m['rainfall'], 'temperature' => $m['temperature'], 'humidity' => $m['humidity'], 'season' => $m['season'], 'source' => ClimateRecordController::AMBULONG_SOURCE,
        ])->all(), ['date_from' => '2024-01-01', 'date_to' => '2024-12-31']);

        $this->actingAs($this->mao())->get(route('reports.show', $report->id))->assertOk()
            ->assertSee('Historical Weather')
            ->assertSee('PAGASA Ambulong Station (Tanauan, Batangas) · used by iClimate as the weather reference for Lian')
            ->assertSee('12 (Jan 2024 – Dec 2024)')
            ->assertSee('Entered Records')
            ->assertSee('No entered climate records for this period.')
            ->assertSee('Record Type')
            ->assertDontSee('recorded in Lian');
    }

    public function test_missing_historical_values_stay_missing(): void
    {
        $report = $this->saveReport([
            ['record_type' => Report::CLIMATE_HISTORICAL, 'record_date' => '2024-01', 'rainfall' => null, 'temperature' => 25.0, 'humidity' => null, 'source' => ClimateRecordController::AMBULONG_SOURCE],
            ['record_type' => Report::CLIMATE_HISTORICAL, 'record_date' => '2024-02', 'rainfall' => null, 'temperature' => 27.0, 'humidity' => null, 'source' => ClimateRecordController::AMBULONG_SOURCE],
        ]);
        $historical = $this->group($report, 'Historical Weather');

        $this->assertSame('Not available', $historical['Total Rainfall']);
        $this->assertSame('Not available', $historical['Average Humidity']);
        $this->assertSame('26.0°C', $historical['Average Temperature']);
    }

    public function test_an_older_report_of_entered_rows_is_summarised_as_entered_records(): void
    {
        // Reports made before this change hold only climate_records rows, without a record type.
        $report = $this->saveReport([
            ['record_date' => '2026-09-05', 'rainfall' => '100.00', 'temperature' => '28.00', 'humidity' => '80.00', 'source' => 'PAGASA'],
            ['record_date' => '2026-09-15', 'rainfall' => '200.00', 'temperature' => '30.00', 'humidity' => '90.00', 'source' => 'PAGASA'],
        ]);

        $this->assertSame('150.0 mm', $this->group($report, 'Entered Records')['Average Rainfall per Record']);
        $this->assertContains('No historical weather months for this period.', collect($report->summary())->pluck('note')->filter()->all());
    }

    public function test_the_date_range_counts_months_that_overlap_it_and_entered_dates_inside_it(): void
    {
        ClimateRecord::query()->create(['record_date' => '2024-01-10', 'rainfall' => 3, 'temperature' => 27, 'humidity' => 80, 'season' => 'Dry', 'source' => 'MAO field log']);
        ClimateRecord::query()->create(['record_date' => '2024-02-01', 'rainfall' => 5, 'temperature' => 28, 'humidity' => 81, 'season' => 'Dry', 'source' => 'MAO field log']);
        [$rows] = app(ClimateRecordHistory::class)->records(plausibleOnly: false);

        $inRange = ClimateRecordHistory::inRange($rows, '2024-01-15', '2024-02-10');

        $this->assertSame(['Jan 2024', 'Feb 2024'], $inRange->where('group', ClimateRecordHistory::HISTORICAL)->sortBy(fn ($r) => $r->record_date)->pluck('date_label')->values()->all());
        $this->assertSame(['Feb 1, 2024'], $inRange->where('group', ClimateRecordHistory::ENTERED)->pluck('date_label')->values()->all());
    }

    public function test_an_entered_copy_of_a_station_month_is_not_listed_twice(): void
    {
        ClimateRecord::query()->create(['record_date' => '2024-03-01', 'rainfall' => 40, 'temperature' => 28, 'humidity' => 80, 'season' => 'Dry', 'source' => ClimateRecordController::AMBULONG_SOURCE]);
        $rows = $this->sourceRows();

        $march = $rows->filter(fn ($r) => str_starts_with($r['record_date'], '2024-03'));
        $this->assertCount(1, $march);
        $this->assertSame(Report::CLIMATE_ENTERED, $march->first()['record_type']);
        $this->assertSame(1, ClimateRecord::query()->count());
    }

    public function test_other_reports_show_the_weather_of_their_period_in_two_parts(): void
    {
        $months = $this->stationYear(2024);
        $mao = $this->mao();
        $run = AnalyticsRun::query()->create([
            'uuid' => (string) Str::uuid(), 'type' => 'report_analytics', 'status' => AnalyticsRun::STATUS_PROCESSING,
            'requested_by' => $mao->id, 'input' => ['report_type' => 'rice_production', 'filters' => ['year' => 2024]],
        ]);
        $result = app(AnalyticsResultPersister::class)->persist($run, [
            'report_type' => 'rice_production', 'filters' => ['year' => 2024],
            'columns' => ['barangay' => 'Barangay', 'yield_per_hectare' => 'Yield/Ha'], 'records' => [], 'row_count' => 0, 'processor' => 'python',
        ]);
        $run->update(['status' => AnalyticsRun::STATUS_COMPLETED, 'result' => $result]);

        $this->actingAs($mao)->get(route('reports.index', ['run' => $run->uuid]))->assertOk()
            ->assertSee('Weather During This Period')
            ->assertSee('12 month(s), Jan 2024 – Dec 2024')
            ->assertSee(number_format($months->sum('rainfall'), 1).' mm total rainfall')
            ->assertSee('no entered climate records for this period.')
            // The source count is the records Climate Records lists, not the empty table.
            ->assertSee('<strong>'.number_format(collect(app(AmbulongHistoricalClimateDataset::class)->load()['observations'])->count()).'</strong><span>Climate</span>', false);
    }
}
