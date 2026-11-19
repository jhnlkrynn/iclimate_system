<?php
namespace Tests\Feature;

use App\Models\{AssociationChairmanAssignment, AssociationReportingPeriod, AssociationYieldReport, FarmerOrganization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssociationReportIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function setupReport(): array
    {
        $mao=User::factory()->maoHead()->create();
        $chair=User::factory()->farmer()->create(['status'=>User::STATUS_APPROVED]);
        $association=FarmerOrganization::create(['name'=>'Integrity association','barangay'=>'Prenza']);
        AssociationChairmanAssignment::create(['farmer_organization_id'=>$association->id,'user_id'=>$chair->id,'status'=>'active']);
        $period=AssociationReportingPeriod::create(['period_key'=>'integrity','label'=>'Integrity period','reporting_year'=>2026,'due_at'=>now()->addWeek()]);
        return [$mao,$chair,$association,$period];
    }

    public function test_clearing_production_does_not_retain_a_stale_calculated_yield(): void
    {
        [$mao,$chair,$association,$period]=$this->setupReport();
        $url=route('association-chairman.reports.store',$period);
        $this->actingAs($chair)->post($url,['action'=>'draft','production_tons'=>8,'area_harvested_hectares'=>2])->assertRedirect();
        $this->post($url,['action'=>'draft','production_tons'=>null,'area_harvested_hectares'=>null])->assertRedirect();
        $this->assertNull(AssociationYieldReport::sole()->average_yield_t_per_hectare);
    }

    public function test_draft_cannot_be_accepted_and_repeated_review_does_not_duplicate_history(): void
    {
        [$mao,$chair,$association,$period]=$this->setupReport();
        $report=AssociationYieldReport::create(['farmer_organization_id'=>$association->id,'reporting_period_id'=>$period->id,'status'=>'draft','submitted_by'=>$chair->id]);
        $url=route('mao.association-reports.reports.review',$report);
        $this->actingAs($mao)->postJson($url,['action'=>'accept','notes'=>'Verified'])->assertStatus(409);
        $report->update(['status'=>'submitted']);
        $this->post($url,['action'=>'accept','notes'=>'Verified'])->assertRedirect();
        $this->post($url,['action'=>'accept','notes'=>'Verified'])->assertRedirect();
        $this->assertSame(1,$report->reviews()->count());
        $this->assertDatabaseCount('notifications',1);
        $this->postJson($url,['action'=>'needs_revision','notes'=>'Stale conflicting review'])->assertStatus(409);
    }

    public function test_revision_form_preserves_dates_and_irrigation_selection(): void
    {
        [$mao,$chair,$association,$period]=$this->setupReport();
        AssociationYieldReport::create(['farmer_organization_id'=>$association->id,'reporting_period_id'=>$period->id,'status'=>'needs_revision','planting_date'=>'2026-05-01','harvest_date'=>'2026-09-01','irrigation_type'=>'Rainfed']);
        $this->actingAs($chair)->get(route('association-chairman.reports.create',$period))->assertOk()
            ->assertSee('value="2026-05-01"',false)->assertSee('value="2026-09-01"',false)->assertSee('value="Rainfed" selected',false);
    }
    public function test_submission_snapshots_and_supporting_documents_remain_private_and_preserved(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$mao,$chair,$association,$period]=$this->setupReport();
        $url=route('association-chairman.reports.store',$period);
        $this->actingAs($chair)->post($url,['action'=>'submit','production_tons'=>8,'area_harvested_hectares'=>2,
            'remarks'=>'Original observed totals', 'supporting_document'=>\Illuminate\Http\UploadedFile::fake()->image('evidence.png')])->assertRedirect();
        $report=AssociationYieldReport::sole();$first=$report->submissions()->sole();
        $this->assertSame('8.00',$first->snapshot['production_tons']);
        $this->get(route('association-reports.document',[$report,$first->id]))->assertOk()->assertDownload();
        $review=route('mao.association-reports.reports.review',$report);
        $this->actingAs($mao)->post($review,['action'=>'needs_revision','notes'=>'Correct area'])->assertRedirect();
        $this->actingAs($chair)->post($url,['action'=>'submit','production_tons'=>8,'area_harvested_hectares'=>4,'remarks'=>'Corrected observed totals'])->assertRedirect();
        $this->assertNull($report->fresh()->reviewed_at);
        $this->assertSame(2,$report->submissions()->count());
        $this->assertSame('Original observed totals',$first->fresh()->snapshot['remarks']);
        $this->get(route('association-chairman.reports.show',$report))->assertOk()->assertSee('Original observed totals')->assertSee('Corrected observed totals');
        $this->actingAs($mao)->get(route('association-reports.document',$report))->assertOk()->assertDownload();
        $other=User::factory()->farmer()->create();
        $this->actingAs($other)->get(route('association-reports.document',$report))->assertForbidden();
        $this->actingAs($chair)->post($url,['action'=>'submit','supporting_document'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('payload.php','<?php echo 1;')])->assertSessionHasErrors('supporting_document');
        $report->update(['supporting_document_path'=>'association-reports/../../.env']);
        $this->get(route('association-reports.document',$report))->assertNotFound();
        try {$first->update(['snapshot'=>['remarks'=>'tampered']]);$this->fail('Expected immutable history');}
        catch (\LogicException $exception) {$this->assertSame('Original observed totals',$first->fresh()->snapshot['remarks']);}
        try {$association->delete();$this->fail('Expected protected history');}
        catch (\Illuminate\Validation\ValidationException $exception) {$this->assertDatabaseHas('farmer_organizations',['id'=>$association->id]);}
    }

    public function test_history_migration_refuses_a_data_losing_rollback(): void
    {
        [$mao,$chair,$association,$period]=$this->setupReport();
        $this->actingAs($chair)->post(route('association-chairman.reports.store',$period),['action'=>'submit','remarks'=>'Preserve this submission'])->assertRedirect();
        $migration = require database_path('migrations/2026_11_18_000001_create_association_report_submissions.php');
        try {$migration->down();$this->fail('Expected data-safe rollback refusal');}
        catch (\RuntimeException $exception) {$this->assertStringContainsString('Submission history exists',$exception->getMessage());}
        $this->assertDatabaseCount('association_yield_report_submissions',1);
    }

}
