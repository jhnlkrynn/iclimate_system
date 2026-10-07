<?php

namespace Tests\Feature\Manuscript;

use App\Models\BarangayProfile;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 3: Barangays page and the Heat Map's flood / drought / yield layers. */
class Phase3BarangaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_barangay_has_a_profile_with_its_official_coordinates(): void
    {
        $this->assertSame(19, BarangayProfile::query()->count());
        $balibago = BarangayProfile::query()->where('barangay', 'Balibago')->sole();
        $this->assertEqualsWithDelta(LianBarangays::coordinateFor('Balibago')[0], (float) $balibago->latitude, 0.0000001);
    }

    public function test_mao_lists_and_edits_a_barangay(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('mao.barangays.index'))->assertOk()
            ->assertSee('Barangay 1 / Bonbon (Poblacion)')->assertSee('data-manuscript-tabs="records"', false);

        $this->actingAs($mao)->put(route('mao.barangays.update', 'Lumaniag'), [
            'farm_area_hectares' => '210.5', 'risk_classification' => 'High', 'flood_prone' => '1', 'notes' => 'Low-lying near the river',
        ])->assertRedirect();

        $profile = BarangayProfile::query()->where('barangay', 'Lumaniag')->sole();
        $this->assertTrue($profile->flood_prone);
        $this->assertFalse($profile->drought_prone);
        $this->assertSame('High', $profile->risk_classification);
        $this->assertSame($mao->id, $profile->updated_by);
    }

    public function test_bad_values_and_unknown_barangays_are_refused(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->put(route('mao.barangays.update', 'Lumaniag'), ['risk_classification' => 'Extreme'])->assertSessionHasErrors('risk_classification');
        $this->actingAs($mao)->put(route('mao.barangays.update', 'Atlantis'), [])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_FARMER]))->get(route('mao.barangays.index'))->assertStatus(403);
    }

    public function test_records_tabs_include_barangays_for_mao(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('climate-records.index'))->assertOk()->assertSee('href="'.route('mao.barangays.index').'"', false);
    }

    public function test_heat_map_has_flood_drought_and_yield_layers_from_the_barangay_records(): void
    {
        BarangayProfile::query()->where('barangay', 'Lumaniag')->update(['flood_prone' => true]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->get(route('heat-map.index', ['range' => 'flood']))->assertOk();
        $response->assertSee('data-heat-map-layer', false)->assertSee('data-heat-map-legend="flood"', false)->assertSee('Flood-prone barangays');

        $ranges = $response->viewData('ranges');
        $this->assertSame('flood', $response->viewData('range'));
        $this->assertSame('high', $ranges['flood']['barangays']['Lumaniag']['level']);
        $this->assertSame('low', $ranges['flood']['barangays']['Balibago']['level']);
        // Once the MAO has marked any barangay, an unmarked one reads "not drought-prone", not "not checked".
        $this->assertSame('low', $ranges['drought']['barangays']['Lumaniag']['level']);
        $this->assertSame('nodata', $ranges['yield']['barangays']['Balibago']['level']);
    }

    public function test_before_any_barangay_is_marked_the_flood_layer_says_not_checked(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $ranges = $this->actingAs($mao)->get(route('heat-map.index'))->assertOk()->viewData('ranges');

        $this->assertSame('nodata', $ranges['flood']['state']);
        $this->assertSame('week', $this->actingAs($mao)->get(route('heat-map.index', ['range' => 'nonsense']))->viewData('range'));
    }

    public function test_farmers_see_the_layers_in_filipino(): void
    {
        BarangayProfile::query()->where('barangay', 'Balibago')->update(['drought_prone' => true]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago', 'preferred_language' => 'fil']);

        $this->actingAs($farmer)->get(route('heat-map.index', ['range' => 'drought']))->assertOk()
            ->assertSee('Madalas tagtuyot');
    }
}
