<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Reports\ManuscriptReportBuilder;
use App\Support\EvaluationEvidence;
use App\Support\RecordProvenance;
use App\Support\YieldCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3 display safeguards (docs/data-audit/phase-3-ml-dataset-methodology.md, decision 6): demo data, copied
 * observations and too-small samples are never shown as model accuracy. Model Evaluation says "Insufficient verified
 * data" instead, and demo harvests are left out of every predicted-against-actual figure.
 */
class EvaluationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00:00', 'Asia/Manila'));
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function rice(array $attributes): RiceProduction
    {
        $row = RiceProduction::query()->create($attributes + ['barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Irrigated',
            'yield_per_hectare' => 4.0, 'area_hectares' => 10, 'total_production' => 40, 'year' => 2024]);
        if (array_key_exists('verified_at', $attributes)) {
            $row->forceFill(['verified_at' => $attributes['verified_at']])->save();
        }

        return $row;
    }

    /** One season total split across barangays, the way the demo seeder writes it: the same yield in every row. */
    private function allocatedSeason(int $year, string $season, float $yield, int $barangays = 10, bool $validated = false): void
    {
        foreach (range(1, $barangays) as $i) {
            $this->rice(['barangay' => 'Barangay '.$i, 'year' => $year, 'season' => $season, 'area_hectares' => 5 * $i,
                // Rounding leaves a 0.01 spread between the copies, as in the saved rows.
                'yield_per_hectare' => $yield + ($i % 2 ? 0 : 0.01), 'remarks' => 'Entered by MAO.',
                'verified_at' => $validated ? now() : null]);
        }
    }

    private function harvest(float $predicted, float $actual, array $attributes = [], ?User $farmer = null): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($attributes + ['user_id' => ($farmer ?? User::factory()->farmer()->create())->id,
            'planting_date' => '2026-05-10', 'scheduled_harvest_date' => '2026-08-28', 'harvested_on' => '2026-09-01', 'season' => 'Wet',
            'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => 1, 'predicted_yield' => $predicted, 'actual_yield' => $actual]);
    }

    private function storedRun(array $evaluation): AnalyticsRun
    {
        return AnalyticsRun::query()->forceCreate(['type' => 'dataset_processing', 'status' => AnalyticsRun::STATUS_COMPLETED,
            'input' => ['schema' => 'rice_yield_v1'], 'result' => ['evaluation' => $evaluation], 'completed_at' => now(), 'created_at' => now()]);
    }

    private function computed(int $samples, array $extra = []): array
    {
        return $extra + ['ready' => true, 'sample_count' => $samples, 'best_algorithm' => 'Random Forest', 'source' => ['computed_live' => true],
            'rows' => [
                ['algorithm' => 'Multiple Linear Regression', 'rmse' => 0.2271, 'mae' => 0.1951, 'r2' => 0.8735],
                ['algorithm' => 'Random Forest', 'rmse' => 0.0074, 'mae' => 0.0044, 'r2' => 0.999912],
            ]];
    }

    // ------------------------------------------------------------ sample eligibility and duplicate detection

    public function test_rice_records_are_classed_by_provenance_not_by_existing(): void
    {
        $verified = $this->rice(['year' => 2023, 'yield_per_hectare' => 4.10, 'verified_at' => now()]);
        $unverified = $this->rice(['year' => 2023, 'barangay' => 'Kapito', 'yield_per_hectare' => 3.62]);
        $demo = $this->rice(['year' => 2025, 'remarks' => '[demo_seed] Aggregated from demo farmer schedules.', 'verified_at' => now()]);
        $allocated = $this->rice(['year' => 2022, 'remarks' => 'Official MAO planting summary 2020-2025; no special remark.']);

        $classes = RecordProvenance::classifyRice(RiceProduction::query()->get());

        $this->assertSame(RecordProvenance::VERIFIED, $classes[$verified->id]);
        $this->assertSame(RecordProvenance::UNVERIFIED, $classes[$unverified->id]);
        $this->assertSame(RecordProvenance::DEMO, $classes[$demo->id], 'a validation stamp does not make a demo row real');
        $this->assertSame(RecordProvenance::DERIVED, $classes[$allocated->id]);
    }

    public function test_one_yield_repeated_across_a_season_is_detected_as_copies_even_when_validated(): void
    {
        $this->allocatedSeason(2024, 'Wet', 1.90, validated: true);
        // Two barangays that happen to share a yield are not copies; three or more are.
        $pairA = $this->rice(['year' => 2021, 'barangay' => 'Luyahan', 'yield_per_hectare' => 3.85, 'verified_at' => now()]);
        $pairB = $this->rice(['year' => 2021, 'barangay' => 'Kapito', 'yield_per_hectare' => 3.85, 'verified_at' => now()]);
        // The same yield in another season is a different observation.
        $other = $this->rice(['year' => 2020, 'yield_per_hectare' => 1.90, 'verified_at' => now()]);

        $summary = RecordProvenance::riceSummary();

        $this->assertSame(10, $summary['derived']);
        $this->assertSame(3, $summary['verified']);
        $this->assertSame(3, $summary['seasons']);
        $classes = RecordProvenance::classifyRice(RiceProduction::query()->get());
        foreach ([$pairA, $pairB, $other] as $row) {
            $this->assertSame(RecordProvenance::VERIFIED, $classes[$row->id]);
        }
    }

    public function test_evidence_needs_enough_verified_records_a_grouped_test_and_lian_climate_observations(): void
    {
        config(['iclimate.model_evaluation_min_observations' => 4]);
        foreach ([[2021, 'Wet', 3.8], [2021, 'Dry', 4.2], [2022, 'Wet', 3.4], [2022, 'Dry', 4.35]] as [$year, $season, $yield]) {
            $this->rice(['year' => $year, 'season' => $season, 'yield_per_hectare' => $yield, 'verified_at' => now()]);
        }
        $grouped = ['validation' => ['method' => 'leave_one_season_out']];

        // No climate observations yet.
        $evidence = EvaluationEvidence::assess($this->computed(4, $grouped));
        $this->assertFalse($evidence['verified']);
        $this->assertContains('No Lian climate observations are on record.', $evidence['reasons']);

        ClimateRecord::query()->create(['record_date' => '2022-06-15', 'rainfall' => 12, 'temperature' => 28, 'humidity' => 80, 'wind_speed' => 9, 'season' => 'Wet', 'source' => 'MAO']);

        $this->assertTrue(EvaluationEvidence::assess($this->computed(4, $grouped))['verified']);
        // A random row split, or more rows than there are verified records, is not accepted.
        $this->assertFalse(EvaluationEvidence::assess($this->computed(4))['verified']);
        $this->assertFalse(EvaluationEvidence::assess($this->computed(40, $grouped))['verified']);
        // Fewer verified records than the minimum.
        config(['iclimate.model_evaluation_min_observations' => 30]);
        $short = EvaluationEvidence::assess($this->computed(4, $grouped));
        $this->assertFalse($short['verified']);
        $this->assertSame(EvaluationEvidence::HEADLINE, $short['headline']);
        $this->assertContains('Only 4 validated, separately measured rice records exist. At least 30 are needed.', $short['reasons']);
    }

    // ------------------------------------------------------------ Model Evaluation page







    // ------------------------------------------------------------ demo harvests

    public function test_demo_harvests_are_left_out_of_the_predicted_against_actual_figures(): void
    {
        $demoFarmer = User::factory()->farmer()->create(['email' => 'farmer0001@'.RecordProvenance::DEMO_EMAIL_DOMAIN]);
        $this->harvest(9.15, 9.03, ['prediction_source' => RecordProvenance::DEMO_MARKER]);            // marked on the record
        $this->harvest(9.15, 9.03, ['notes' => '[demo_seed] Variety: Rc 222.']);                       // marked in the notes
        $this->harvest(9.15, 9.03, [], $demoFarmer);                                                   // owned by a demo account
        $this->assertSame(0, YieldCorrections::summary()['count']);
        $this->assertNull(YieldCorrections::summary()['error']);

        $this->harvest(4.0, 5.0, ['prediction_source' => 'iClimate Rice Harvest Estimate']);
        $this->harvest(4.0, 3.5);

        $summary = YieldCorrections::summary();
        $this->assertSame(2, $summary['count']);
        $this->assertSame(0.75, $summary['error'], 'the average miss is of the real harvests only');
        $this->assertSame(5, FarmerPlantingSchedule::query()->count(), 'no record is removed');
    }


}
