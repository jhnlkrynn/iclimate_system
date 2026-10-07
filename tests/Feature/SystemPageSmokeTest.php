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
        '/ai-admin/topics',           // Chatbot Knowledge is hidden from the interface (CHATBOT_KNOWLEDGE_UI=false): opens the dashboard
        '/mao/operations',            // the Operations page was removed; its address opens the MAO dashboard (2026-10)
        '/management/advisories',     // Advisory Management moved into the Advisories page (2026-10)
        '/ai-farming-assistant',      // Climora lives in the widget on every page
        '/announcements',             // announcements are part of the Community Feed
        '/testing/candidate-rice-yield', // the candidate model test page moved to Model Evaluation (2026-10)
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

    /**
     * @param  array<string, string>  $pages  path => text the page must show
     * @param  array<int, string>  $alsoRedirects  paths that answer this role with a redirect
     */
    private function assertPagesOpen(User $user, array $pages, array $alsoRedirects = []): void
    {
        $problems = [];
        foreach ($pages as $path => $expected) {
            $response = $this->actingAs($user)->get($path);
            $status = $response->getStatusCode();

            if (in_array($path, [...self::REDIRECTS, ...$alsoRedirects], true)) {
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
        $farmer = $this->user(User::ROLE_FARMER);
        $this->assertPagesOpen($farmer, $pages = [
            // Farmer Home's barangay risk card (2026-10). Community, Climate Records, Rice Production and the farm map
            // are no longer farmer pages; they redirect (FarmerPagesSimplificationTest).
            '/farmer/dashboard' => 'Barangay Risk',
            '/heat-map' => 'Heat Map',
            '/planting-advisories' => 'Advisories',
            '/calendar' => 'Calendar',
            '/farmer/my-farm' => 'My Farm',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
            '/weather-predictions' => 'Forecast',             // the farmer's Forecast page (2026-10)
            '/profile' => 'Profile',
            '/ai-farming-assistant' => '',
        ]);

        // A missing translation must never reach a farmer as a raw key such as "farmer.about.title".
        $rawKeys = [];
        foreach (array_keys($pages) as $path) {
            $text = html_entity_decode(strip_tags((string) $this->actingAs($farmer)->get($path)->getContent()));
            if (preg_match_all('/\bfarmer\.[a-z_]+\.[a-z_.]+\b/', $text, $matches)) {
                $rawKeys[$path] = array_values(array_unique($matches[0]));
            }
        }
        $this->assertSame([], $rawKeys, 'Farmer pages showing untranslated keys');
    }

    public function test_every_mao_page_opens_with_optional_services_down(): void
    {
        $this->assertPagesOpen($this->user(User::ROLE_MAO), [
            '/mao/dashboard' => 'Needs Action',
            '/mao/operations' => 'Barangay',     // removed page: redirects (see REDIRECTS)
            '/heat-map' => 'Heat Map',
            '/calendar' => 'Calendar',
            '/climate-records' => 'Climate Records',
            '/planting-advisories' => 'Advisories',
            '/management/advisories' => 'Advisor',
            '/farmer-profiles' => 'Farmer',
            '/weather-predictions' => 'Prediction',
            '/rice-productions' => 'Rice',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
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
            '/heat-map' => 'Heat Map',
            '/ai-admin/topics' => 'Topic',
            '/messages' => 'Messages',
            '/notifications' => 'Notifications',
            '/profile' => 'Profile',
        ], ['/weather-predictions', '/planting-advisories', '/reports', '/heat-map']);   // not IT pages (2026-10): they open the IT dashboard
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
