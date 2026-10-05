<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Every main page of every role opens while all optional services are down: weather, PAGASA and the AI provider
 * answer 500, Python cannot be found, and there are no prepared predictions, rice-production records, computed
 * risk ratings or climate records (a fresh install). Optional parts show their own "unavailable" state; the page
 * itself is never a 500.
 */
class SystemPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Pages that intentionally answer with a redirect instead of a page of their own. */
    private const REDIRECTS = [
        '/ai-farming-assistant',      // Climora lives in the widget on every page
        '/announcements',             // announcements are part of the Community Feed
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        config([
            'iclimate.python_binary' => '/nonexistent/python',
            'services.groq.enabled' => true,
            'services.groq.key' => 'test-key',
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'barangay' => $role === User::ROLE_FARMER ? 'Binubusan' : null]);
        if ($role === User::ROLE_FARMER) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Binubusan']);
        }

        return $user;
    }

    /** @param  array<string, string>  $pages  path => text the page must show */
    private function assertPagesOpen(User $user, array $pages): void
    {
        $problems = [];
        foreach ($pages as $path => $expected) {
            $response = $this->actingAs($user)->get($path);
            $status = $response->getStatusCode();

            if (in_array($path, self::REDIRECTS, true)) {
                if (! $response->isRedirect()) {
                    $problems[] = "{$path}: expected a redirect, got {$status}";
                }

                continue;
            }
            if ($status !== 200) {
                $problems[] = "{$path}: {$status}".($status >= 500 ? ' ('.($response->exception ? $response->exception::class.': '.$response->exception->getMessage() : 'server error').')' : '');

                continue;
            }
            if (! str_contains(html_entity_decode(strip_tags((string) $response->getContent())), $expected)) {
                $problems[] = "{$path}: 200 but \"{$expected}\" is missing";
            }
        }

        $this->assertSame([], $problems, $user->role.' pages that did not open cleanly');
    }

    public function test_every_farmer_page_opens_with_optional_services_down(): void
    {
        $this->assertPagesOpen($this->user(User::ROLE_FARMER), [
            '/farmer/dashboard' => 'Farm Risk in Your Barangay',
            '/heat-map' => 'Risk Map',
            '/planting-advisories' => 'Advisories',
            '/calendar' => 'Calendar',
            '/community-feed' => 'Community',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
            '/climate-records' => 'Climate Records',
            '/weather-predictions' => 'Prediction',
            '/rice-productions' => 'Rice',
            '/field-reports' => 'Report',
            '/announcements' => 'Announcements',
            '/farmer/boundary' => 'farm',
            '/profile' => 'Profile',
            '/ai-farming-assistant' => '',
        ]);
    }

    public function test_every_mao_page_opens_with_optional_services_down(): void
    {
        $this->assertPagesOpen($this->user(User::ROLE_MAO), [
            '/mao/dashboard' => 'Municipal Risk Overview',
            '/mao/operations' => 'Barangay',
            '/heat-map' => 'Risk Map',
            '/calendar' => 'Calendar',
            '/climate-records' => 'Climate Records',
            '/planting-advisories' => 'Advisories',
            '/management/advisories' => 'Advisor',
            '/farmer-profiles' => 'Farmer',
            '/weather-predictions' => 'Prediction',
            '/rice-productions' => 'Rice',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
            '/ai-admin/topics' => 'Topic',
            '/field-reports' => 'Report',
            '/community-feed' => 'Community',
            '/announcements' => 'Announcements',
            '/reports' => 'Report',
            '/profile' => 'Profile',
        ]);
    }

    public function test_every_it_page_opens_with_optional_services_down(): void
    {
        $this->assertPagesOpen($this->user(User::ROLE_IT_EXPERT), [
            '/admin/dashboard' => 'System',
            '/users' => 'Users',
            '/users/create' => 'User',
            '/weather-predictions' => 'Prediction',
            '/testing/candidate-rice-yield' => 'Candidate',
            '/planting-advisories' => 'Advisories',
            '/reports' => 'Report',
            '/system-logs' => 'Log',
            '/system-health' => 'Health',
            '/system-health/model-registry' => 'Model',
            '/model-evaluation' => 'Model',
            '/heat-map' => 'Risk Map',
            '/ai-admin/topics' => 'Topic',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
            '/profile' => 'Profile',
        ]);
    }

    public function test_climora_answers_without_the_ai_provider_weather_or_prepared_predictions(): void
    {
        $farmer = $this->user(User::ROLE_FARMER);

        foreach (['en' => ["What's the weather right now?", 'Will it rain tomorrow?', 'What is the rainfall forecast for next month?'],
            'tl' => ['Ano ang panahon ngayon?', 'Gaano karami ang ulan sa susunod na buwan?']] as $language => $questions) {
            foreach ($questions as $question) {
                $chat = $this->actingAs($farmer)->postJson(route('ai-chat.message'), ['question' => $question, 'language' => $language])
                    ->assertOk()->json('chat');

                $this->assertNotSame('', trim((string) $chat['answer']), $question);
                $this->assertDoesNotMatchRegularExpression('/\b0(\.0)?\s?(mm|°C)\b/', (string) $chat['answer'], 'no made-up zero for: '.$question);
            }
        }
    }
}
