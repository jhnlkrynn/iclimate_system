<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The blue government strip ("Republic of the Philippines · Province of Batangas · Municipality of Lian") was removed from every page. */
class GovernmentStripRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function assertNoStrip(string $html, string $page): void
    {
        $this->assertStringNotContainsString('Republic of the Philippines', $html, "{$page} still shows the government strip");
        $this->assertStringNotContainsString('Province of Batangas', $html, "{$page} still shows the government strip");
        $this->assertStringNotContainsString('gov-strip', $html, "{$page} still has the government strip markup");
    }

    public function test_public_pages_have_no_government_strip(): void
    {
        // /login and /register only open the landing card (/#login, /#register).
        $this->get(route('login'))->assertRedirect(url('/').'#login');
        $this->get(route('register'))->assertRedirect(url('/').'#register');
        foreach (['/', route('password.request')] as $url) {
            $this->assertNoStrip($this->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function test_each_role_dashboard_has_no_government_strip(): void
    {
        foreach ([User::ROLE_FARMER => 'farmer.dashboard', User::ROLE_MAO => 'mao.dashboard', User::ROLE_IT_EXPERT => 'admin.dashboard'] as $role => $dashboard) {
            $user = User::factory()->create(['role' => $role]);
            $html = $this->actingAs($user)->get(route($dashboard))->assertOk()->getContent();

            $this->assertNoStrip($html, $dashboard);
            // The office name in the header is ordinary content and stays.
            $this->assertStringContainsString('Municipal Agriculture Office', $html);
        }
    }
}
