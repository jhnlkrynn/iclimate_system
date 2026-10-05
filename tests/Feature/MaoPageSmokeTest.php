<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaoPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'service temporarily down'], 503)]);
        config([
            'iclimate.python_binary' => '/nonexistent/python',
            'services.groq.enabled' => false,
        ]);
    }

    private function maoUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function test_all_major_mao_pages_open_with_200_and_identifying_content(): void
    {
        $user = $this->maoUser();

        $routes = [
            '/mao/dashboard' => 'Municipal Risk Overview',
            '/mao/operations' => 'Barangay',
            '/heat-map' => 'Risk Map',
            '/calendar' => 'Calendar',
            '/climate-records' => 'Climate Records',
            '/planting-advisories' => 'Advisories',
            '/management/advisories' => 'Advisory',
            '/farmer-profiles' => 'Farmer',
            '/weather-predictions' => 'Prediction',
            '/messages' => 'Messages',
            '/ai-admin/topics' => 'Topic',
            '/profile' => 'Profile',
            '/notifications' => 'Notifications',
            '/rice-productions' => 'Rice',
        ];

        foreach ($routes as $uri => $identifyingText) {
            $response = $this->actingAs($user)->get($uri);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Failed asserting 200 on MAO route {$uri}. Got {$response->getStatusCode()}"
            );

            $content = html_entity_decode(strip_tags((string) $response->getContent()));
            $this->assertTrue(
                str_contains($content, $identifyingText),
                "Failed asserting content '{$identifyingText}' on MAO route {$uri}"
            );
        }
    }
}
