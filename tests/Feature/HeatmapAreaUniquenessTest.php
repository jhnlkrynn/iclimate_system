<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09 duplicate-heatmap-row audit finding:
 * `heatmap_areas` had a plain (non-unique) index on `barangay`, and
 * historical duplicate rows (e.g. Balibago x2, Humayingan x3) let
 * RoleAssistantService's `->latest()->first()` pick a months-stale row over
 * the actively-refreshed one. The dedupe+unique-index migration
 * (2026_09_22_005317_...) fixed the existing data; this test guards against
 * the underlying DB constraint ever being dropped or bypassed again.
 */
class HeatmapAreaUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_barangay_column_has_a_unique_database_constraint(): void
    {
        // An earlier migration (2026_06_30_000002_seed_lian_barangay_heatmap_defaults)
        // already seeds one row per barangay, so start from a clean slate for
        // this specific barangay rather than assuming none exists yet.
        HeatmapArea::query()->where('barangay', 'Bagong Pook')->delete();
        HeatmapArea::factory()->create(['barangay' => 'Bagong Pook']);

        // Bypass Eloquent's updateOrCreate entirely — a raw duplicate insert
        // must be rejected by the database itself, not merely avoided by
        // application code that could later be changed or bypassed.
        $threw = false;

        try {
            HeatmapArea::factory()->create(['barangay' => 'Bagong Pook']);
        } catch (\Throwable $exception) {
            $threw = true;
            $this->assertInstanceOf(QueryException::class, $exception);
        }

        $this->assertTrue($threw, 'Inserting a second heatmap_areas row for the same barangay must violate a unique DB constraint.');
        $this->assertSame(1, HeatmapArea::query()->where('barangay', 'Bagong Pook')->count());
    }

    public function test_updateorcreate_never_produces_a_second_row_for_the_same_barangay(): void
    {
        HeatmapArea::query()->updateOrCreate(
            ['barangay' => 'Balibago'],
            ['risk_level' => 'Low', 'risk_type' => 'Drought', 'risk_score' => 0.30]
        );
        HeatmapArea::query()->updateOrCreate(
            ['barangay' => 'Balibago'],
            ['risk_level' => 'High', 'risk_type' => 'Flood', 'risk_score' => 0.90]
        );

        $this->assertSame(1, HeatmapArea::query()->where('barangay', 'Balibago')->count());
        $this->assertSame('High', HeatmapArea::query()->where('barangay', 'Balibago')->first()->risk_level);
    }

    public function test_a_full_risk_map_refresh_never_creates_duplicate_rows(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([
            'current' => [
                'time' => now()->toIso8601String(),
                'temperature_2m' => 28,
                'relative_humidity_2m' => 70,
                'precipitation' => 8.3,
                'wind_speed_10m' => 10,
            ],
            'hourly' => [
                'precipitation' => array_fill(0, 24, 8.3),
                'precipitation_probability' => array_fill(0, 24, 50),
            ],
            'daily' => [
                'precipitation_sum' => [200],
                'precipitation_probability_max' => [50],
            ],
        ], 200)]);

        $service = app(BarangayRiskMapService::class);
        $service->riskMap(refresh: true);
        $service->riskMap(refresh: true);

        $this->assertSame(19, HeatmapArea::query()->count());
        $this->assertSame(19, HeatmapArea::query()->distinct()->count('barangay'));
    }
}
