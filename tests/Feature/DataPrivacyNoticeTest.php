<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataPrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_mobile_signup_require_privacy_agreement_and_save_timestamp(): void
    {
        $captcha = [
            CaptchaService::SESSION_KEY => [
                'context' => 'register',
                'question' => 'What is 3 + 4?',
                'answer_hash' => Hash::make('7'),
                'created_at' => now()->timestamp,
            ],
        ];

        $this->from('/register')
            ->withSession($captcha)
            ->post('/register', [
                'name' => 'No Agree Farmer',
                'email' => 'no-agree@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'barangay' => 'Balibago',
                'captcha_answer' => '7',
            ])
            ->assertRedirect('/register')
            ->assertSessionHasErrors('privacy_agreed');

        $this->withSession($captcha)
            ->post('/register', [
                'name' => 'Agreed Web Farmer',
                'email' => 'agreed-web@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'barangay' => 'Balibago',
                'captcha_answer' => '7',
                'privacy_agreed' => '1',
            ])
            ->assertRedirect('/');

        $webUser = User::query()->where('email', 'agreed-web@example.com')->firstOrFail();
        $this->assertNotNull($webUser->privacy_agreed_at);

        $this->postJson('/api/mobile/register', [
            'name' => 'No Agree Mobile',
            'email' => 'no-agree-mobile@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'contact_number' => '09170000000',
            'barangay' => 'Prenza',
            'farm_type' => 'Irrigated',
        ])->assertUnprocessable()->assertJsonValidationErrors('privacy_agreed');

        $this->postJson('/api/mobile/register', [
            'name' => 'Agreed Mobile',
            'email' => 'agreed-mobile@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'contact_number' => '09170000000',
            'barangay' => 'Prenza',
            'farm_type' => 'Irrigated',
            'privacy_agreed' => true,
        ])->assertCreated();

        $mobileUser = User::query()->where('email', 'agreed-mobile@example.com')->firstOrFail();
        $this->assertNotNull($mobileUser->privacy_agreed_at);
    }

    public function test_privacy_notice_page_footer_and_profile_links_render_in_english_and_filipino(): void
    {
        $this->get(route('privacy.notice'))
            ->assertOk()
            ->assertSee('Data Privacy Notice (RA 10173)')
            ->assertSee('What information we collect')
            ->assertSee('Your rights / contact');

        $this->get(route('privacy.notice', ['lang' => 'fil']))
            ->assertOk()
            ->assertSee('Abiso sa Pagkapribado ng Datos (RA 10173)')
            ->assertSee('Anong impormasyon ang kinokolekta');

        $this->get('/')
            ->assertOk()
            ->assertSee(route('privacy.notice'));

        $farmer = User::factory()->farmer()->create(['email_verified_at' => now()]);
        $this->actingAs($farmer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('privacy.notice'));
    }
}
