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
        $this->seed(FarmerOrganizationSeeder::class);

        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee(route('captcha.image', ['context' => 'register']), false);
        $response->assertSee('Balibago Farmers Association');
        $response->assertSee('Farm location / barangay');
        $response->assertSee('At least 8 characters');
        $response->assertSee('Uppercase letter');
        $response->assertSee('Symbol');
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
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'Account created. The Municipal Agriculture Office will review it; you can log in once it is approved.');   // Manuscript – Naicel: sign-ups wait for approval
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'barangay' => 'Balibago',
        ]);
        $this->assertDatabaseHas('farmer_profiles', [
            'full_name' => 'Test User',
            'barangay' => 'Balibago',
            'farmer_organization_id' => null,
        ]);
    }

    public function test_registration_assigns_barangay_from_selected_organization(): void
    {
        $this->seed(FarmerOrganizationSeeder::class);
        $organization = FarmerOrganization::query()
            ->where('name', 'Cuaco Irrigator\'s Association')
            ->firstOrFail();

        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Org User',
            'email' => 'org-user@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'farmer_organization_id' => $organization->id,
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'Account created. The Municipal Agriculture Office will review it; you can log in once it is approved.');   // Manuscript – Naicel: sign-ups wait for approval
        $this->assertDatabaseHas('users', [
            'email' => 'org-user@example.com',
            'barangay' => 'Binubusan',
        ]);
        $this->assertDatabaseHas('farmer_profiles', [
            'full_name' => 'Org User',
            'farmer_organization_id' => $organization->id,
            'barangay' => 'Binubusan',
            'farm_type' => 'Irrigated',
        ]);
    }

    public function test_registration_requires_manual_barangay_when_no_organization_is_selected(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'No Barangay User',
            'email' => 'no-barangay@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'captcha_answer' => '7',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('barangay');
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Weak Password User',
            'email' => 'weak-password@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }

    public function test_registration_rejects_duplicate_email_and_keeps_entered_names(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from('/register')->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay' => 'Balibago',
            'captcha_answer' => '7',
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
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function test_registration_rejects_a_generic_poblacion_and_offers_only_official_barangay_names(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Pob Farmer',
            'email' => 'pob@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay' => 'Poblacion',   // Lian has five Poblacion barangays: a bare "Poblacion" is ambiguous
            'captcha_answer' => '7',
        ]);

        $response->assertSessionHasErrors('barangay');
        $this->assertDatabaseMissing('users', ['email' => 'pob@example.com']);

        $offered = $this->get('/register')->viewData('manualBarangays');
        $this->assertNotEmpty($offered);
        $this->assertSame([], array_values(array_diff($offered, \App\Support\LianBarangays::all())), 'Every registration choice must be an official Lian barangay name.');
    }

    /** A sign-up saves one user, one farmer profile and one first farm, and nothing twice (2026-10-08). */
    public function test_a_new_farmer_is_saved_once_with_a_profile_and_a_first_farm_and_waits_for_approval(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', $this->validSignUp());

        $response->assertRedirect('/');
        $response->assertSessionHasNoErrors();
        $this->assertGuest();

        $user = User::query()->where('email', 'new-farmer@example.com')->sole();
        $this->assertSame(User::ROLE_FARMER, $user->role);
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertDatabaseCount('farmer_profiles', 1);
        $this->assertDatabaseHas('farmer_profiles', ['user_id' => $user->id, 'barangay' => 'Balibago', 'farm_type' => 'Irrigated']);
        $this->assertSame(1, \App\Models\Farm::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('farms', ['user_id' => $user->id, 'barangay' => 'Balibago', 'is_primary' => true]);
    }

    public function test_with_approval_switched_off_a_new_farmer_is_active_and_can_log_in_and_open_the_dashboard(): void
    {
        config(['iclimate.farmer_approval' => false]);

        $this->withSession($this->captchaSession('register', '7'))->post('/register', $this->validSignUp())->assertRedirect('/');

        $user = User::query()->where('email', 'new-farmer@example.com')->sole();
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
        $this->actingAs($user)->get(route($user->dashboardRoute()))->assertOk();
        $this->actingAs($user)->get(route($user->dashboardRoute()))->assertOk();   // and again on refresh
    }

    /** Only farmers sign up here: a role sent with the form is ignored, never saved. */
    public function test_a_sign_up_can_never_create_a_mao_or_it_account(): void
    {
        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $index => $role) {
            $this->withSession($this->captchaSession('register', '7'))
                ->post('/register', $this->validSignUp(['email' => "staff{$index}@example.com", 'role' => $role, 'status' => User::STATUS_ACTIVE]))
                ->assertRedirect('/');

            $this->assertSame(User::ROLE_FARMER, User::query()->where('email', "staff{$index}@example.com")->sole()->role);
        }

        $this->assertSame(0, User::query()->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])->count());
    }

    public function test_a_wrong_form_gets_validation_messages_and_saves_nothing(): void
    {
        $wrong = [
            'name' => ['name' => ''],
            'email' => ['email' => 'not-an-email'],
            'password' => ['password_confirmation' => 'Different123!'],
            'barangay' => ['barangay' => 'Not A Barangay'],
            'farmer_organization_id' => ['farmer_organization_id' => 999999],
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
            ->post('/register', $this->validSignUp(['farmer_organization_id' => '', 'contact_number' => '', 'farm_area' => '', 'preferred_language' => '']))
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new-farmer@example.com', 'contact_number' => null]);
        $this->assertDatabaseHas('farmer_profiles', ['full_name' => 'New Farmer', 'farmer_organization_id' => null]);
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
