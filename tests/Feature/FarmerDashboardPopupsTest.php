<?php

namespace Tests\Feature;

use App\Models\FarmBoundary;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What the farmer dashboard's Add Farm Size, Update Profile and Map My Farm pop-ups save, and for whom. */
class FarmerDashboardPopupsTest extends TestCase
{
    use RefreshDatabase;

    private const OUTLINE = [['lat' => 14.0300, 'lng' => 120.6500], ['lat' => 14.0310, 'lng' => 120.6500], ['lat' => 14.0310, 'lng' => 120.6512], ['lat' => 14.0300, 'lng' => 120.6512]];

    private function farmer(?float $area = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => User::ROLE_FARMER, 'name' => 'Juan Dela Cruz', 'barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'full_name' => $user->name, 'barangay' => 'Binubusan', 'farm_area' => $area, 'farm_type' => 'Rainfed']);

        return $user;
    }

    private function profileData(array $changes = []): array
    {
        return $changes + ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'contact_number' => '0917 123 4567', 'address' => 'Purok 2', 'barangay' => 'Binubusan', 'farm_type' => 'Rainfed'];
    }

    // ------------------------------------------------------------ Add Farm Size

    public function test_farm_size_is_saved_on_the_signed_in_farmers_one_profile(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(4.0);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '1.5', 'user_id' => $other->id, 'farmer_profile_id' => $other->farmerProfile->id])
            ->assertOk()
            ->assertJson(['message' => 'Farm size saved successfully.', 'values' => ['farm_area' => '1.50']]);
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '2.75'])->assertOk();

        $this->assertSame(1, FarmerProfile::query()->where('user_id', $farmer->id)->count());      // updated, never duplicated
        $this->assertSame('2.75', $farmer->farmerProfile()->first()->farm_area);
        $this->assertSame('4.00', $other->farmerProfile()->first()->farm_area);
    }

    public function test_farm_information_also_saves_the_farm_type_when_it_is_sent(): void
    {
        $farmer = $this->farmer(1.0);                                                               // Rainfed

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '2', 'farm_type' => 'Irrigated'])
            ->assertOk()->assertJson(['message' => 'Farm information saved successfully.', 'values' => ['farm_area' => '2.00', 'farm_type' => 'Irrigated']]);
        $this->assertSame(['2.00', 'Irrigated'], [$farmer->farmerProfile()->first()->farm_area, $farmer->farmerProfile()->first()->farm_type]);

        // Left unchosen (or not sent at all, as from "Add farm size") the saved farm type stays.
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '2.5', 'farm_type' => ''])->assertOk();
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '2.5'])->assertOk()->assertJsonPath('values.farm_type', 'Irrigated');
        $this->assertSame('Irrigated', $farmer->farmerProfile()->first()->farm_type);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '2.5', 'farm_type' => 'Hydroponic'])
            ->assertStatus(422)->assertJsonPath('errors.farm_type.0', 'Please choose a water source from the list.');
    }

    public function test_farm_size_must_be_a_number_above_zero(): void
    {
        $farmer = $this->farmer(1.25);

        foreach ([null, '', '0', '-1', 'abc', '10001'] as $area) {
            $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => $area])
                ->assertStatus(422)->assertJsonPath('errors.farm_area.0', 'Please enter a valid farm size.');
        }
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '1.234'])
            ->assertStatus(422)->assertJsonPath('errors.farm_area.0', 'Please use up to 2 decimal places, for example 1.50.');

        $this->assertSame('1.25', $farmer->farmerProfile()->first()->farm_area);
    }

    public function test_farm_size_fills_a_current_plan_that_has_none_but_leaves_a_set_one_alone(): void
    {
        $plan = fn (User $farmer, ?float $area) => FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => now()->subDays(5)->toDateString(), 'scheduled_harvest_date' => now()->addDays(110)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Rainfed', 'farm_area_hectares' => $area,
        ]);
        $empty = $plan($first = $this->farmer(), null);
        $set = $plan($second = $this->farmer(), 3.0);

        $this->actingAs($first)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '1.5'])->assertOk();
        $this->actingAs($second)->postJson(route('farmer.dashboard.farm-size'), ['farm_area' => '1.5'])->assertOk();

        $this->assertSame('1.50', $empty->refresh()->farm_area_hectares);
        $this->assertSame('3.00', $set->refresh()->farm_area_hectares);
    }

    // ------------------------------------------------------------ Update Profile

    public function test_profile_updates_the_account_and_the_farmers_profile_record(): void
    {
        $farmer = $this->farmer(2.0);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.profile'), $this->profileData([
            'name' => 'Juan P. Dela Cruz', 'email' => '  Juan.New@Example.com ', 'barangay' => 'Lumaniag', 'farm_type' => 'Irrigated',
        ]))->assertOk()->assertJson(['message' => 'Profile updated successfully.', 'values' => ['name' => 'Juan P. Dela Cruz', 'email' => 'juan.new@example.com', 'barangay' => 'Lumaniag', 'farm_type' => 'Irrigated']]);

        $farmer->refresh();
        $this->assertSame(['Juan P. Dela Cruz', 'juan.new@example.com', '0917 123 4567', 'Purok 2', 'Lumaniag'], [$farmer->name, $farmer->email, $farmer->contact_number, $farmer->address, $farmer->barangay]);
        $this->assertSame(1, FarmerProfile::query()->where('user_id', $farmer->id)->count());      // no second profile
        $profile = $farmer->farmerProfile()->first();
        $this->assertSame(['Juan P. Dela Cruz', '0917 123 4567', 'Purok 2', 'Lumaniag', 'Irrigated'], [$profile->full_name, $profile->contact_number, $profile->address, $profile->barangay, $profile->farm_type]);
        $this->assertSame('2.00', $profile->farm_area);                                             // not this pop-up's to change
    }

    public function test_profile_fields_are_validated_with_messages_under_each_field(): void
    {
        $farmer = $this->farmer();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.profile'), $this->profileData([
            'name' => '', 'email' => 'not-an-email', 'contact_number' => 'call me', 'barangay' => 'Makati', 'farm_type' => 'Hydroponic',
        ]))->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Please enter your full name.')
            ->assertJsonPath('errors.email.0', 'Please enter a valid email address.')
            ->assertJsonPath('errors.contact_number.0', 'Please enter a valid contact number, for example 0917 123 4567.')
            ->assertJsonPath('errors.barangay.0', 'Please choose a barangay from the list.')
            ->assertJsonPath('errors.farm_type.0', 'Please choose a water source from the list.');
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.profile'), $this->profileData(['email' => 'taken@example.com']))
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'This email address is already used by another account.');

        $this->assertSame('Juan Dela Cruz', $farmer->refresh()->name);
    }

    public function test_profile_keeps_the_saved_barangay_and_farm_type_when_they_are_left_unchosen(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.profile'), $this->profileData(['barangay' => '', 'farm_type' => '', 'contact_number' => '', 'address' => '']))->assertOk();

        $profile = $farmer->farmerProfile()->first();
        $this->assertSame(['Binubusan', 'Rainfed'], [$profile->barangay, $profile->farm_type]);
        $this->assertNull($farmer->refresh()->contact_number);
    }

    public function test_profile_changes_only_the_signed_in_farmer(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(null, ['name' => 'Maria Santos']);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.profile'), $this->profileData(['name' => 'Changed Name', 'user_id' => $other->id, 'id' => $other->id]))->assertOk();

        $this->assertSame('Changed Name', $farmer->refresh()->name);
        $this->assertSame('Maria Santos', $other->refresh()->name);
        $this->assertSame(User::ROLE_FARMER, $farmer->role);                                        // a role sent along would be ignored too
    }

    // ------------------------------------------------------------ Map My Farm

    public function test_the_farm_outline_is_saved_for_the_signed_in_farmer_and_measured(): void
    {
        $farmer = $this->farmer(1.5);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), ['boundary_coordinates' => self::OUTLINE])
            ->assertOk()->assertJson(['message' => 'Farm location saved successfully.', 'boundary_coordinates' => self::OUTLINE]);
        $moved = array_map(fn (array $point) => ['lat' => round($point['lat'] + 0.001, 4), 'lng' => $point['lng']], self::OUTLINE);
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), ['boundary_coordinates' => $moved])->assertOk();

        $boundary = FarmBoundary::query()->sole();                                                  // adjusted, not added again
        $this->assertSame($farmer->farmerProfile->id, $boundary->farmer_profile_id);
        $this->assertEquals($moved, $boundary->boundary_coordinates);
        $this->assertGreaterThan(0, (float) $boundary->calculated_area_hectares);
        $this->assertSame('1.50', $farmer->farmerProfile()->first()->farm_area);                    // the entered farm size is not this pop-up's to change
    }

    public function test_saving_the_farm_outline_drops_the_farmers_stored_dashboard_page(): void
    {
        $farmer = $this->farmer();
        $key = \App\Services\Analytics\FarmerDashboardService::cacheKey($farmer);
        \Illuminate\Support\Facades\Cache::put($key, ['stale' => true], 60);

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), ['boundary_coordinates' => self::OUTLINE])->assertOk();

        $this->assertNull(\Illuminate\Support\Facades\Cache::get(\App\Services\Analytics\FarmerDashboardService::cacheKey($farmer)));
    }

    public function test_a_farm_location_must_be_marked(): void
    {
        $farmer = $this->farmer();

        foreach ([[], null, array_slice(self::OUTLINE, 0, 2)] as $points) {
            $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), ['boundary_coordinates' => $points])
                ->assertStatus(422)->assertJsonPath('errors.boundary_coordinates.0', 'Please select your farm location on the map.');
        }

        $this->assertSame(0, FarmBoundary::query()->count());
    }

    // ------------------------------------------------------------ Map My Farm: Lian only, barangay from the map

    /** @return array<int, array{lat: float, lng: float}> a small square field around a position */
    private function field(float $lat, float $lng, float $half = 0.0004): array
    {
        return [['lat' => $lat - $half, 'lng' => $lng - $half], ['lat' => $lat - $half, 'lng' => $lng + $half], ['lat' => $lat + $half, 'lng' => $lng + $half], ['lat' => $lat + $half, 'lng' => $lng - $half]];
    }

    private function saveMap(User $farmer, array $data)
    {
        return $this->actingAs($farmer)->postJson(route('farmer.dashboard.farm-map'), $data);
    }

    public function test_the_farms_barangay_is_worked_out_from_the_outline_and_kept_on_the_farm(): void
    {
        $farmer = $this->farmer();                                                                  // lives in Binubusan

        $this->saveMap($farmer, ['boundary_coordinates' => self::OUTLINE])->assertOk()->assertJsonPath('barangay', 'San Diego');

        $this->assertSame('San Diego', FarmBoundary::query()->sole()->barangay);
        // A farmer may live in one barangay and farm in another: the profile and the account are not touched.
        $this->assertSame('Binubusan', $farmer->farmerProfile()->first()->barangay);
        $this->assertSame('Binubusan', $farmer->refresh()->barangay);
    }

    public function test_a_farm_in_the_far_south_of_lian_is_accepted(): void
    {
        // Balibago and Matabungkay lie south of the old "service area" box, which wrongly turned them away.
        foreach (['Balibago' => [13.9413816, 120.6489014], 'Matabungkay' => [13.9467451, 120.6249676]] as $barangay => [$lat, $lng]) {
            $this->saveMap($this->farmer(), ['boundary_coordinates' => $this->field($lat, $lng)])->assertOk()->assertJsonPath('barangay', $barangay);
        }
    }

    public function test_a_farm_outside_lian_can_not_be_saved(): void
    {
        $farmer = $this->farmer();
        $message = 'You can only map farms located within Lian, Batangas. Please select a location inside the municipality.';

        // Manila; Nasugbu just north of Lian; Calatagan just south; the sea to the west. The last three were inside the old box check.
        foreach ([[14.5995, 120.9842], [14.0700, 120.6350], [13.9200, 120.6400], [14.0000, 120.5900]] as [$lat, $lng]) {
            $this->saveMap($farmer, ['boundary_coordinates' => $this->field($lat, $lng)])
                ->assertStatus(422)->assertJsonPath('errors.boundary_coordinates.0', $message);
        }

        $this->assertSame(0, FarmBoundary::query()->count());
    }

    public function test_a_farm_that_is_partly_outside_lian_can_not_be_saved(): void
    {
        $farmer = $this->farmer();
        $message = 'Part of this farm area is outside Lian, Batangas. Please adjust the farm boundary before saving.';

        // One corner pulled out to Nasugbu.
        $stretched = [self::OUTLINE[0], self::OUTLINE[1], ['lat' => 14.0700, 'lng' => 120.6350]];
        $this->saveMap($farmer, ['boundary_coordinates' => $stretched])->assertStatus(422)->assertJsonPath('errors.boundary_coordinates.0', $message);
        // Every corner is inside Lian (all in Luyahan), but one side runs out across the coast and back.
        $acrossTheCoast = [['lat' => 13.968067, 'lng' => 120.612469], ['lat' => 13.967618, 'lng' => 120.614915], ['lat' => 13.966375, 'lng' => 120.613423]];
        $this->saveMap($farmer, ['boundary_coordinates' => $acrossTheCoast])->assertStatus(422)->assertJsonPath('errors.boundary_coordinates.0', $message);

        $this->assertSame(0, FarmBoundary::query()->count());
    }

    public function test_a_barangay_sent_by_the_browser_is_ignored(): void
    {
        $farmer = $this->farmer();

        $this->saveMap($farmer, ['boundary_coordinates' => self::OUTLINE, 'barangay' => 'Balibago', 'farm_barangay' => 'Balibago', 'municipality' => 'Nasugbu'])
            ->assertOk()->assertJsonPath('barangay', 'San Diego');
        $this->saveMap($farmer, ['boundary_coordinates' => $this->field(14.0700, 120.6350), 'barangay' => 'San Diego'])->assertStatus(422);

        $this->assertSame('San Diego', FarmBoundary::query()->sole()->barangay);                    // the rejected attempt changed nothing
    }

    public function test_a_farm_across_two_barangays_always_gets_the_same_one(): void
    {
        // Three corners and the middle are in Prenza, one corner in Puting-Kahoy.
        $outline = [['lat' => 13.997154, 'lng' => 120.651725], ['lat' => 13.997154, 'lng' => 120.652925], ['lat' => 13.998354, 'lng' => 120.652925], ['lat' => 13.998354, 'lng' => 120.651725]];

        foreach ([$outline, array_reverse($outline), [$outline[2], $outline[3], $outline[0], $outline[1]]] as $order) {
            $this->saveMap($this->farmer(), ['boundary_coordinates' => $order])->assertOk()->assertJsonPath('barangay', 'Prenza');
        }
    }

    public function test_the_farm_map_page_follows_the_same_lian_rule(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($farmer)->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode($this->field(14.0700, 120.6350))])
            ->assertSessionHasErrors('boundary_coordinates');
        $this->assertSame(0, FarmBoundary::query()->count());

        $this->actingAs($farmer)->put(route('farmer.boundary.update'), ['boundary_coordinates' => json_encode(self::OUTLINE)])->assertSessionHasNoErrors();
        $this->assertSame('San Diego', FarmBoundary::query()->sole()->barangay);
    }

    // ------------------------------------------------------------ access

    public function test_only_signed_in_farmers_can_use_the_pop_ups(): void
    {
        $requests = [
            'farmer.dashboard.farm-size' => ['farm_area' => '1.5'],
            'farmer.dashboard.profile' => $this->profileData(),
            'farmer.dashboard.farm-map' => ['boundary_coordinates' => self::OUTLINE],
        ];

        foreach ($requests as $route => $data) {
            $this->postJson(route($route), $data)->assertUnauthorized();
        }
        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            foreach ($requests as $route => $data) {
                $this->actingAs($staff)->postJson(route($route), $data)->assertForbidden();
            }
        }

        $this->assertSame(0, FarmerProfile::query()->count());
    }
}
