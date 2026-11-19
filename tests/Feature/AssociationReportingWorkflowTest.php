<?php
namespace Tests\Feature;

use App\Models\{AssociationChairmanAssignment, AssociationReportingPeriod, AssociationYieldReport, FarmerOrganization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssociationReportingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_period_key_returns_validation_error_instead_of_server_error(): void
    {
        $mao = User::factory()->maoHead()->create();
        $data = ['period_key' => '2026-wet', 'label' => 'Wet season 2026', 'reporting_year' => 2026, 'due_at' => now()->addWeek()->toDateTimeString()];
        $this->actingAs($mao)->postJson(route('mao.association-reports.periods.store'), $data)->assertRedirect();
        $this->postJson(route('mao.association-reports.periods.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('period_key');
        $this->assertDatabaseCount('association_reporting_periods', 1);
    }

    public function test_chairman_submission_revision_and_acceptance_preserve_review_history(): void
    {
        $mao = User::factory()->maoHead()->create();
        $chairman = User::factory()->farmer()->create(['status' => User::STATUS_APPROVED]);
        $association = FarmerOrganization::create(['name' => 'Workflow association', 'barangay' => 'Prenza']);
        AssociationChairmanAssignment::create(['farmer_organization_id' => $association->id, 'user_id' => $chairman->id, 'status' => 'active']);
        $period = AssociationReportingPeriod::create(['period_key' => 'workflow', 'label' => 'Workflow period', 'reporting_year' => 2026, 'due_at' => now()->addDays(5)]);
        $url = route('association-chairman.reports.store', $period);
        $data = ['action' => 'submit', 'production_tons' => 8, 'area_harvested_hectares' => 2, 'remarks' => 'First submission'];
        $this->actingAs($chairman)->post($url, $data)->assertRedirect();
        $report = AssociationYieldReport::sole();
        $this->assertSame('4.0000', $report->average_yield_t_per_hectare);
        $this->assertSame('submitted', $report->status);
        $review = route('mao.association-reports.reports.review', $report);
        $this->actingAs($mao)->post($review, ['action' => 'needs_revision'])->assertSessionHasErrors('notes');
        $this->post($review, ['action' => 'needs_revision', 'notes' => 'Correct harvested area'])->assertRedirect();
        $this->actingAs($chairman)->post($url, array_replace($data, ['area_harvested_hectares' => 4, 'remarks' => 'Corrected submission']))->assertRedirect();
        $this->actingAs($mao)->post($review, ['action' => 'accept', 'notes' => 'Verified with association'])->assertRedirect();
        $this->assertSame('accepted', $report->fresh()->status);
        $this->assertSame('2.0000', $report->fresh()->average_yield_t_per_hectare);
        $this->assertSame(['needs_revision', 'accept'], $report->reviews()->orderBy('id')->pluck('action')->all());
        $this->actingAs($chairman)->get(route('association-chairman.reports.show', $report))->assertOk()->assertSee('Correct harvested area')->assertSee('Verified with association');
        $this->post($url, $data)->assertForbidden();
        $other = User::factory()->farmer()->create();
        $this->actingAs($other)->get(route('association-chairman.reports.show', $report))->assertForbidden();
    }
}
