<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlainLanguagePagesTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = ['Random Forest', 'Python', 'Open-Meteo', 'analytics run', 'Traceback', '.pkl'];

    private function visibleText(string $html): string
    {
        return strip_tags(preg_replace('#<(script|style)\b.*?</\1>#is', '', $html));
    }

    public function test_advisory_and_report_pages_hide_technical_terms_for_farmer_and_mao(): void
    {
        foreach ([User::ROLE_FARMER, User::ROLE_MAO] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['/planting-advisories', '/reports'] as $url) {
                $response = $this->actingAs($user)->get($url);
                if ($response->status() === 403) {
                    $this->assertSame(User::ROLE_FARMER, $role);
                    continue;
                }
                $response->assertOk();
                $text = $this->visibleText($response->getContent());
                foreach (self::FORBIDDEN as $term) {
                    $this->assertStringNotContainsStringIgnoringCase($term, $text, "$role $url shows $term");
                }
            }
        }
    }

    public function test_management_page_stays_plain_for_mao(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $response = $this->actingAs($user)->get('/management/advisories');
        $response->assertOk();
        $text = $this->visibleText($response->getContent());
        foreach (self::FORBIDDEN as $term) {
            $this->assertStringNotContainsStringIgnoringCase($term, $text);
        }
    }

    public function test_farmer_cannot_open_advisory_management(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($user)->get('/management/advisories')->assertForbidden();
    }

    public function test_failed_status_shows_friendly_message_without_raw_error(): void
    {
        $run = new \stdClass();
        $run->status = 'failed';
        $run->error = 'Traceback (most recent call last): Python model.pkl';
        $html = view('advisories._analytics-status', ['analyticsRun' => $run])->render();

        $this->assertStringContainsString('could not be prepared', $html);
        $this->assertStringNotContainsString('Traceback', $html);
        $this->assertStringNotContainsString('.pkl', $html);
    }
}
