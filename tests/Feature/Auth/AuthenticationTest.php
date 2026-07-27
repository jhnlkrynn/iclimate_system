<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee(route('captcha.image', ['context' => 'login']), false);
    }

    public function test_login_screen_shows_captcha_after_three_failed_attempts(): void
    {
        $response = $this->withSession([
            CaptchaService::attemptsSessionKey('login') => 3,
        ])->get('/login');

        $response->assertStatus(200);
        $response->assertSee(route('captcha.image', ['context' => 'login']), false);
    }

    public function test_authenticated_users_can_still_open_login_screen_from_landing_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_login_captcha_image_can_be_rendered(): void
    {
        $response = $this->get('/captcha/login');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $response->assertSee('<svg', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_answer' => '7',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('farmer.dashboard', absolute: false));
    }

    public function test_login_fails_when_captcha_is_missing_after_threshold_reached(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession([
            ...$this->captchaSession('login', '7'),
            CaptchaService::attemptsSessionKey('login') => 3,
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_login_fails_when_captcha_is_invalid_after_threshold_reached(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession([
            ...$this->captchaSession('login', '7'),
            CaptchaService::attemptsSessionKey('login') => 3,
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_answer' => '8',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_login_does_not_require_captcha_before_three_failed_attempts(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $response->assertSessionDoesntHaveErrors('captcha_answer');
    }

    public function test_failed_login_attempts_below_threshold_still_allow_retry_without_captcha(): void
    {
        $user = User::factory()->create();

        // Two failed attempts should not yet require the CAPTCHA.
        for ($i = 0; $i < 2; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('farmer.dashboard', absolute: false));
    }

    public function test_captcha_is_required_after_three_consecutive_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->get('/login');
        $response->assertSee(route('captcha.image', ['context' => 'login']), false);

        $failedLoginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $failedLoginResponse->assertSessionHasErrors('captcha_answer');
    }

    public function test_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_answer' => '7',
        ]);

        $this->assertAuthenticated();
        Auth::logout();

        $response = $this->get('/login');
        $response->assertDontSee(route('captcha.image', ['context' => 'login']), false);
    }

    public function test_mao_personnel_is_redirected_after_login(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_answer' => '7',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('mao.dashboard', absolute: false));
    }

    public function test_it_expert_is_redirected_after_login(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $response = $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'captcha_answer' => '7',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->withSession($this->captchaSession('login', '7'))->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'captcha_answer' => '7',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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
}
