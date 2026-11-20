<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    // 2026-11-20: a farmer without a profile row got HTTP 500 (farmer_profiles.barangay is NOT NULL).
    public function test_a_farmer_without_a_profile_gets_one_from_their_account_barangay(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Cumba']);
        $this->assertNull($user->farmerProfile);

        $this->actingAs($user)->patch('/profile', ['name' => 'Juan Dela Cruz', 'email' => $user->email, 'barangay' => '', 'farm_type' => 'Irrigated'])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $profile = $user->fresh()->farmerProfile;
        $this->assertSame('Cumba', $profile->barangay);
        $this->assertSame('Irrigated', $profile->farm_type);
    }

    public function test_a_farmer_without_any_barangay_is_asked_for_one_instead_of_an_error(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => null]);

        $this->actingAs($user)->patch('/profile', ['name' => 'Juan Dela Cruz', 'email' => $user->email, 'farm_type' => 'Rainfed'])
            ->assertRedirect('/profile')->assertSessionHasErrors('barangay');
        $this->assertNull($user->fresh()->farmerProfile);
    }

    public function test_a_blank_barangay_keeps_the_saved_one(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Cumba']);
        \App\Models\FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Bagong Pook']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Juan Dela Cruz', 'email' => $user->email, 'barangay' => '', 'farm_type' => 'Rainfed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Bagong Pook', $user->fresh()->farmerProfile->barangay);
    }
}
