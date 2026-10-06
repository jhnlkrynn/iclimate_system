<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\Analytics\MaoDashboardService;
use App\Support\DashboardData;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ComputesRiskRatings;
use Tests\TestCase;

/**
 * The install migration seeds every barangay with a rating nobody computed (mostly "Moderate / Pending climate update",
 * plus hand-written demo levels). MAO risk statistics count only real computed ratings, the same rule the risk map and
 * the farmer dashboard use; a barangay with only a seed row is No Data.
 *
 * Fixture: the 19 seeded rows (15 Moderate, 2 High, 2 Low as seeded), four of which are then computed as
 * Low / Moderate / High / Severe. Before this rule the MAO counts were Low 3, Moderate 12, High 3, Severe 1 (19 "rated");
 * now they are 1 / 1 / 1 / 1 with 15 No Data.
 */
class MaoRiskCountsPlaceholderTest extends TestCase
{
    use ComputesRiskRatings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);              // page requests never compute the map anyway
        $this->computedRiskRating('Bagong Pook', 'Low');
        $this->computedRiskRating('Balibago', 'Moderate');
        $this->computedRiskRating('Barangay 2 (Pob.)', 'High');
        $this->computedRiskRating('Barangay 3 (Pob.)', 'Severe');
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $ratings = app(DashboardData::class)->currentRiskRatings();

        return collect(['Low', 'Moderate', 'High', 'Severe'])->mapWithKeys(fn ($l) => [$l => $ratings->where('risk_level', $l)->count()])->all();
    }

    public function test_the_seed_rows_are_recognised_as_placeholders_and_computed_rows_are_not(): void
    {
        $seeded = HeatmapArea::query()->whereNotIn('barangay', ['Bagong Pook', 'Balibago', 'Barangay 2 (Pob.)', 'Barangay 3 (Pob.)'])->get();

        $this->assertCount(15, $seeded);
        $this->assertTrue($seeded->every->isSeedPlaceholder());
        $this->assertSame(['High', 'Low', 'Moderate'], $seeded->pluck('risk_level')->unique()->sort()->values()->all());   // demo levels too
        $this->assertSame(4, HeatmapArea::query()->computed()->count());
        $this->assertFalse(HeatmapArea::query()->where('barangay', 'Balibago')->first()->isSeedPlaceholder());
    }

    public function test_placeholders_are_excluded_and_every_genuine_level_is_counted(): void
    {
        $this->assertSame(['Low' => 1, 'Moderate' => 1, 'High' => 1, 'Severe' => 1], $this->counts());

        $barangays = app(DashboardData::class)->currentRiskRatings()->pluck('barangay')->all();
        $this->assertNotContains('Bungahan', $barangays);       // seeded "Moderate / Pending climate update"
        $this->assertNotContains('Malaruhatan', $barangays);    // seeded demo "High"
    }

    public function test_the_latest_genuine_rating_is_used(): void
    {
        $this->travel(2)->hours();
        $this->computedRiskRating('Balibago', 'High');           // recomputed: Moderate -> High

        $this->assertSame(['Low' => 1, 'Moderate' => 0, 'High' => 2, 'Severe' => 1], $this->counts());
        $this->assertSame('High', app(DashboardData::class)->currentRiskRatings()->firstWhere('barangay', 'Balibago')->risk_level);
    }

    public function test_the_municipal_risk_overview_totals_add_up(): void
    {
        $risk = app(MaoDashboardService::class)->payload()['risk'];

        $this->assertSame('ok', $risk['state']);
        $this->assertSame(['Low' => 1, 'Moderate' => 1, 'High' => 1, 'Severe' => 1], array_intersect_key($risk['counts'], array_flip(['Low', 'Moderate', 'High', 'Severe'])));
        $this->assertSame(4, $risk['rated']);
        $this->assertSame(array_sum($risk['counts']), $risk['rated']);
        $this->assertSame(count(LianBarangays::all()), $risk['total']);
        $this->assertSame($risk['total'], $risk['rated'] + $risk['no_data']);
        $this->assertSame('No Data', collect($risk['barangays'])->firstWhere('barangay', 'Bungahan')['level']);
        $this->assertSame('No Data', collect($risk['barangays'])->firstWhere('barangay', 'Malaruhatan')['level']);
    }

    public function test_with_only_placeholders_nothing_is_counted_and_no_warning_is_raised(): void
    {
        HeatmapArea::query()->whereIn('barangay', ['Bagong Pook', 'Balibago', 'Barangay 2 (Pob.)', 'Barangay 3 (Pob.)'])
            ->update(['rainfall_status' => HeatmapArea::SEED_RAINFALL_STATUS, 'description' => HeatmapArea::SEED_DESCRIPTION]);
        Cache::flush();

        $this->assertSame(['Low' => 0, 'Moderate' => 0, 'High' => 0, 'Severe' => 0], $this->counts());
        $this->assertSame('empty', app(MaoDashboardService::class)->payload()['risk']['state']);   // "No barangay has a calculated rating yet"

    }
}
