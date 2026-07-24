<?php

namespace Tests\Feature\Auth;

use App\Services\Security\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee(route('captcha.image', ['context' => 'register']), false);
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
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha_answer' => '7',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('farmer.dashboard', absolute: false));
    }

    public function test_registration_fails_when_captcha_is_missing(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_registration_fails_when_captcha_is_invalid(): void
    {
        $response = $this->withSession($this->captchaSession('register', '7'))->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'captcha_answer' => '8',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('captcha_answer');
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
