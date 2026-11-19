<?php
namespace Tests\Feature;

use App\Models\{AssociationChairmanAssignment, AssociationReportingPeriod, AssociationYieldReport, FarmerOrganization, Notification, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssociationReminderApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_newly_approved_chairman_receives_scheduled_and_manual_reminders_once(): void
    {
        $chairman = User::factory()->farmer()->create(['status' => User::STATUS_APPROVED]);
        $mao = User::factory()->maoHead()->create();
        $association = FarmerOrganization::create(['name' => 'Verification association', 'barangay' => 'Prenza']);
        AssociationChairmanAssignment::create(['farmer_organization_id' => $association->id, 'user_id' => $chairman->id, 'status' => 'active']);
        $period = AssociationReportingPeriod::create(['period_key' => 'verification', 'label' => 'Verification period', 'reporting_year' => 2026, 'due_at' => now()->subDays(2), 'required' => true]);
        $this->artisan('associations:send-reminders')->assertSuccessful();
        $this->artisan('associations:send-reminders')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $chairman->id)->where('category', 'association_reporting')->count());
        $report = AssociationYieldReport::create(['farmer_organization_id' => $association->id, 'reporting_period_id' => $period->id, 'status' => 'draft']);
        $url = route('mao.association-reports.reports.remind', $report);
        $this->actingAs($mao)->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->assertSame(2, Notification::where('user_id', $chairman->id)->where('category', 'association_reporting')->count());
        $chairman->update(['status' => User::STATUS_REJECTED]);
        $this->post($url)->assertUnprocessable();
        $this->travel(1)->days();
        $this->artisan('associations:send-reminders')->assertSuccessful();
        $this->assertSame(2, Notification::where('user_id', $chairman->id)->where('category', 'association_reporting')->count());
    }
}
