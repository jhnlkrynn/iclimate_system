<?php

namespace Tests\Feature;

use App\Models\ExternalWeatherData;
use App\Models\MonthlyWeatherPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MariaDB fills an "ON UPDATE CURRENT_TIMESTAMP" column from its +08:00 session clock when an UPDATE leaves it
 * out, which Laravel then reads 8 hours in the future (external_weather_data row 99, 2026-11-10). Every Eloquent
 * UPDATE of these models must write the column itself.
 */
class DatabaseClockColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_update_that_does_not_change_the_fetch_time_still_writes_it(): void
    {
        $fetched = Carbon::parse('2026-11-10 15:57:01', 'UTC');
        $row = ExternalWeatherData::query()->create([
            'source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.03, 'longitude' => 120.65,
            'forecast_date' => '2026-11-10', 'temperature' => 30, 'fetched_at' => $fetched,
        ]);

        DB::enableQueryLog();
        // What the dashboard snapshot does: new values, same fetch time (so Eloquent sees fetched_at as unchanged).
        $row->update(['temperature' => 31, 'fetched_at' => $fetched]);
        $update = collect(DB::getQueryLog())->pluck('query')->first(fn (string $sql) => str_starts_with(strtolower($sql), 'update'));

        $this->assertNotNull($update);
        $this->assertStringContainsString('fetched_at', $update);
        $this->assertSame('2026-11-10 15:57:01', $row->fresh()->getRawOriginal('fetched_at'));
        $this->assertFalse($row->wasChanged('fetched_at'), 'the column is written, not reported as a change');
    }

    public function test_a_save_without_changes_sends_no_update(): void
    {
        $row = ExternalWeatherData::query()->create([
            'source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.03, 'longitude' => 120.65,
            'forecast_date' => '2026-11-11', 'fetched_at' => now(),
        ]);

        DB::enableQueryLog();
        $row->save();

        $this->assertSame([], collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'update'))->all());
    }

    public function test_every_model_with_a_database_clock_column_uses_the_guard(): void
    {
        foreach ([ExternalWeatherData::class => 'fetched_at', MonthlyWeatherPrediction::class => 'generated_at',
            \App\Models\TyphoonSafetyResponse::class => 'responded_at', \App\Models\BackupVerification::class => 'verified_at',
            \App\Models\WeatherFetchLog::class => 'started_at', \App\Models\WeatherForecastFeedback::class => 'submitted_at'] as $model => $column) {
            $this->assertContains(\App\Models\Concerns\KeepsDatabaseClockColumns::class, class_uses($model), $model);
            $this->assertSame([$column], $model::DATABASE_CLOCK_COLUMNS, $model);
        }
    }

    public function test_a_manila_zoned_yield_time_is_stored_as_the_same_instant_in_utc(): void
    {
        $at = \Carbon\CarbonImmutable::parse('2026-11-11 02:40:35', 'Asia/Manila');   // = 2026-11-10 18:40:35 UTC
        foreach ([$at, $at->toIso8601String()] as $i => $value) {
            $row = \App\Models\YieldPrediction::query()->create([
                'scope' => 'barangay', 'subject_key' => 'barangay:Prenza'.$i, 'barangay' => 'Prenza', 'season_start' => '2026-11-01',
                'predicted_yield' => 4.2, 'classification' => 'Average', 'season' => 'Wet', 'basis' => 'barangay_model', 'generated_at' => $value,
            ]);

            $this->assertSame('2026-11-10 18:40:35', $row->fresh()->getRawOriginal('generated_at'));
            $this->assertTrue($row->fresh()->generated_at->equalTo($at));
        }
    }
}
