<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A stored barangay that is not one official Lian name (a bare "Poblacion" could be any of Barangay 1–5 (Pob.)) is
 * never guessed: the farmer is asked to confirm it, no risk rating is shown for it, and MAO can correct it.
 */
class BarangayVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
    }

    private function poblacionFarmer(): User
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => null]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Poblacion']);

        return $farmer;
    }

    public function test_a_generic_poblacion_is_not_an_official_barangay(): void
    {
        $this->assertNull(LianBarangays::canonicalName('Poblacion'));
        $this->assertSame('Barangay 2 (Pob.)', LianBarangays::canonicalName('Barangay 2 (Pob.)'));
        $this->assertCount(19, LianBarangays::all());
        $this->assertCount(19, array_unique(LianBarangays::all()));
    }

    public function test_the_farmer_is_asked_to_confirm_the_barangay_and_no_rating_is_invented(): void
    {
        $farmer = $this->poblacionFarmer();

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $start = strpos($html, 'data-fh-risk');
        $card = html_entity_decode(strip_tags(substr($html, $start, strpos($html, '</section>', $start) - $start)), ENT_QUOTES);

        $this->assertStringContainsString('Your barangay "Poblacion" needs to be confirmed.', $card);
        $this->assertStringNotContainsString('Add your barangay', $card);
        $this->assertStringNotContainsString('fh-risk-badge', substr($html, $start, strpos($html, '</section>', $start) - $start));
        $this->assertStringContainsString('data-fh-barangay-unverified', $html);
        $this->assertSame('Poblacion', $farmer->fresh()->farmerProfile->barangay);   // nothing is rewritten
    }

    public function test_mao_can_correct_it_to_an_official_poblacion_barangay_but_not_to_a_generic_one(): void
    {
        $farmer = $this->poblacionFarmer();
        $profile = $farmer->farmerProfile;
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $fields = ['user_id' => $farmer->id, 'full_name' => $profile->full_name, 'farm_type' => 'Irrigated'];

        $this->actingAs($mao)->put(route('farmer-profiles.update', $profile), $fields + ['barangay' => 'Poblacion'])
            ->assertSessionHasErrors('barangay');

        $this->actingAs($mao)->put(route('farmer-profiles.update', $profile), $fields + ['barangay' => 'Barangay 2 (Pob.)'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Barangay 2 (Pob.)', $profile->fresh()->barangay);
        $this->assertSame('Barangay 2 (Pob.)', $farmer->fresh()->registeredBarangay());
    }

    public function test_mao_can_filter_farmer_profiles_by_every_official_barangay(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $html = $this->actingAs($mao)->get(route('farmer-profiles.index'))->assertOk()->getContent();

        foreach (['Barangay 1 (Pob.)', 'Barangay 5 (Pob.)', 'Puting-Kahoy', 'Bagong Pook'] as $barangay) {
            $this->assertStringContainsString('value="'.e($barangay).'"', $html);
        }
    }
}
