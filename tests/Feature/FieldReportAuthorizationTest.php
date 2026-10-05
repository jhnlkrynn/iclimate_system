<?php

namespace Tests\Feature;

use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Field reports are internal MAO operations records: staff only, enforced on the route, not just hidden from the menu. */
class FieldReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function report(): FieldReport
    {
        return FieldReport::query()->create([
            'title' => 'Stem borer in lower paddies', 'barangay' => 'Binubusan', 'issue_type' => 'Pest/Disease',
            'description' => 'Reported by the barangay technician.', 'status' => FieldReport::STATUS_PENDING,
            'reported_by' => User::factory()->create(['role' => User::ROLE_MAO])->id,
        ]);
    }

    public function test_a_farmer_typing_any_field_report_url_is_denied(): void
    {
        $report = $this->report();
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->get(route('field-reports.index'))->assertForbidden();
        $this->actingAs($farmer)->get(route('field-reports.show', $report))->assertForbidden();
        $this->actingAs($farmer)->get(route('field-reports.create'))->assertForbidden();
        $this->actingAs($farmer)->get(route('field-reports.edit', $report))->assertForbidden();
        $this->actingAs($farmer)->post(route('field-reports.store'), ['title' => 'x'])->assertForbidden();
        $this->actingAs($farmer)->delete(route('field-reports.destroy', $report))->assertForbidden();

        $this->assertDatabaseHas('field_reports', ['id' => $report->id]);
    }

    public function test_mao_and_it_personnel_keep_full_access(): void
    {
        $report = $this->report();

        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
            $staff = User::factory()->create(['role' => $role]);

            $this->actingAs($staff)->get(route('field-reports.index'))->assertOk()->assertSee('Stem borer in lower paddies');
            $this->actingAs($staff)->get(route('field-reports.show', $report))->assertOk();
            $this->actingAs($staff)->get(route('field-reports.create'))->assertOk();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('field-reports.index'))->assertRedirect(route('login'));
    }
}
