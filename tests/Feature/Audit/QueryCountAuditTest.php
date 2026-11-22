<?php

namespace Tests\Feature\Audit;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit 2026-11-22: database queries per page, and whether they grow with the number of records (N+1 queries).
 * Each page is opened with a cold cache, once with a little data and once with much more; a page whose query count
 * grows with the rows is querying once per row. The counts are printed (STDERR) so the audit can report them.
 */
class QueryCountAuditTest extends TestCase
{
    use RefreshDatabase;

    /** Extra queries allowed when the data grows 15-fold (cache keys, one-off lookups); per-row queries go far past it. */
    private const GROWTH_ALLOWED = 6;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function queries(User $user, string $route): int
    {
        Cache::flush();
        $count = 0;
        DB::listen(function () use (&$count): void { $count++; });
        $this->actingAs($user)->get(route($route))->assertOk();
        DB::getEventDispatcher()?->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $count;
    }

    private function farmWithPlans(User $farmer, int $plans, int $i = 0): Farm
    {
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm '.$i, 'barangay' => 'Prenza',
            'farm_size_hectares' => 1.0 + $i, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_type' => 'Irrigated', 'is_primary' => $i === 0]);
        for ($p = 0; $p < $plans; $p++) {
            FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'farm_id' => $farm->id,
                'planting_date' => now()->subDays(30 + $p * 120)->toDateString(), 'scheduled_harvest_date' => now()->addDays(80 - $p * 120)->toDateString(),
                'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => 1.0]);
        }

        return $farm;
    }

    private function report(string $label, int $small, int $large): void
    {
        fwrite(STDERR, sprintf("\n  [query count] %-34s small: %3d  large: %3d  growth: %+d", $label, $small, $large, $large - $small));
        $this->assertLessThanOrEqual($small + self::GROWTH_ALLOWED, $large, "{$label}: queries grow with the data (N+1?)");
    }

    public function test_farmer_pages_do_not_query_once_per_farm_or_plan(): void
    {
        $small = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $this->farmWithPlans($small, 1);

        $large = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        for ($i = 0; $i < 15; $i++) {
            $this->farmWithPlans($large, 4, $i);
        }

        foreach (['farmer.dashboard', 'farmer.my-farm', 'weather-predictions.index', 'calendar.index', 'planting-advisories.index'] as $route) {
            $this->report($route, $this->queries($small, $route), $this->queries($large, $route));
        }
    }

    public function test_mao_pages_do_not_query_once_per_farmer(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $this->farmWithPlans($farmer, 1);
        $routes = ['mao.dashboard', 'mao.farmer-approvals.index', 'farmer-profiles.index', 'mao.barangays.index', 'mao.farm-associations.index'];
        $small = [];
        foreach ($routes as $route) {
            $small[$route] = $this->queries($mao, $route);
        }

        for ($i = 0; $i < 15; $i++) {
            $this->farmWithPlans(User::factory()->farmer()->create(['barangay' => 'Prenza']), 2, $i);
        }
        foreach ($routes as $route) {
            $this->report($route, $small[$route], $this->queries($mao, $route));
        }
    }
}
