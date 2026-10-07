<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerProfileModifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_modify_form_loads_existing_profile_without_address_field(): void
    {
        $it = User::factory()->itExpert()->create();
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->create([
            'user_id' => $farmer->id,
            'full_name' => 'Existing Farmer',
            'contact_number' => '09170000000',
            'address' => 'Preserved address',
        ]);

        $response = $this->actingAs($it)->get(route('farmer-profiles.create', ['user_id' => $farmer->id]));

        $response->assertOk()
            ->assertSee('Modify Farmer Profile')
            ->assertSee('Existing Farmer')
            ->assertSee('value="09170000000"', false)
            ->assertDontSee('name="address"', false)
            ->assertDontSee('>Address<', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee(route('farmer-profiles.update', FarmerProfile::query()->where('user_id', $farmer->id)->first()), false);
    }

    public function test_update_persists_changes_preserves_address_and_leaves_farm_records_untouched(): void
    {
        $it = User::factory()->itExpert()->create();
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create([
            'user_id' => $farmer->id,
            'address' => 'Do not erase this address',
        ]);
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'North Farm',
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.5,
            'farm_size_method' => 'exact',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($it)->put(route('farmer-profiles.update', $profile), [
            'user_id' => $farmer->id,
            'full_name' => 'Updated Farmer',
            'contact_number' => '09171111111',
            'barangay' => LianBarangays::all()[0],
            'farm_area' => 3.25,
            'farm_type' => 'Rainfed',
        ]);

        $response->assertRedirect(route('farmer-profiles.show', $profile));
        $this->assertSame(1, FarmerProfile::query()->where('user_id', $farmer->id)->count());
        $this->assertDatabaseHas('farmer_profiles', [
            'id' => $profile->id,
            'full_name' => 'Updated Farmer',
            'contact_number' => '09171111111',
            'address' => 'Do not erase this address',
        ]);
        $this->assertDatabaseHas('farms', [
            'id' => $farm->id,
            'farm_name' => 'North Farm',
            'farm_size_hectares' => 2.5,
            'farm_type' => 'Irrigated',
        ]);
        $this->actingAs($it)->get(route('farmer-profiles.create', ['user_id' => $farmer->id]))
            ->assertSee('Updated Farmer')
            ->assertSee('value="09171111111"', false);
    }

    public function test_store_for_existing_user_updates_the_same_profile_and_address_is_not_required(): void
    {
        $it = User::factory()->itExpert()->create();
        $farmer = User::factory()->farmer()->create();
        $profile = FarmerProfile::factory()->create([
            'user_id' => $farmer->id,
            'address' => 'Keep this value',
        ]);

        $this->actingAs($it)->post(route('farmer-profiles.store'), [
            'user_id' => $farmer->id,
            'full_name' => 'Updated From Store',
            'contact_number' => null,
            'barangay' => 'Binubusan',
            'farm_area' => 1.5,
            'farm_type' => 'Irrigated',
        ])->assertRedirect(route('farmer-profiles.show', $profile));

        $this->assertSame(1, FarmerProfile::query()->where('user_id', $farmer->id)->count());
        $this->assertDatabaseHas('farmer_profiles', [
            'id' => $profile->id,
            'full_name' => 'Updated From Store',
            'address' => 'Keep this value',
        ]);
    }

    public function test_farmer_cannot_open_the_modify_form(): void
    {
        $farmer = User::factory()->farmer()->create();

        $this->actingAs($farmer)->get(route('farmer-profiles.create'))->assertForbidden();
    }
}
