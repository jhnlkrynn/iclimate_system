<?php

namespace Tests\Unit\Climate;

use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AmbulongHistoricalClimateDatasetTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function tempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ambulong_test_').'.csv';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_the_real_shipped_csv_loads_correctly_row_count_sentinel_exclusion_and_temperature_derivation(): void
    {
        Cache::flush();

        $dataset = new AmbulongHistoricalClimateDataset;
        $result = $dataset->load();

        $this->assertTrue($result['available']);
        $this->assertNull($result['message']);

        // 132 data rows in the CSV, minus the one 2020-01 sentinel row.
        $this->assertSame(131, $result['observations']->count());
        $this->assertSame(1, $result['excluded_sentinel_count']);
        $this->assertSame(0, $result['duplicate_count']);

        // No trace of the sentinel month among the loaded observations.
        $sentinelMonth = $result['observations']->first(fn (array $o) => $o['year'] === 2020 && $o['month'] === 1);
        $this->assertNull($sentinelMonth);

        // 2015-01: RAINFALL 50.8, TMAX 28.6, TMIN 21.6 => temperature = (28.6+21.6)/2 = 25.1
        $jan2015 = $result['observations']->first(fn (array $o) => $o['year'] === 2015 && $o['month'] === 1);
        $this->assertNotNull($jan2015);
        $this->assertSame(50.8, $jan2015['rainfall']);
        $this->assertEqualsWithDelta(25.1, $jan2015['temperature'], 0.001);
        $this->assertSame('Dry', $jan2015['season']);
    }

    public function test_missing_csv_file_returns_graceful_unavailable_state(): void
    {
        Cache::flush();

        $dataset = new AmbulongHistoricalClimateDataset(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv');
        $result = $dataset->load();

        $this->assertFalse($result['available']);
        $this->assertNotNull($result['message']);
        $this->assertTrue($result['observations']->isEmpty());
    }

    public function test_malformed_headers_return_graceful_unavailable_state(): void
    {
        Cache::flush();

        $path = $this->tempCsv("A,B,C\n1,2,3\n");
        $dataset = new AmbulongHistoricalClimateDataset($path);
        $result = $dataset->load();

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('missing required column', $result['message']);
    }

    public function test_empty_file_returns_graceful_unavailable_state(): void
    {
        Cache::flush();

        $path = $this->tempCsv('');
        $dataset = new AmbulongHistoricalClimateDataset($path);
        $result = $dataset->load();

        $this->assertFalse($result['available']);
        $this->assertNotNull($result['message']);
    }

    public function test_duplicate_year_month_rows_are_deduplicated_keeping_the_first_occurrence(): void
    {
        Cache::flush();

        $path = $this->tempCsv(
            "YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION\n".
            "2021,1,10,30,20,80,2,40\n".
            "2021,1,999,31,21,79,2,40\n". // duplicate month with plausible values (loader now rejects impossible ones as invalid before dedup) — must be ignored
            "2021,2,20,31,21,78,2,40\n"
        );

        $dataset = new AmbulongHistoricalClimateDataset($path);
        $result = $dataset->load();

        $this->assertTrue($result['available']);
        $this->assertSame(2, $result['observations']->count());
        $this->assertSame(1, $result['duplicate_count']);

        $jan = $result['observations']->first(fn (array $o) => $o['year'] === 2021 && $o['month'] === 1);
        // The FIRST occurrence's rainfall (10), not the duplicate's (999).
        $this->assertSame(10.0, $jan['rainfall']);
    }

    public function test_a_row_where_only_core_fields_are_sentinel_is_still_excluded(): void
    {
        Cache::flush();

        // Generic detection: RAINFALL/TMAX/TMIN equal to -999 marks the row
        // as missing even if RH/WIND_SPEED happen to hold other values.
        $path = $this->tempCsv(
            "YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION\n".
            "2022,5,-999,-999,-999,70,2,40\n".
            "2022,6,100,30,22,75,2,40\n"
        );

        $dataset = new AmbulongHistoricalClimateDataset($path);
        $result = $dataset->load();

        $this->assertTrue($result['available']);
        $this->assertSame(1, $result['observations']->count());
        $this->assertSame(1, $result['excluded_sentinel_count']);
    }

    public function test_cache_key_changes_when_the_file_is_modified_so_updates_are_not_silently_ignored(): void
    {
        Cache::flush();

        $path = $this->tempCsv(
            "YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION\n".
            "2023,1,10,30,20,80,2,40\n"
        );

        $dataset = new AmbulongHistoricalClimateDataset($path);
        $versionBefore = $dataset->version();
        $resultBefore = $dataset->load();
        $this->assertSame(1, $resultBefore['observations']->count());

        // Touch the file with new content and a forced later mtime.
        sleep(1);
        file_put_contents($path, "YEAR,MONTH,RAINFALL,TMAX,TMIN,RH,WIND_SPEED,WIND_DIRECTION\n2023,1,10,30,20,80,2,40\n2023,2,20,31,21,78,2,40\n");
        touch($path, time() + 5);

        $versionAfter = $dataset->version();
        $this->assertNotSame($versionBefore, $versionAfter);

        $resultAfter = $dataset->load();
        $this->assertSame(2, $resultAfter['observations']->count());
    }
}
