<?php

namespace Tests\Feature;

use App\Models\Advisory;
use App\Models\Notification;
use App\Services\Security\CaptchaService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The public landing page (welcome.blade.php + resources/views/landing/*): its nav, login card, advisory and contact form. */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_has_the_nav_the_login_form_and_a_csrf_field(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('data-public-nav', false)
            ->assertSee('href="#hero"', false)
            ->assertSee('href="#about"', false)
            ->assertSee('href="#features"', false)
            ->assertSee('href="#roles"', false)
            ->assertSee('href="#contact"', false)
            ->assertSee('data-weather-chip', false)
            ->assertDontSee('data-text-size-toggle', false)
            ->assertSee('action="'.route('login').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="remember"', false)   // no "Remember me": the sign-in ends when the browser closes
            ->assertSee('href="'.route('password.request').'"', false)
            ->assertSee('action="'.route('contact.send').'"', false)
            ->assertDontSee('Republic of the Philippines');
    }

    public function test_every_section_anchor_exists(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['hero', 'about', 'features', 'numbers', 'how', 'roles', 'contact', 'top'] as $id) {
            $response->assertSee('id="'.$id.'"', false);
        }
        $response->assertDontSee('id="advisory"', false);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function roles(): array
    {
        return [
            'farmer' => [User::ROLE_FARMER, 'farmer.dashboard'],
            'MAO personnel' => [User::ROLE_MAO, 'mao.dashboard'],
            'IT personnel' => [User::ROLE_IT_EXPERT, 'admin.dashboard'],
        ];
    }

    /** @dataProvider roles */
    public function test_valid_login_from_the_landing_card_goes_to_the_role_dashboard(string $role, string $dashboard): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->from('/')->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertRedirect(route($dashboard, absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    /** @dataProvider roles */
    public function test_a_page_remembered_before_login_is_ignored_and_the_landing_login_goes_to_the_role_dashboard(string $role, string $dashboard): void
    {
        $user = User::factory()->create(['role' => $role]);

        // A guest opens the heat map (e.g. from the weather panel before it changed): Laravel remembers it.
        $this->get(route('heat-map.index'))->assertRedirect(route('login'));
        $this->assertSame(route('heat-map.index'), session('url.intended'));

        $this->from('/')->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($dashboard))
            ->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($user);
    }

    /** @dataProvider roles */
    public function test_signed_in_users_who_open_a_guest_only_page_go_to_their_dashboard(string $role, string $dashboard): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('register'))
            ->assertRedirect(route($dashboard));
    }

    public function test_the_weather_panel_sends_guests_to_the_login_card_and_signed_in_users_to_the_heat_map(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<a href="#login" class="lp-link mt-3 inline-flex min-h-11 items-center" data-weather-heat-map>Log in to view the heat map</a>', false)
            ->assertDontSee('href="'.route('heat-map.index').'"', false);

        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('href="'.route('heat-map.index').'" class="lp-link mt-3 inline-flex min-h-11 items-center" data-weather-heat-map>View heat map</a>', false);
    }

    public function test_invalid_login_returns_to_the_landing_page_with_the_error_in_the_card(): void
    {
        $user = User::factory()->create();

        $this->from('/')->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->get('/')->assertOk()
            ->assertSee('id="login-email-error"', false)
            ->assertSee(trans('auth.failed'))
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_the_card_asks_for_the_security_code_after_repeated_failures(): void
    {
        $this->withSession([\App\Services\Security\CaptchaService::attemptsSessionKey('login') => 3])
            ->get('/')->assertOk()
            ->assertSee('name="captcha_answer"', false)
            ->assertSee(route('captcha.image', ['context' => 'login']), false);
    }

    /** @dataProvider roles */
    public function test_logged_in_users_see_go_to_dashboard_instead_of_the_form(string $role, string $dashboard): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Go to dashboard')
            ->assertSee('href="'.route($dashboard).'"', false)
            ->assertDontSee('data-login-form', false);
    }

    public function test_there_is_no_advisory_block_and_the_bell_opens_the_advisories_page(): void
    {
        Advisory::query()->create([
            'title' => 'Typhoon watch for Lian',
            'content' => 'Harvest mature palay before Friday.',
            'type' => 'Climate',
            'status' => Advisory::STATUS_PUBLISHED,
            'published_at' => now(),
            'posted_by' => User::factory()->maoPersonnel()->create()->id,
        ]);

        $this->get('/')->assertOk()
            ->assertDontSee('id="advisory"', false)
            ->assertDontSee('All advisories')
            ->assertDontSee('Typhoon watch for Lian');
    }

    public function test_the_landing_nav_has_the_weather_chip_and_text_size_toggle_instead_of_the_user_and_bell_icons(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));

        $this->assertStringNotContainsString('aria-label="Log in"', $nav);
        $this->assertStringNotContainsString('aria-label="MAO advisories"', $nav);
        $this->assertStringNotContainsString('data-nav-login-icon', $nav);
        $this->assertStringNotContainsString('data-nav-bell', $nav);
        $this->assertStringNotContainsString('href="'.route('planting-advisories.index').'"', $nav);
        $this->assertStringContainsString('data-weather-url="'.route('api.weather.current').'"', $nav);
        $this->assertStringContainsString('aria-controls="weatherPanel"', $nav);
        $this->assertStringContainsString('data-weather-heat-map', $nav);
        $this->assertStringNotContainsString('Larger text', $nav);   // the text size button was removed
        // The Weather analysis card reads the same answer (one request), so it carries no URL of its own.
        $this->assertStringNotContainsString(route('login.weather'), $html);
    }

    public function test_the_hero_is_one_screen_and_every_other_section_shares_the_section_rhythm(): void
    {
        $this->markTestSkipped('Landing sections now use varied layouts and no CTA band.');
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<section id="hero"[^>]*class="lp-hero /', $html);
        foreach (['about', 'features', 'numbers', 'how', 'roles', 'faq', 'contact', 'get-started'] as $id) {
            $this->assertMatchesRegularExpression('/<section id="'.$id.'"[^>]*class="lp-section[ "]/', $html, "#{$id} does not use .lp-section");
        }
        // White, surface and the two dark bands, in this order.
        preg_match_all('/<section id="([a-z-]+)"[^>]*class="([^"]*)"/', $html, $sections, PREG_SET_ORDER);
        $backgrounds = collect($sections)->mapWithKeys(fn (array $m): array => [$m[1] => str_contains($m[2], 'lp-band') ? 'dark'
            : (str_contains($m[2], 'lp-section-surface') ? 'surface' : 'white')])->except('hero')->all();
        $this->assertSame(['about' => 'white', 'features' => 'surface', 'numbers' => 'dark', 'how' => 'white', 'roles' => 'surface',
            'faq' => 'white', 'contact' => 'surface', 'get-started' => 'dark'], $backgrounds);
    }

    public function test_how_it_works_uses_four_ordered_columns_and_static_ui_fragments(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-steps', $html);
        $this->assertSame(4, substr_count($html, 'class="how-step"'));
        $this->assertStringContainsString('data-ui-fragment="farm"', $html);
        $this->assertStringContainsString('data-ui-fragment="forecast"', $html);
        $this->assertStringContainsString('data-ui-fragment="advisory"', $html);
        $this->assertStringContainsString('Days to harvest: 69 · around Jan 8, 2027', $html);
        $this->assertStringNotContainsString('data-season-timeline', $html);
        $this->assertStringNotContainsString('data-season-month', $html);
        $this->assertStringNotContainsString('data-step-number', $html);
        $this->assertStringNotContainsString('data-dashboard-shot', $html);
        $this->assertStringNotContainsString('farmer-dashboard.jpg', $html);
    }

    public function test_the_hero_card_holds_both_the_login_and_the_create_account_form(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('login').'"', $html);
        $this->assertStringContainsString('action="'.route('register').'"', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'name="_token"'), 'login, sign-up and contact forms each carry CSRF');
        $this->assertStringContainsString('data-auth-mode="login"', $html);
        // The Sign in / Create account tabs are the only switch; the old bottom links are gone.
        $this->assertStringContainsString('role="tablist" aria-label="Log in or create an account"', $html);
        $this->assertMatchesRegularExpression('#<button type="button" role="tab" id="auth-tab-login" aria-controls="auth-panel-login" data-auth-switch="login"\s+aria-selected="true" tabindex="0"#', $html);
        $this->assertMatchesRegularExpression('#<button type="button" role="tab" id="auth-tab-register" aria-controls="auth-panel-register" data-auth-switch="register"\s+aria-selected="false" tabindex="-1"#', $html);
        $this->assertStringContainsString('role="tabpanel" id="auth-panel-register" aria-labelledby="auth-tab-register"', $html);
        $this->assertStringNotContainsString('New to iClimate?', $html);
        $this->assertStringNotContainsString('Already have an account?', $html);
        // The sign-up fields are the shared /register ones.
        foreach (['name="first_name"', 'name="last_name"', 'name="farmer_organization_id"', 'name="barangay"', 'name="password_confirmation"', 'name="captcha_answer"', 'id="pwdToggle"', 'id="passwordStrengthLabel"', 'data-password-rule="symbol"', 'id="agreeTerms"', 'Register as a rice farmer in Lian, Batangas.', 'MAO Staff and IT Personnel accounts are set up by the system administrators.'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        // The sign-up captcha picture waits until that form is shown (one captcha per session).
        $this->assertStringContainsString('data-captcha-src="'.route('captcha.image', ['context' => 'register']).'"', $html);
    }

    public function test_a_failed_sign_up_from_the_landing_card_returns_to_the_landing_page_in_create_account_mode(): void
    {
        $this->from('/')->post(route('register'), [
            '_auth_form' => 'register', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'name' => 'Juan Dela Cruz',
            'email' => 'not-an-email', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'captcha_answer' => '',
        ])->assertRedirect('/')->assertSessionHasErrors(['email', 'captcha_answer', 'barangay']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-auth-mode="register"', $html);
        $this->assertMatchesRegularExpression('#<div role="tabpanel" id="auth-panel-login" aria-labelledby="auth-tab-login" data-auth-panel="login"\s+hidden\s*>#', $html);
        $this->assertMatchesRegularExpression('#id="auth-tab-register" aria-controls="auth-panel-register" data-auth-switch="register"\s+aria-selected="true"#', $html);
        $this->assertMatchesRegularExpression('#<p class="field-error" id="email-error"\s*>[^<]*valid email#', $html);
        $this->assertMatchesRegularExpression('#id="captcha_answer-error"\s*>Please answer the security check#', $html);
        $this->assertStringContainsString('value="Juan"', $html);
        $this->assertStringContainsString('value="not-an-email"', $html);
        $this->assertStringNotContainsString('Secret123!', $html, 'passwords are never sent back');
        // The login form stays clean: no sign-up email, no sign-up errors.
        $this->assertMatchesRegularExpression('#id="login-email" name="email" value=""#', $html);
        $this->assertStringNotContainsString('id="login-email-error"', $html);
        // Its captcha picture loads straight away now that the sign-up form is the one showing.
        $this->assertStringContainsString('src="'.route('captcha.image', ['context' => 'register']).'?v=', $html);
    }

    public function test_a_valid_sign_up_from_the_landing_card_keeps_the_existing_success_redirect(): void
    {
        $session = [CaptchaService::SESSION_KEY => ['context' => 'register', 'question' => 'x', 'answer_hash' => Hash::make('7'), 'created_at' => now()->timestamp]];

        $this->withSession($session)->from('/')->post(route('register'), [
            '_auth_form' => 'register', 'name' => 'New Farmer', 'email' => 'landing-farmer@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'barangay' => 'Balibago', 'captcha_answer' => '7',
        ])->assertRedirect('/')->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(User::ROLE_FARMER, User::query()->where('email', 'landing-farmer@example.com')->sole()->role);
        $this->assertGuest();
        // Back on the landing page the card is in its normal Log in mode.
        $this->get('/')->assertOk()->assertSee('data-auth-mode="login"', false);
    }

    public function test_a_failed_login_reopens_the_card_in_log_in_mode_and_register_still_works(): void
    {
        $this->from('/')->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('data-auth-mode="login"', false)->assertSee('id="login-email-error"', false)
            ->assertDontSee('id="email-error" >', false);

        $this->get(route('register'))->assertRedirect(url('/').'#register');
        $this->get('/')->assertSee('action="'.route('register').'"', false)->assertSee('name="first_name"', false);
    }

    public function test_the_contact_form_validates_and_submits(): void
    {
        $mao = User::factory()->maoPersonnel()->create();

        $this->postJson(route('contact.send'), ['name' => '', 'email' => 'not-an-email', 'message' => 'hi'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        // Every role the landing form offers is one the controller accepts.
        foreach (['Rice Farmer', 'MAO Personnel', 'IT Personnel', 'Other'] as $role) {
            $this->get('/')->assertSee('<option value="'.$role.'">', false);
        }

        $this->postJson(route('contact.send'), ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'role' => 'Rice Farmer', 'message' => 'When is the next seed distribution?'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame($mao->id, Notification::query()->sole()->user_id);
    }

    public function test_contact_links_use_mailto_and_directions_open_in_a_new_tab(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('class="questions-faq"', false)
            ->assertSee('class="questions-contact"', false)
            ->assertSee('class="contact-map', false)
            ->assertSee("All NSIC-registered rice varieties. When you choose the variety you planted, iClimate estimates your harvest date from that variety's days to maturity for the season and planting method.", false)
            ->assertSee('href="mailto:mao_lian@yahoo.com"', false)
            ->assertSee('href="https://www.google.com/maps/dir/?api=1&amp;destination=Lian%20Municipal%20Hall%2C%20Lian%2C%20Batangas" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('Municipal Agriculture Office, Lian Municipal Hall, Poblacion, Lian, Batangas')
            ->assertSee('src="https://maps.google.com/maps?q=Lian%20Municipal%20Hall%2C%20Lian%2C%20Batangas&amp;ll=13.992,120.648&amp;z=11&amp;output=embed"', false)
            ->assertDontSee('officeMap', false);
    }

    public function test_every_route_used_by_the_new_views_exists(): void
    {
        $files = [
            resource_path('views/welcome.blade.php'),
            resource_path('views/components/public-nav.blade.php'),
            resource_path('views/auth/partials/register-form.blade.php'),
            ...File::glob(resource_path('views/landing/*.blade.php')),
        ];
        $names = [];
        foreach ($files as $file) {
            preg_match_all("/route\\('([^']+)'/", File::get($file), $matches);
            $names = [...$names, ...$matches[1]];
        }

        $this->assertNotEmpty($names);
        foreach (array_unique($names) as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] used in the landing views does not exist.");
        }
    }

    public function test_the_new_views_have_no_dead_links(): void
    {
        $files = [resource_path('views/welcome.blade.php'), resource_path('views/components/public-nav.blade.php'),
            resource_path('views/auth/partials/register-form.blade.php'), ...File::glob(resource_path('views/landing/*.blade.php'))];

        foreach ($files as $file) {
            $html = File::get($file);
            foreach (['href="#"', 'href=""', 'javascript:void', 'onclick=""'] as $dead) {
                $this->assertStringNotContainsString($dead, $html, basename($file)." contains {$dead}");
            }
        }
    }

    public function test_the_nav_on_other_public_pages_links_back_to_the_landing_anchors(): void
    {
        $html = $this->blade('<x-public-nav />');

        $html->assertSee('href="'.url('/').'#about"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.url('/').'#register"', false)   // Create account opens the landing card in that mode
            ->assertDontSee('href="'.route('register').'"', false)
            ->assertDontSee('aria-label="MAO advisories"', false);
    }
}
