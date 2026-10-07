<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\MaoActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * MAO Planner and the MAO's Farmer Profiles pop-up (2026-10). The planner is a list of the MAO's activities sorted by
 * date with a read-only list of what farmers planted and harvested; Farmer Profiles shows five columns and opens the
 * details (and a conversation) in a pop-up.
 */
class MaoPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function mao(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
    }

    private function activity(User $mao, array $attributes = []): MaoActivity
    {
        return MaoActivity::query()->create($attributes + [
            'user_id' => $mao->id, 'activity_type' => 'Planting Event', 'barangay' => 'Balibago',
            'starts_on' => '2026-10-15', 'title' => 'Balibago planting day',
        ]);
    }

    public function test_planner_lists_activities_by_date_and_filters_by_barangay_and_type(): void
    {
        $mao = $this->mao();
        $this->activity($mao, ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-22', 'barangay' => null, 'activity_type' => 'Training', 'title' => 'Seed care training']);
        $this->activity($mao);
        $this->activity($mao, ['barangay' => 'Prenza', 'activity_type' => 'Meeting', 'starts_on' => '2026-10-01', 'title' => 'Prenza meeting']);

        $this->actingAs($mao)->get(route('mao.planner.index'))->assertOk()
            ->assertSee('MAO Planner')
            ->assertSeeInOrder(['Brgy. Prenza – Meeting – Oct 1', 'Brgy. Balibago – Planting Event – Oct 15', 'All Barangays – Training – Oct 20–22']);

        // A barangay's list keeps the activities meant for all barangays.
        $this->actingAs($mao)->get(route('mao.planner.index', ['barangay' => 'Balibago']))->assertOk()
            ->assertSee('Balibago planting day')->assertSee('Seed care training')->assertDontSee('Prenza meeting');

        $this->actingAs($mao)->get(route('mao.planner.index', ['type' => 'Meeting']))->assertOk()
            ->assertSee('Prenza meeting')->assertDontSee('Balibago planting day');
    }

    public function test_mao_adds_edits_and_deletes_only_their_own_activities(): void
    {
        $mao = $this->mao();
        $other = $this->mao();

        $this->actingAs($mao)->post(route('mao.planner.store'), [
            'activity_type' => 'Seed Distribution', 'barangay' => '', 'starts_on' => '2026-11-03', 'title' => 'Hybrid seed release', 'notes' => 'Bring your RSBSA card.',
        ])->assertRedirect(route('mao.planner.index'));
        $activity = MaoActivity::query()->sole();
        $this->assertNull($activity->barangay);
        $this->assertSame('All Barangays – Seed Distribution – Nov 3', $activity->summary());

        $this->actingAs($mao)->post(route('mao.planner.store'), ['activity_type' => 'Party', 'barangay' => 'Nowhere', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-01', 'title' => ''])
            ->assertSessionHasErrors(['activity_type', 'barangay', 'ends_on', 'title']);

        $change = ['activity_type' => 'Field Visit', 'barangay' => 'Prenza', 'starts_on' => '2026-11-05', 'title' => 'Field visit'];
        $this->actingAs($other)->put(route('mao.planner.update', $activity), $change)->assertForbidden();
        $this->actingAs($other)->delete(route('mao.planner.destroy', $activity))->assertForbidden();
        // Someone else's activity is shown without Edit or Delete.
        $this->actingAs($other)->get(route('mao.planner.index'))->assertOk()->assertSee('Hybrid seed release')->assertDontSee('Save changes');

        $this->actingAs($mao)->put(route('mao.planner.update', $activity), $change)->assertRedirect(route('mao.planner.index'));
        $this->assertSame('Brgy. Prenza – Field Visit – Nov 5', $activity->fresh()->summary());

        $this->actingAs($mao)->delete(route('mao.planner.destroy', $activity))->assertRedirect(route('mao.planner.index'));
        $this->assertSame(0, MaoActivity::query()->count());
    }

    public function test_farmer_activities_are_read_only_show_the_farm_and_filter_by_barangay(): void
    {
        $farmer = User::factory()->farmer()->create(['name' => 'Ana Reyes']);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North Farm', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated', 'farm_size_hectares' => 1.5, 'farm_size_method' => 'exact']);
        FarmerPlantingSchedule::query()->create(['user_id' => $farmer->id, 'farm_id' => $farm->id, 'planting_date' => '2026-06-10', 'scheduled_harvest_date' => '2026-09-28',
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => 1.5]);

        $this->actingAs($this->mao())->get(route('mao.planner.index'))->assertOk()
            ->assertSeeInOrder(['Farmer Activities', 'Planted', 'Ana Reyes', 'Prenza', 'North Farm', '1.5 ha']);

        $this->actingAs($this->mao())->get(route('mao.planner.index', ['farmer_barangay' => 'Balibago']))->assertOk()->assertDontSee('Ana Reyes');
    }

    public function test_page_and_menu_name_follow_the_saved_language_and_farmers_are_refused(): void
    {
        $fil = $this->mao(['preferred_language' => 'fil']);
        $this->actingAs($fil)->get(route('mao.planner.index'))->assertOk()->assertSee('Planner ng MAO');
        $this->actingAs($fil)->get(route('mao.dashboard'))->assertSee('Planner ng MAO')->assertSee(route('mao.planner.index'), false);

        $this->actingAs(User::factory()->farmer()->create())->get(route('mao.planner.index'))->assertForbidden();
    }

    public function test_farmer_profiles_table_and_pop_up_hide_contact_details_and_empty_fields(): void
    {
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id, 'full_name' => 'Ana Reyes', 'barangay' => 'Prenza',
            'contact_number' => '09171234567', 'address' => '12 Mabini Street', 'farm_area' => null]);
        Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North Farm', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated', 'farm_size_hectares' => 2.5, 'farm_size_method' => 'exact']);
        $empty = FarmerProfile::factory()->create(['full_name' => 'Ben Cruz', 'farm_area' => null]);
        $mao = $this->mao();

        $this->actingAs($mao)->get(route('farmer-profiles.index'))->assertOk()
            ->assertSeeInOrder(['Name', 'Barangay', 'Farm Type', 'Total Farm Area', 'Status'])
            ->assertSee('2.5 ha')
            ->assertDontSee('09171234567')->assertDontSee('12 Mabini Street')
            ->assertDontSee('Not set')->assertDontSee('Not available');

        $this->actingAs($mao)->get(route('farmer-profiles.show', [$profile, 'modal' => 1]))->assertOk()
            ->assertSee('Ana Reyes')->assertSee('North Farm')->assertSee('2.5 ha')
            ->assertSee('data-mensahe="'.$farmer->id.'"', false)
            ->assertDontSee('09171234567')->assertDontSee('12 Mabini Street');

        // A profile with nothing saved for a field leaves the field out: no label, no dash.
        $this->actingAs($mao)->get(route('farmer-profiles.show', [$empty, 'modal' => 1]))->assertOk()
            ->assertSee('Farm type')->assertDontSee('Total farm area')->assertDontSee('N/A')->assertDontSee('—');
    }

    public function test_mensahe_opens_the_conversation_as_an_embedded_page(): void
    {
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);
        $mao = $this->mao();

        $redirect = $this->actingAs($mao)->postJson(route('messages.open'), ['recipient_id' => $farmer->id])->assertOk()->json('redirect');
        $this->assertNotEmpty($redirect);

        $this->actingAs($mao)->get($redirect.'?embed=1')->assertOk()->assertSee('.sidebar-fixed, .topbar', false);
        $this->actingAs($mao)->get($redirect)->assertOk()->assertDontSee('.sidebar-fixed, .topbar', false);
    }
}
