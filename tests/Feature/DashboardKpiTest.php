<?php

namespace Tests\Feature;

use App\Models\FieldReport;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Dashboards call live weather/ML services; keep tests offline.
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function rice(string $barangay, int $year, float $area, float $production): void
    {
        RiceProduction::factory()->create([
            'barangay' => $barangay,
            'year' => $year,
            'area_hectares' => $area,
            'total_production' => $production,
            'yield_per_hectare' => round($production / $area, 2),
        ]);
    }

    private function kpi(array $kpis, string $key): array
    {
        $match = collect($kpis)->firstWhere('key', $key);
        $this->assertNotNull($match, "KPI [{$key}] missing");

        return $match;
    }

    public function test_yield_is_weighted_by_area_not_a_plain_average(): void
    {
        // 50 t on 10 ha (5 t/ha) + 4 t on 2 ha (2 t/ha): plain avg 3.5, true yield 54/12 = 4.5.
        $this->rice('Binubusan', 2024, 10, 50);
        $this->rice('Lumaniag', 2024, 2, 4);

        $latest = app(DashboardKpiService::class)->yieldByYear()->first();

        $this->assertSame(2024, $latest['year']);
        $this->assertEqualsWithDelta(4.5, $latest['yield'], 0.001);
        $this->assertEqualsWithDelta(54.0, $latest['production'], 0.001);
    }

    public function test_municipal_yoy_only_compares_barangays_reporting_both_years(): void
    {
        $this->rice('Binubusan', 2023, 10, 40);   // 4 t/ha
        $this->rice('Binubusan', 2024, 10, 50);   // 5 t/ha
        $this->rice('Lumaniag', 2024, 2, 4);      // new in 2024 — must not skew the comparison

        $yoy = app(DashboardKpiService::class)->yieldYoy();

        $this->assertSame(25.0, $yoy['delta_percent']);
        $this->assertSame(1, $yoy['barangays_compared']);
        $this->assertSame('up', $yoy['direction']);
    }

    public function test_yoy_is_null_without_two_years(): void
    {
        $this->rice('Binubusan', 2024, 10, 50);

        $this->assertNull(app(DashboardKpiService::class)->yieldYoy());
    }

    public function test_mao_production_kpi_uses_latest_year_only(): void
    {
        $this->rice('Binubusan', 2023, 10, 40);
        $this->rice('Binubusan', 2024, 10, 50);

        $production = $this->kpi(app(DashboardKpiService::class)->mao(), 'rice-production');

        $this->assertSame('50.0', $production['value']);
        $this->assertStringContainsString('2024', $production['label']);
    }

    public function test_mao_high_risk_kpi_counts_high_and_severe_barangays(): void
    {
        HeatmapArea::query()->delete(); // drop the seeded defaults
        HeatmapArea::factory()->create(['barangay' => 'Binubusan', 'risk_level' => 'Severe']);
        HeatmapArea::factory()->create(['barangay' => 'Lumaniag', 'risk_level' => 'Low']);

        $kpi = $this->kpi(app(DashboardKpiService::class)->mao(), 'high-risk');

        $this->assertSame('1', $kpi['value']);
        $this->assertSame('critical', $kpi['status']);
    }

    public function test_mao_field_report_kpi_counts_only_unresolved(): void
    {
        $reporter = User::factory()->create(['role' => User::ROLE_FARMER]);
        foreach ([FieldReport::STATUS_PENDING, FieldReport::STATUS_VERIFIED, FieldReport::STATUS_RESOLVED] as $status) {
            FieldReport::query()->forceCreate([
                'title' => 'Test report',
                'reported_by' => $reporter->id,
                'barangay' => 'Binubusan',
                'issue_type' => 'Other',
                'description' => 'Test report',
                'status' => $status,
                'resolved_at' => $status === FieldReport::STATUS_RESOLVED ? now() : null,
            ]);
        }

        $kpi = $this->kpi(app(DashboardKpiService::class)->mao(), 'field-reports');

        $this->assertSame('2', $kpi['value']);
    }

    public function test_farmer_kpis_are_scoped_to_the_signed_in_farmer(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        $other = User::factory()->create(['role' => User::ROLE_FARMER]);
        UserNotification::factory()->create(['user_id' => $farmer->id, 'is_read' => false, 'type' => 'Warning']);
        UserNotification::factory()->count(3)->create(['user_id' => $other->id, 'is_read' => false]);
        $this->rice('Binubusan', 2024, 10, 50);
        $this->rice('Lumaniag', 2024, 2, 4);

        $kpis = app(DashboardKpiService::class)->farmer($farmer, 'Binubusan', ['risk_level' => 'High']);

        $alerts = $this->kpi($kpis, 'alerts');
        $this->assertSame('1', $alerts['value']);
        $this->assertSame('warning', $alerts['status']);
        $this->assertSame('5.00', $this->kpi($kpis, 'barangay-yield')['value']);
        $this->assertSame('High', $this->kpi($kpis, 'farm-risk')['value']);
    }

    public function test_it_risk_freshness_reports_failures_recorded_today(): void
    {
        HeatmapArea::query()->delete(); // no barangay refreshed recently
        Cache::put('metrics:risk-map:failures:'.\App\Support\BusinessClock::today()->toDateString(), 2, now()->addDay());

        $kpi = $this->kpi(app(DashboardKpiService::class)->itExpert(), 'risk-freshness');

        $this->assertStringContainsString('2 failures today', $kpi['note']);
        $this->assertSame('critical', $kpi['status']);
    }

    public function test_each_role_dashboard_renders_its_kpi_strip(): void
    {
        $this->rice('Binubusan', 2024, 10, 50);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        // Farmer Home (2026-10): five plain parts instead of a KPI strip, in reading order.
        Http::fake(['*' => Http::response([], 500)]);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['data-fh-message', 'data-fh-weather', 'data-fh-risk', 'data-fh-harvest', 'data-fh-week'], false)
            ->assertDontSee('My Farm Today')
            ->assertDontSee('data-kpi="users"', false)
            // With nothing saved and the weather source down: "No data yet", never a made-up 0 or an error.
            ->assertSee('No data yet');

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->get(route('mao.operations', ['year' => 2024]))
            ->assertOk()
            ->assertSee('Agriculture at a Glance')
            ->assertSee('data-kpi="yield"', false)
            ->assertSee('Total Rice Production (2024)');

        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $this->actingAs($it)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('IT Personnel Dashboard')
            ->assertSee('data-kpi="total-users"', false)
            ->assertSee('data-kpi="active-users"', false)
            ->assertSee('data-kpi="status"', false)
            ->assertDontSee('data-kpi="prediction"', false)          // prediction diagnostics live on System Health, not the overview
            ->assertDontSee('data-kpi="historical-yield"', false);

        // The MAO overview has its own agriculture-focused KPI strip.
        $this->actingAs($mao)->get(route('mao.dashboard'))
            ->assertOk()
            ->assertSee('data-mao-kpis', false)
            ->assertDontSee('data-kpi="users"', false)
            ->assertDontSee('Agriculture at a Glance');
    }
}
