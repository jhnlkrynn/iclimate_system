<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: list filters declared as plain lists (['Wet', 'Dry'], LianBarangays::all()) were rendered with the list
 * position as the option value, so choosing one submitted "?barangay=0" and every filtered list came back empty.
 */
class CrudListFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_filters_submit_and_match_the_stored_value(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        FarmerProfile::factory()->create(['full_name' => 'Ana Pob Two', 'barangay' => 'Barangay 2 (Pob.)']);
        FarmerProfile::factory()->create(['full_name' => 'Ben Prenza', 'barangay' => 'Prenza']);

        $this->actingAs($mao)->get(route('farmer-profiles.index'))
            ->assertSee('<option value="Barangay 2 (Pob.)"', false)
            ->assertDontSee('<option value="0"', false);

        $this->actingAs($mao)->get(route('farmer-profiles.index', ['barangay' => 'Barangay 2 (Pob.)']))
            ->assertOk()->assertSee('Ana Pob Two')->assertDontSee('Ben Prenza');
    }

    public function test_status_filters_on_staff_lists_work(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        foreach ([FieldReport::STATUS_PENDING => 'Open drainage issue', FieldReport::STATUS_RESOLVED => 'Fixed canal'] as $status => $title) {
            FieldReport::query()->create(['title' => $title, 'barangay' => 'Binubusan', 'issue_type' => 'Flooding', 'status' => $status, 'reported_by' => $mao->id]);
        }

        $this->actingAs($mao)->get(route('field-reports.index', ['status' => FieldReport::STATUS_RESOLVED]))
            ->assertOk()->assertSee('Fixed canal')->assertDontSee('Open drainage issue');
    }

    public function test_a_filter_keyed_by_its_stored_value_keeps_those_keys(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        // notifications.is_read is stored as 0 / 1, so its options stay keyed 0 => Unread, 1 => Read.
        $this->actingAs($it)->get(route('notifications.index'))->assertOk()
            ->assertSee('<option value="0" >Unread</option>', false);
    }
}
