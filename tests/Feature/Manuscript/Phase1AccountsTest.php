<?php

namespace Tests\Feature\Manuscript;

use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 1: farmer approval, Last Login and IT password reset. */
class Phase1AccountsTest extends TestCase
{
    use RefreshDatabase;

    private function captchaSession(string $context, string $answer): array
    {
        return [
            CaptchaService::SESSION_KEY => [
                'context' => $context,
                'question' => 'What is 3 + 4?',
                'answer_hash' => Hash::make($answer),
                'created_at' => now()->timestamp,
            ],
        ];
    }

    private function pendingFarmer(array $attributes = []): User
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_PENDING, 'barangay' => 'Balibago'] + $attributes);
        FarmerProfile::query()->create(['user_id' => $farmer->id, 'full_name' => $farmer->name, 'barangay' => 'Balibago', 'farm_type' => 'Irrigated']);

        return $farmer;
    }

    public function test_a_farmer_sign_up_waits_for_approval(): void
    {
        $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'New Farmer', 'email' => 'new@example.com', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'barangay' => 'Balibago', 'captcha_answer' => '7',
        ])->assertRedirect('/');

        $this->assertSame(User::STATUS_PENDING, User::query()->where('email', 'new@example.com')->value('status'));
    }

    public function test_sign_ups_are_active_at_once_when_approval_is_switched_off(): void
    {
        config(['iclimate.farmer_approval' => false]);

        $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'New Farmer', 'email' => 'new@example.com', 'password' => 'Password123!',
            'password_confirmation' => 'Password123!', 'barangay' => 'Balibago', 'captcha_answer' => '7',
        ]);

        $this->assertSame(User::STATUS_ACTIVE, User::query()->where('email', 'new@example.com')->value('status'));
    }

    public function test_a_pending_farmer_cannot_log_in_and_is_told_why(): void
    {
        $farmer = $this->pendingFarmer();

        $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $farmer->email, 'password' => 'password', 'captcha_answer' => '7',
        ])->assertSessionHasErrors(['email' => User::signInRefusal(User::STATUS_PENDING)]);

        $this->assertGuest();
    }

    public function test_a_mobile_sign_up_is_pending_and_cannot_log_in(): void
    {
        $this->postJson('/api/mobile/register', [
            'name' => 'App Farmer', 'email' => 'app@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'contact_number' => '09170000000', 'barangay' => 'Balibago', 'farm_type' => 'Rainfed',
        ])->assertCreated()->assertJsonPath('user.status', User::STATUS_PENDING);

        $this->postJson('/api/mobile/login', ['email' => 'app@example.com', 'password' => 'Password123!'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', User::signInRefusal(User::STATUS_PENDING));
    }

    public function test_mao_sees_pending_sign_ups_and_approves_one(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = $this->pendingFarmer(['name' => 'Juan Dela Cruz']);

        $this->actingAs($mao)->get(route('mao.farmer-approvals.index'))
            ->assertOk()->assertSee('Juan Dela Cruz')->assertSee('Balibago');

        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $farmer))->assertRedirect();

        $farmer->refresh();
        $this->assertSame(User::STATUS_ACTIVE, $farmer->status);
        $this->assertSame($mao->id, $farmer->approved_by);
        $this->assertNotNull($farmer->approved_at);
        $this->assertTrue(Notification::query()->where('user_id', $farmer->id)->where('category', 'account')->exists());
        $this->assertDatabaseHas('system_logs', ['action' => 'Approved Farmer Account']);
    }

    public function test_mao_rejects_a_sign_up_with_a_reason(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = $this->pendingFarmer();

        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer), [])->assertSessionHasErrors('reason');
        $this->actingAs($mao)->post(route('mao.farmer-approvals.reject', $farmer), ['reason' => 'Not on the RSBSA list'])->assertRedirect();

        $farmer->refresh();
        $this->assertSame(User::STATUS_REJECTED, $farmer->status);
        $this->assertSame('Not on the RSBSA list', $farmer->rejection_reason);
        $this->actingAs($mao)->get(route('mao.farmer-approvals.index', ['show' => 'rejected']))->assertSee('Not on the RSBSA list');
    }

    public function test_only_pending_farmer_sign_ups_can_be_decided_and_only_by_mao(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $active = User::factory()->create(['role' => User::ROLE_FARMER]);
        $pending = $this->pendingFarmer();

        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $active))->assertNotFound();
        $this->actingAs($mao)->post(route('mao.farmer-approvals.approve', $it))->assertNotFound();
        $this->actingAs($it)->get(route('mao.farmer-approvals.index'))->assertStatus(403);
        $this->actingAs($active)->post(route('mao.farmer-approvals.approve', $pending))->assertStatus(403);
    }

    public function test_login_records_last_login_shown_on_the_user_list(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email, 'password' => 'password', 'captcha_answer' => '7',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $this->actingAs($it)->get(route('users.index'))->assertOk()->assertSee('Last Login');
    }

    public function test_it_resets_a_users_password_to_a_one_time_temporary_password(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $response = $this->actingAs($it)->post(route('users.reset-password', $farmer));
        $response->assertRedirect(route('users.show', $farmer));
        $temporary = session('temporary_password');

        $this->assertIsString($temporary);
        $this->assertTrue(Hash::check($temporary, $farmer->fresh()->password));
        $this->actingAs($it)->post(route('users.reset-password', $it))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->post(route('users.reset-password', $farmer))->assertStatus(403);
    }

    public function test_farmer_approvals_is_a_tab_of_the_farmers_page_for_mao(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('farmer-profiles.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-manuscript-tabs="farmers"', $html);
        $this->assertStringContainsString('href="'.route('mao.farmer-approvals.index').'"', $html);
        $this->actingAs($mao)->get(route('mao.farmer-approvals.index'))->assertSee('data-manuscript-tabs="farmers"', false);
    }
}
