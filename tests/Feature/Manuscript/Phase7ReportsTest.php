<?php

namespace Tests\Feature\Manuscript;

use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/** Manuscript – Naicel (2026-10-08), Phase 7: new report types, Draft / Published with versions, Excel / PDF, My Reports. */
class Phase7ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO]);
    }

    public function test_seasonal_production_report_is_built_at_once_as_a_versioned_draft(): void
    {
        $mao = $this->mao();
        RiceProduction::query()->create(['barangay' => 'Balibago', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'year' => 2024, 'production_month' => '2024-10', 'area_hectares' => 100, 'area_harvested_hectares' => 95, 'total_production' => 420, 'yield_per_hectare' => 4.4]);
        RiceProduction::query()->create(['barangay' => 'Balibago', 'season' => 'Wet', 'irrigation_type' => 'Rainfed', 'year' => 2024, 'production_month' => '2024-10', 'area_hectares' => 50, 'area_harvested_hectares' => 50, 'total_production' => 180, 'yield_per_hectare' => 3.6]);

        $this->actingAs($mao)->post(route('reports.generate'), ['report_type' => 'seasonal_production', 'year' => 2024])->assertRedirect();
        $report = Report::query()->sole();
        $this->assertSame('Seasonal Production Report', $report->report_type);
        $this->assertSame(Report::STATUS_DRAFT, $report->status);
        $this->assertSame(1, $report->version);
        $row = $report->savedRecords()[0];
        $this->assertEquals(150, $row['area_planted']);
        $this->assertEquals(600, $row['production_mt']);
        $this->assertEquals(4.0, $row['yield_t_ha']);

        $this->actingAs($mao)->get(route('reports.show', $report))->assertOk()->assertSee('Draft · v1')->assertSee('Download Excel');

        // The same type and filters again: version 2 of the same report.
        $this->actingAs($mao)->post(route('reports.generate'), ['report_type' => 'seasonal_production', 'year' => 2024]);
        $this->assertSame(2, Report::query()->latest('id')->first()->version);
    }

    public function test_the_other_new_report_types_build(): void
    {
        $mao = $this->mao();
        YieldPredictionRequest::query()->create(['user_id' => $mao->id, 'scope' => 'municipal', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'area_hectares' => 100, 'target_month' => '2026-09-01', 'status' => 'done', 'predicted_yield' => 4.2, 'total_tons' => 420, 'historical_average' => 4.0, 'impact_level' => 'Normal']);

        foreach (['yield_prediction' => 1, 'climate_trend' => null, 'barangay_risk' => 19] as $type => $rows) {
            $this->actingAs($mao)->post(route('reports.generate'), ['report_type' => $type])->assertRedirect();
            $report = Report::query()->latest('id')->first();
            $this->assertSame($type, $report->typeKey());
            if ($rows !== null) {
                $this->assertCount($rows, $report->savedRecords(), $type);
            } else {
                $this->assertNotEmpty($report->savedRecords());
            }
            $this->assertNotEmpty($report->summary(), $type);
        }
    }

    public function test_mao_publishes_a_report_and_downloads_excel_and_pdf(): void
    {
        $mao = $this->mao();
        $this->actingAs($mao)->post(route('reports.generate'), ['report_type' => 'barangay_risk']);
        $report = Report::query()->sole();

        $this->actingAs($mao)->post(route('reports.publish', $report), ['publish' => 1])->assertRedirect(route('reports.show', $report));
        $this->assertSame(Report::STATUS_PUBLISHED, $report->fresh()->status);
        $this->assertNotNull($report->fresh()->published_at);

        $xlsx = $this->actingAs($mao)->get(route('reports.export.xlsx', ['report' => $report->id]));
        $xlsx->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($xlsx->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Barangay', $sheet);
        $this->assertStringContainsString('Balibago', $sheet);
        $zip->close();

        $pdf = $this->actingAs($mao)->get(route('reports.export.pdf', ['report' => $report->id]));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_only_mao_exports_and_publishes_reports(): void
    {
        $this->actingAs($this->mao())->post(route('reports.generate'), ['report_type' => 'barangay_risk']);
        $report = Report::query()->sole();
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->get(route('reports.export.pdf', ['report' => $report->id]))->assertStatus(403);
        // IT is sent back to the IT dashboard from agricultural pages (RedirectItFromFieldPages) or refused: never allowed.
        $it = $this->actingAs(User::factory()->create(['role' => User::ROLE_IT_EXPERT]))->post(route('reports.publish', $report), ['publish' => 1]);
        $this->assertContains($it->status(), [303, 403]);
        $this->assertSame(Report::STATUS_DRAFT, $report->fresh()->status);
    }

    public function test_a_farmer_reads_and_downloads_my_reports(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);
        YieldPredictionRequest::query()->create(['user_id' => $farmer->id, 'scope' => 'farm', 'barangay' => 'Balibago', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'area_hectares' => 2, 'target_month' => '2026-09-01', 'status' => 'done', 'predicted_yield' => 4.5, 'total_tons' => 9, 'historical_average' => 4.0, 'impact_level' => 'Favorable']);
        PlantingAdvisory::query()->create(['title' => 'Plant after the first heavy rains', 'content' => 'x', 'message' => 'x', 'type' => 'planting', 'advisory_type' => 'planting', 'severity' => 'moderate', 'target_barangay' => 'Balibago', 'status' => 'published', 'published_at' => now(), 'recommended_action' => 'Prepare seedbeds.', 'posted_by' => User::factory()->create(['role' => User::ROLE_MAO])->id]);
        Notification::query()->create(['user_id' => $farmer->id, 'title' => 'New advisory', 'message' => 'm', 'type' => 'Advisory', 'is_read' => true]);

        $this->actingAs($farmer)->get(route('farmer.reports.index'))->assertOk()
            ->assertSee('My Reports')->assertSee('9 t')->assertSee('Plant after the first heavy rains')->assertSee('Alerts you received: 1 (read: 1)');
        $pdf = $this->actingAs($farmer)->get(route('farmer.reports.pdf'));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertSee('data-my-reports-link', false);
    }

    public function test_my_reports_is_in_filipino_for_filipino_farmers_and_farmers_only(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'preferred_language' => 'fil']);
        $this->actingAs($farmer)->get(route('farmer.reports.index'))->assertOk()->assertSee('Aking mga Ulat');
        $this->actingAs($this->mao())->get(route('farmer.reports.index'))->assertStatus(403);
    }
}
