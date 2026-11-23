<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExistingUserPrivacyConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_users_of_all_roles_without_privacy_agreed_at_must_agree_once_on_login(): void
    {
        $roles = [
            'farmer' => User::factory()->farmer()->create(['privacy_agreed_at' => null]),
            'mao' => User::factory()->maoPersonnel()->create(['privacy_agreed_at' => null]),
            'mao_head' => User::factory()->maoHead()->create(['privacy_agreed_at' => null]),
        ];

        foreach ($roles as $user) {
            $this->actingAs($user)
                ->get(route($user->dashboardRoute()))
                ->assertRedirect(route('privacy.consent'));

            $this->actingAs($user)
                ->get(route('privacy.consent'))
                ->assertOk()
                ->assertSee(__('privacy.consent_title'));

            $this->actingAs($user)
                ->post(route('privacy.consent.store'), [])
                ->assertSessionHasErrors('privacy_agreed');

            $this->actingAs($user)
                ->post(route('privacy.consent.store'), ['privacy_agreed' => '1'])
                ->assertRedirect(route($user->dashboardRoute()));

            $this->assertNotNull($user->fresh()->privacy_agreed_at);

            $this->actingAs($user->fresh())
                ->get(route('privacy.consent'))
                ->assertRedirect(route($user->dashboardRoute()));
        }
    }
}
