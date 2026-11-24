<?php

namespace Tests\Feature\Auth;

use App\Models\FarmerOrganization;
use App\Models\User;
use App\Services\Security\CaptchaService;
use Database\Seeders\FarmerOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertRedirect(url('/').'#register');
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('captcha.image', ['context' => 'register']), false);
        $response->assertDontSee('Farm location / barangay');
        $response->assertSee('At least 8 characters');
        $response->assertSee('Uppercase letter');
        $response->assertSee('Symbol');
        $response->assertSee("You'll add your barangay and association after you log in.", false);
    }

    public function test_registration_captcha_image_can_be_rendered(): void
    {
        $response = $this->get('/captcha/register');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $response->assertSee('<svg', false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'captcha_answer' => '7',
            'privacy_agreed' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'Account created. The Municipal Agriculture Office will review it; you can log in once it is approved.');   // Manuscript – Naicel: sign-ups wait for approval
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'barangay' => null,
        ]);
        $this->assertDatabaseHas('farmer_profiles', [
            'full_name' => 'Test User',
            'barangay' => null,
            'farmer_organization_id' => null,
        ]);
    }

    public function test_registration_allows_8_char_password_without_complexity_chips_blocking(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Simple Password User',
            'email' => 'simple-pass@example.com',
            'password' => 'myfarm2026',
            'password_confirmation' => 'myfarm2026',
            'captcha_answer' => '7',
            'privacy_agreed' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'simple-pass@example.com']);
    }

    public function test_registration_rejects_passwords_shorter_than_8_characters(): void
    {
        $short = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Short Password User',
            'email' => 'short-password@example.com',
            'password' => 'short12',
            'password_confirmation' => 'short12',
            'captcha_answer' => '7',
            'privacy_agreed' => '1',
        ]);

        $this->assertGuest();
        $short->assertSessionHasErrors('password');
    }

    public function test_registration_rejects_duplicate_email_and_keeps_entered_names(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/register')->withSession($this->captchaSession('register', '7'))->post('/register', [
            '_auth_form' => 'register',
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
            'privacy_agreed' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);

        $this->followRedirects($response)
            ->assertSee('The email has already been taken.')
            ->assertSee('value="Juan"', false)
            ->assertSee('value="Dela Cruz"', false);
    }

    public function test_registration_fails_when_captcha_is_missing(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_registration_fails_when_captcha_is_invalid(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay' => 'Balibago',
            'captcha_answer' => '8',
            'privacy_agreed' => '1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    /** A sign-up saves one user and one incomplete farmer profile (no farm yet) and waits for approval. */
    public function test_a_new_farmer_is_saved_once_with_an_incomplete_profile_and_waits_for_approval(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', $this->validSignUp());

        $response->assertRedirect('/');
        $response->assertSessionHasNoErrors();
        $this->assertGuest();

        $user = User::query()->where('email', 'new-farmer@example.com')->sole();
        $this->assertSame(User::ROLE_FARMER, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertNull($user->barangay);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertDatabaseCount('farmer_profiles', 1);
        $this->assertDatabaseHas('farmer_profiles', ['user_id' => $user->id, 'barangay' => null, 'farm_type' => null]);
        $this->assertSame(0, \App\Models\Farm::query()->where('user_id', $user->id)->count());
    }

    public function test_legacy_approval_setting_cannot_bypass_pending_registration(): void
    {
        config(['iclimate.farmer_approval' => false]);

        $this->withSession($this->captchaSession('register', '7'))->post('/register', $this->validSignUp())->assertRedirect('/');

        $user = User::query()->where('email', 'new-farmer@example.com')->sole();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->actingAs($user)->get(route($user->dashboardRoute()))->assertRedirect(route('login'));
        $this->actingAs($user)->getJson(route($user->dashboardRoute()))->assertUnauthorized();
    }

    /** Only farmers sign up here: a role sent with the form is ignored, never saved. */
    public function test_a_sign_up_can_never_create_a_mao_or_it_account(): void
    {
        foreach ([User::ROLE_MAO, 'IT Expert'] as $index => $role) {
            $this->withSession($this->captchaSession('register', '7'))
                ->post('/register', $this->validSignUp(['email' => "staff{$index}@example.com", 'role' => $role, 'status' => User::STATUS_ACTIVE]))
                ->assertRedirect('/');

            $this->assertSame(User::ROLE_FARMER, User::query()->where('email', "staff{$index}@example.com")->sole()->role);
        }

        $this->assertSame(0, User::query()->whereIn('role', [User::ROLE_MAO, 'IT Expert'])->count());
    }

    public function test_a_wrong_form_gets_validation_messages_and_saves_nothing(): void
    {
        $wrong = [
            'name' => ['name' => ''],
            'email' => ['email' => 'not-an-email'],
            'password' => ['password_confirmation' => 'Different123!'],
            'privacy_agreed' => ['privacy_agreed' => '0'],
        ];

        foreach ($wrong as $field => $change) {
            $response = $this->from('/register')->withSession($this->captchaSession('register', '7'))->post('/register', $this->validSignUp($change));

            $response->assertRedirect('/register');
            $response->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('farmer_profiles', 0);
    }

    /** Fields the form does not require may be left out or sent empty. */
    public function test_empty_optional_fields_do_not_stop_a_sign_up(): void
    {
        $this->withSession($this->captchaSession('register', '7'))
            ->post('/register', $this->validSignUp(['contact_number' => '', 'farm_area' => '', 'preferred_language' => '']))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new-farmer@example.com', 'contact_number' => null]);
        $this->assertDatabaseHas('farmer_profiles', ['full_name' => 'New Farmer', 'farmer_organization_id' => null]);
    }

    /** A wrong security answer sends the form back with the entered name and email kept. */
    public function test_entered_fields_survive_a_failed_submission(): void
    {
        $this->from('/register')->withSession($this->captchaSession('register', '7'))
            ->post('/register', $this->validSignUp(['_auth_form' => 'register', 'first_name' => 'New', 'last_name' => 'Farmer', 'captcha_answer' => '8']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('captcha_answer');

        $this->get('/register')->assertRedirect(url('/').'#register');
        $page = $this->get('/')->assertOk()->assertSee('data-auth-mode="register"', false);
        $page->assertSee('value="New"', false);
        $page->assertSee('value="Farmer"', false);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    private function validSignUp(array $change = []): array
    {
        return $change + [
            'name' => 'New Farmer',
            'email' => 'new-farmer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
            'privacy_agreed' => '1',
        ];
    }

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
