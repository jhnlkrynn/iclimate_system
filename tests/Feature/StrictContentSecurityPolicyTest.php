<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Deployment audit hardening 2026-11-20: scripts run only with the response's nonce; no inline handlers. */
class StrictContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function nonce(string $csp): string
    {
        $this->assertSame(1, preg_match("/script-src 'nonce-([A-Za-z0-9+\\/=_-]+)' 'strict-dynamic'/", $csp, $m), $csp);

        return $m[1];
    }

    public function test_every_script_on_a_page_carries_this_responses_nonce(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        \App\Models\FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Binubusan', 'farm_area' => 2.0, 'farm_type' => 'Irrigated']);
        \Illuminate\Support\Facades\Queue::fake();
        $pages = ['/' => null, route('farmer.dashboard') => $farmer, route('weather-predictions.index') => $farmer];
        $seen = [];
        foreach ($pages as $url => $user) {
            $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);
            $response->assertOk();
            $nonce = $this->nonce((string) $response->headers->get('Content-Security-Policy'));
            $seen[] = $nonce;
            preg_match_all('/<script\b(?![^>]*type="application\/json")[^>]*>/i', $response->getContent(), $tags);
            $this->assertNotEmpty($tags[0], $url);
            foreach ($tags[0] as $tag) {
                $this->assertStringContainsString('nonce="'.$nonce.'"', $tag, $url.': '.$tag);
            }
            $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|load|input)="/i', $response->getContent(), $url.': inline handler');
        }
        $this->assertCount(count($seen), array_unique($seen), 'a fresh nonce per response');
    }

    public function test_the_policy_blocks_inline_handlers_plugins_and_foreign_framing(): void
    {
        $csp = (string) $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString("'unsafe-eval'", $csp, 'Alpine is the CSP build; nothing may evaluate strings');
        foreach (["default-src 'self'", "script-src-attr 'none'", "object-src 'none'", "frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
    }

    public function test_the_rollback_switch_restores_the_previous_policy(): void
    {
        config(['security.csp_strict' => false]);
        $response = $this->get('/login');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self' https: data: blob: ws: wss: 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString('nonce-', $csp);
        $this->assertStringNotContainsString('nonce="', $response->getContent());
    }
}
