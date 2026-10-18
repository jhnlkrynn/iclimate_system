<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Security\CaptchaService;
use Database\Seeders\StaffLoginAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_address_opens_the_sign_in_card_on_the_landing_page(): void
    {
        $this->get('/login')->assertRedirect(url('/').'#login');

        $this->get('/')->assertOk()
            ->assertSee('data-login-form', false)
            ->assertDontSee(route('captcha.image', ['context' => 'login']), false);
    }

    public function test_a_message_sent_to_the_login_address_reaches_the_landing_card(): void
    {
        // e.g. EnsureAccountIsActive: redirect()->route('login')->withErrors(...), then /login sends it on to /#login.
        $this->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['email' => 'This account is inactive.']))])
            ->get('/login')->assertRedirect(url('/').'#login');

        $this->get('/')->assertOk()->assertSee('id="login-email-error"', false)->assertSee('This account is inactive.');
    }

    public function test_login_screen_shows_captcha_after_three_failed_attempts(): void
    {
        $response = $this->withSession([
            CaptchaService::attemptsSessionKey('login') => 3,
        ])->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('captcha.image', ['context' => 'login']), false);
    }

    public function test_signed_in_users_who_open_the_login_address_reach_the_landing_card(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(url('/').'#login');
        $this->actingAs($user)->get('/')->assertOk()->assertSee('data-go-dashboard', false)->assertSee('data-landing-logout', false);
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

        $response = $this->get('/');
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

        $response = $this->get('/');
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

    public function test_seeded_staff_accounts_sign_in_and_reach_their_own_dashboard(): void
    {
        config(['iclimate.staff_account_password' => 'Password@123']);
        $this->seed(StaffLoginAccountSeeder::class);
        $this->seed(StaffLoginAccountSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'it@gmail.com')->count());
        $this->assertSame(1, User::query()->where('email', 'mao@gmail.com')->count());
        $this->assertNotSame('Password@123', User::query()->where('email', 'it@gmail.com')->value('password'));

        $this->post('/login', ['email' => 'it@gmail.com', 'password' => 'Password@123'])
            ->assertRedirect(route('admin.dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame(User::ROLE_IT_EXPERT, Auth::user()->role);
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('mao.dashboard'))->assertForbidden();
        $this->post('/logout');

        $this->post('/login', ['email' => 'mao@gmail.com', 'password' => 'Password@123'])
            ->assertRedirect(route('mao.dashboard', absolute: false));
        $this->assertSame(User::ROLE_MAO, Auth::user()->role);
        $this->get(route('mao.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_seeded_staff_accounts_reject_a_wrong_password(): void
    {
        config(['iclimate.staff_account_password' => 'Password@123']);
        $this->seed(StaffLoginAccountSeeder::class);

        foreach (['it@gmail.com', 'mao@gmail.com'] as $email) {
            $response = $this->from('/login')->post('/login', ['email' => $email, 'password' => 'WrongPassword']);

            $this->assertGuest();
            $this->followRedirects($response)->assertSee(trans('auth.failed'));
        }
    }

    public function test_staff_accounts_are_not_seeded_without_a_configured_password(): void
    {
        config(['iclimate.staff_account_password' => null]);
        $this->seed(StaffLoginAccountSeeder::class);

        $this->assertSame(0, User::query()->whereIn('email', ['it@gmail.com', 'mao@gmail.com'])->count());
    }

    public function test_login_ignores_stray_spaces_and_capitals_in_the_email(): void
    {
        $user = User::factory()->create(['email' => 'juan@example.com']);

        $response = $this->post('/login', ['email' => '  Juan@Example.com ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('farmer.dashboard', absolute: false));
    }

    public function test_login_does_not_follow_a_remembered_page_the_role_can_not_open(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $this->post('/login', ['email' => $mao->email, 'password' => 'password'])
            ->assertRedirect(route('mao.dashboard', absolute: false));
    }

    public function test_login_goes_to_the_dashboard_even_when_a_page_the_role_can_open_was_remembered(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        // Every sign-in lands on the role's dashboard; the remembered page is forgotten, not followed.
        $this->post('/login', ['email' => $mao->email, 'password' => 'password'])
            ->assertRedirect(route('mao.dashboard'))
            ->assertSessionMissing('url.intended');
    }

    public function test_farmers_can_not_open_the_staff_dashboards(): void
    {
        $farmer = User::factory()->create();

        $this->actingAs($farmer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($farmer)->get(route('mao.dashboard'))->assertForbidden();
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

    public function test_failed_login_shows_inline_alert_without_revealing_which_field_was_wrong(): void
    {
        $user = User::factory()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        // Back on the landing card: one message that does not say whether the email or the password was wrong.
        $this->followRedirects($response)
            ->assertSee(trans('auth.failed'))
            ->assertSee('id="login-email-error"', false)
            ->assertDontSee('id="login-password-error"', false)
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_inactive_account_can_not_sign_in(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_INACTIVE]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $this->followRedirects($response)
            ->assertSee('This account is inactive. Please contact the administrator.');
    }

    public function test_login_never_sets_a_remember_cookie_even_when_remember_is_sent(): void
    {
        $user = User::factory()->create();
        $recaller = Auth::guard('web')->getRecallerName();

        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertCookieMissing($recaller);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_session_ends_when_the_browser_closes(): void
    {
        $this->assertTrue((bool) config('session.expire_on_close'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(url('/').'#login');
    }

    public function test_a_guest_sees_the_sign_in_form_on_the_landing_page(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-login-form', false)
            ->assertSee('Log in to iClimate')
            ->assertDontSee('data-go-dashboard', false);
    }

    public function test_after_logout_the_landing_page_shows_the_sign_in_form(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get('/')->assertSee('data-go-dashboard', false)->assertSee('data-landing-logout', false);

        $this->post('/logout')->assertRedirect(url('/').'#login');

        $this->assertGuest();
        $this->get('/')->assertOk()
            ->assertSee('data-login-form', false)
            ->assertDontSee('Welcome back')
            ->assertDontSee('data-go-dashboard', false);
    }

    public function test_a_signed_in_user_on_the_landing_page_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Go to dashboard')
            ->assertSee('<form method="POST" action="'.route('logout').'" class="mt-3 text-center" data-landing-logout>', false)
            ->assertSee('Sign out');
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
