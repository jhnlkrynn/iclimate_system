<?php

namespace Tests\Feature;

use App\Models\AssociationChairmanAssignment;
use App\Models\AssociationReportingPeriod;
use App\Models\AssociationYieldReport;
use App\Models\FarmerOrganization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit 2026-11-18: the MAO list showed only the latest deadline, so an overdue period's reports could not be opened. */
class MaoAssociationReportPeriodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mao_can_open_any_period_including_an_overdue_one(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $chairman = User::factory()->farmer()->create(['name' => 'Chairman Dela Cruz']);
        $association = FarmerOrganization::query()->create(['name' => 'Prenza Irrigators', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated']);
        AssociationChairmanAssignment::query()->create(['farmer_organization_id' => $association->id, 'user_id' => $chairman->id, 'status' => 'active']);
        $overdue = AssociationReportingPeriod::query()->create(['period_key' => 'ws-2026', 'label' => 'Wet season 2026', 'reporting_year' => 2026, 'due_at' => now()->subDays(3)]);
        $upcoming = AssociationReportingPeriod::query()->create(['period_key' => 'ds-2027', 'label' => 'Dry season 2027', 'reporting_year' => 2027, 'due_at' => now()->addDays(20)]);
        $report = AssociationYieldReport::query()->create(['farmer_organization_id' => $association->id, 'reporting_period_id' => $overdue->id, 'status' => AssociationYieldReport::SUBMITTED]);

        $this->actingAs($mao)->get('/mao/association-reports')->assertOk()
            ->assertSee('Dry season 2027')->assertSee('Wet season 2026')->assertSee('(overdue)')
            ->assertSee('Not Submitted')->assertDontSee(route('mao.association-reports.reports.show', $report))
            ->assertSee('Chairman Dela Cruz');

        $this->actingAs($mao)->get('/mao/association-reports?period='.$overdue->id)->assertOk()
            ->assertSee(route('mao.association-reports.reports.show', $report))->assertSee('submitted');

        $this->actingAs($mao)->get('/mao/association-reports?period=999999')->assertOk()->assertSee('Dry season 2027');
        $this->assertNotNull($upcoming);
    }
}
