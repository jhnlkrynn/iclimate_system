<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantingAdvisoryBarangayTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_barangay_is_a_lian_barangay_dropdown(): void
    {
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)
            ->get(route('planting-advisories.create'))
            ->assertOk()
            ->assertSee('All Barangays')
            ->assertSee('Bagong Pook')
            ->assertSee('Matabungkay')
            ->assertSee('San Diego');
    }

    public function test_planting_advisory_requires_valid_lian_barangay_when_targeted(): void
    {
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->post(route('planting-advisories.store'), [
            'title' => 'Dry Season Planting',
            'content' => 'Prepare seedlings and monitor water supply.',
            'type' => 'Planting',
            'target_barangay' => 'Not A Lian Barangay',
            'status' => 'Draft',
        ])->assertSessionHasErrors('target_barangay');

        $this->actingAs($mao)->post(route('planting-advisories.store'), [
            'title' => 'Dry Season Planting',
            'content' => 'Prepare seedlings and monitor water supply.',
            'type' => 'Planting',
            'target_barangay' => 'Matabungkay',
            'status' => 'Draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('planting_advisories', [
            'title' => 'Dry Season Planting',
            'target_barangay' => 'Matabungkay',
        ]);
    }
}
