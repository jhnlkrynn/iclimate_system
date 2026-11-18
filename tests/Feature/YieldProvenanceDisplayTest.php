<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Analytics\MaoYieldAnalytics;
use App\Support\RecordProvenance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rice-production figures are shown under what they are (docs/data-audit/phase-3-ml-dataset-methodology.md): a season
 * total split across barangays is not a barangay's actual yield, demo records are not used, and stored model results
 * carry a note of where their "recorded" side comes from.
 */
class YieldProvenanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function rice(string $barangay, int $year, float $yield, array $more = []): RiceProduction
    {
        $row = RiceProduction::query()->create($more + ['barangay' => $barangay, 'season' => 'Wet', 'irrigation_type' => 'Irrigated',
            'yield_per_hectare' => $yield, 'area_hectares' => 10, 'total_production' => round($yield * 10, 2), 'year' => $year, 'remarks' => 'Entered by MAO.']);
        if (! empty($more['verified_at'])) {
            $row->forceFill(['verified_at' => $more['verified_at']])->save();
        }

        return $row;
    }

    private function forecast(User $by, string $barangay): YieldPredictionRequest
    {
        return YieldPredictionRequest::query()->create(['user_id' => $by->id, 'scope' => YieldPredictionRequest::SCOPE_BARANGAY, 'barangay' => $barangay,
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'area_hectares' => 10, 'target_month' => '2024-08-01', 'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => 3.9, 'total_tons' => 39, 'completed_at' => now()]);
    }

    private function tile(string $html): string
    {
        $this->assertSame(1, preg_match('#<a class="mhd-tile"[^>]*data-kpi="production">(.*?)</a>#s', $html, $m));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    private function dashboard(User $as): string
    {
        Cache::flush();

        return $this->actingAs($as)->get(route('mao.dashboard'))->assertOk()->getContent();
    }

    // ------------------------------------------------------------ Yield Forecast: the "Actual" column

    public function test_yield_forecast_only_calls_a_validated_separate_record_an_actual_yield(): void
    {
        $mao = $this->mao();
        // One season total split across barangays: the same yield everywhere.
        foreach (['Prenza', 'Bungahan', 'Binubusan', 'Malaruhatan'] as $barangay) {
            $this->rice($barangay, 2024, 1.90, ['remarks' => 'Official MAO planting summary 2020-2025; Typhoon Kristine losses.']);
        }
        $this->rice('Kapito', 2024, 3.75, ['verified_at' => now()]);
        $this->rice('Luyahan', 2024, 3.20);
        $this->rice('Balibago', 2024, 3.56, ['remarks' => '[demo_seed] Aggregated from demo farmer schedules.', 'verified_at' => now()]);
        foreach (['Prenza', 'Kapito', 'Luyahan', 'Balibago'] as $barangay) {
            $this->forecast($mao, $barangay);
        }

        $html = $this->actingAs($mao)->get(route('analysis.forecast'))->assertOk()->getContent();
        $cell = function (string $provenance) use ($html): string {
            $this->assertSame(1, preg_match_all('#<td data-actual-provenance="'.$provenance.'">(.*?)</td>#s', $html, $m), $provenance);

            return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1][0])));
        };

        $this->assertSame('3.75 t/ha', $cell('verified'));
        $this->assertSame('No verified actual yield available Derived municipal allocation: 1.9 t/ha. A season total split across barangays, not a barangay measurement.', $cell('derived'));
        $this->assertSame('No verified actual yield available Unverified MAO record: 3.2 t/ha. Not yet validated.', $cell('unverified'));
        // The only record for Balibago is demo data: it is not shown at all, validated stamp or not.
        $this->assertSame('No verified actual yield available', $cell('none'));
        $this->assertStringNotContainsString('3.56', $html);
        $this->assertStringNotContainsString('Not recorded yet', $html);
    }

    // ------------------------------------------------------------ MAO dashboard: the rice production tile

    public function test_mao_production_tile_leaves_demo_records_out_and_names_split_season_totals(): void
    {
        foreach (['Prenza', 'Bungahan', 'Binubusan'] as $barangay) {
            $this->rice($barangay, 2026, 4.0, ['remarks' => 'Official MAO planting summary 2020-2025; no special remark.']);
        }
        $this->rice('Balibago', 2026, 9.0, ['total_production' => 999, 'remarks' => '[demo_seed] Aggregated from demo farmer schedules.']);

        $tile = $this->tile($this->dashboard($this->mao()));

        $this->assertStringStartsWith('Rice production (2026) 120.0 tons', $tile);          // 3 x 40 t; the 999 demo tons are not added
        $this->assertStringContainsString('30.0 ha · 4.00 t/ha · municipal season totals split across barangays, not barangay reports', $tile);
        $this->assertStringNotContainsString('barangays reported', $tile);
    }

    public function test_mao_production_tile_says_when_barangay_reports_are_not_validated(): void
    {
        $prenza = $this->rice('Prenza', 2026, 4.2);
        $kapito = $this->rice('Kapito', 2026, 3.6);

        $this->assertStringContainsString('barangays reported · not yet validated', $this->tile($this->dashboard($this->mao())));

        foreach ([$prenza, $kapito] as $row) {
            $row->forceFill(['verified_at' => now()])->save();
        }
        $tile = $this->tile($this->dashboard($this->mao()));
        $this->assertStringContainsString('20.0 ha · 3.90 t/ha · 2 of '.count(\App\Support\LianBarangays::all()).' barangays reported', $tile);
        $this->assertStringNotContainsString('not yet validated', $tile);
    }

    // ------------------------------------------------------------ MAO dashboard: predicted against recorded

    public function test_barangay_comparison_ignores_demo_harvests_and_the_backtest_is_marked_unverified(): void
    {
        $harvest = fn (array $more = []) => FarmerPlantingSchedule::query()->create($more + ['user_id' => User::factory()->farmer()->create()->id,
            'planting_date' => '2026-05-10', 'scheduled_harvest_date' => '2026-08-28', 'harvested_on' => '2026-09-01', 'season' => 'Wet', 'farm_type' => 'Irrigated',
            'barangay' => 'Prenza', 'farm_area_hectares' => 1, 'predicted_yield' => 9.15, 'actual_yield' => 9.03]);
        $harvest(['prediction_source' => RecordProvenance::DEMO_MARKER]);
        $harvest(['notes' => '[demo_seed] Variety: Rc 222.', 'barangay' => 'Kapito']);

        $panel = app(MaoYieldAnalytics::class)->predictedActual();
        $this->assertSame([], $panel['barangays']);

        $harvest(['predicted_yield' => 4.0, 'actual_yield' => 5.0, 'barangay' => 'Luyahan']);
        $rows = app(MaoYieldAnalytics::class)->predictedActual()['barangays'];
        $this->assertSame(['Luyahan'], array_column($rows, 'barangay'));
        $this->assertSame(1, $rows[0]['count']);

        // The stored backtest stays on the page, with what it is and a plain guess to compare it with.
        $this->assertSame('ok', $panel['state'], 'the backtest file is part of the project');
        $this->assertNotNull($panel['baseline']);
        $this->assertSame($panel['test']['count'], $panel['baseline']['count']);
        $this->assertSame($panel['test']['actual'], $panel['baseline']['actual'], 'the same held-out months');
        $html = $this->dashboard($this->mao());
        $this->assertSame(1, preg_match('#<p class="mtd-sub" data-accuracy-provenance>(.*?)</p>#s', $html, $m));
        $note = trim((string) preg_replace('/\s+/', ' ', html_entity_decode($m[1])));
        $this->assertStringStartsWith('Unverified backtest. The recorded yields are municipal monthly averages from the MAO planting summary and are not yet validated. '
            .$panel['train']['count'].' of '.$panel['overall']['count'].' months were used to train the model', $note);
        $this->assertStringContainsString('always guessing the average yield gives an error (MAPE) of '.rtrim(rtrim(number_format($panel['baseline']['mape'], 1), '0'), '.').'%', $note);
    }

    // ------------------------------------------------------------ IT: Model Management


}
