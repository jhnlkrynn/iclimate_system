<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClimoraGreetingTest extends TestCase
{
    use RefreshDatabase;

    private function greetingFor(User $user): string
    {
        // The assistant is loaded when its button is first clicked (components/ai-chat-launcher), from this route.
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('data-ai-launch', false);
        $html = $this->get(route('ai-chat.widget'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="ic-ai-bubble">(.*?)</div>#s', $html);
        preg_match('#<div class="ic-ai-bubble">(.*?)</div>#s', $html, $m);

        return html_entity_decode(trim($m[1]));
    }

    public static function roles(): array
    {
        return [
            'farmer' => [User::ROLE_FARMER],
            'mao' => [User::ROLE_MAO],
            'it' => [User::ROLE_IT_EXPERT],
        ];
    }

    #[DataProvider('roles')]
    public function test_greets_each_role_by_first_name(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'name' => 'Luke Reyes', 'email' => 'private.luke@example.test']);

        $greeting = $this->greetingFor($user);

        $this->assertSame('Hi, Luke! How can I help you today?', $greeting);
        foreach (['Reyes', 'private.luke', '@example', $role, (string) $user->id.'?'] as $private) {
            $this->assertStringNotContainsString($private, $greeting);
        }
    }

    public function test_uses_a_single_word_name_and_ignores_extra_spaces(): void
    {
        $this->assertSame('Hi, Maria! How can I help you today?', $this->greetingFor(User::factory()->farmer()->create(['name' => 'Maria'])));
        $this->assertSame('Hi, Juan! How can I help you today?', $this->greetingFor(User::factory()->farmer()->create(['name' => "  Juan   dela Cruz "])));
    }

    public function test_falls_back_to_a_plain_greeting_when_name_is_missing(): void
    {
        $user = User::factory()->farmer()->create(['name' => '   ']);

        $this->assertSame('Hi! How can I help you today?', $this->greetingFor($user));
    }

    public function test_name_is_escaped_in_the_greeting(): void
    {
        $user = User::factory()->farmer()->create(['name' => '<script>alert(1)</script> Luke']);

        $html = $this->actingAs($user)->get(route('ai-chat.widget'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_guests_do_not_get_the_widget_or_a_greeting(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->get(route('ai-chat.widget'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertDontSee('ic-ai-bubble', false);
    }
}
