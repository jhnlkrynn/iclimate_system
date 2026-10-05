<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\AI\BarangayInsightService;
use App\Services\AI\KnowledgeBaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Farmer-facing wording is short, plain and free of technical words, in English and Filipino. MAO and IT pages may
 * keep more detail; internal class, route and field names are not checked.
 */
class FarmerLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** Words a farmer should never have to read. */
    private const TECHNICAL = '/\b(precipitation|meteorolog\w*|algorithms?|classification|inference|dataset|synchroni[sz]\w*|aggregat\w*|computation|probability distribution|model confidence|confidence score|random forest|machine learning|training data|prediction engine|API|cache|query)\b/i';

    private const FARMER_PAGES = ['/farmer/dashboard', '/heat-map', '/planting-advisories', '/calendar', '/community-feed', '/messages',
        '/notifications', '/climate-records', '/weather-predictions', '/rice-productions', '/profile'];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        config(['iclimate.python_binary' => '/nonexistent/python']);
    }

    public function test_farmer_pages_show_no_technical_words_in_either_language(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Binubusan']);

        $found = [];
        foreach (['en', 'fil'] as $language) {
            $farmer->forceFill(['preferred_language' => $language])->save();
            foreach (self::FARMER_PAGES as $path) {
                $html = (string) $this->actingAs($farmer)->get($path)->assertOk()->getContent();
                // What a farmer reads: page text and labels (aria-label, title, placeholder), not script or style code.
                $html = preg_replace('#<(script|style)\b.*?</\1>#si', ' ', $html);
                preg_match_all('#\b(?:aria-label|title|placeholder|alt)="([^"]*)"#', $html, $labels);
                $text = html_entity_decode(strip_tags($html)).' '.implode(' ', $labels[1]);
                if (preg_match_all(self::TECHNICAL, $text, $matches)) {
                    $found[] = "{$language} {$path}: ".implode(', ', array_unique($matches[0]));
                }
            }
        }

        $this->assertSame([], $found, 'Technical words on farmer pages');
    }

    public function test_risk_levels_are_explained_in_plain_words(): void
    {
        $plain = [
            "Low: 'Conditions are generally good.'",
            "Moderate: 'Conditions need some attention.'",
            "High: 'Conditions may affect farm work.'",
            "Severe: 'Conditions need close attention.'",
        ];
        $map = file_get_contents(resource_path('js/barangay-risk-map.js'));
        foreach ($plain as $line) {
            $this->assertStringContainsString($line, $map);
        }
        $this->assertStringContainsString("meaning: 'Conditions need some attention.'", file_get_contents(resource_path('js/risk-overview.js')));
    }

    public function test_status_messages_are_simple(): void
    {
        $js = file_get_contents(resource_path('js/barangay-risk-map.js')).file_get_contents(resource_path('js/barangay-insight.js'));

        $this->assertStringContainsString("'Risk is being updated.'", $js);
        $this->assertStringContainsString("'Latest weather is temporarily unavailable.'", $js);
        $this->assertStringNotContainsString('Risk assessment is being refreshed', $js);
        $this->assertStringNotContainsString('Risk calculated:', $js);
        $this->assertSame('Latest weather is temporarily unavailable.', __('farmer.weather_card.error', [], 'en'));
    }

    public function test_ai_insight_is_one_sentence_and_one_action(): void
    {
        $this->assertSame(1, BarangayInsightService::MAX_INSIGHT_SENTENCES);
        $this->assertSame(1, BarangayInsightService::MAX_ACTION_SENTENCES);

        $context = ['risk_level' => 'Moderate', 'humidity_percent' => 91, 'weather_status' => 'current'];
        $check = fn (string $insight, string $action) => BarangayInsightService::check(['insight' => $insight, 'action' => $action], $context);

        $this->assertNotNull($check('Rain is expected to stay low today.', 'Check the soil before watering.'));
        $this->assertNotNull($check('Kaunti lang ang ulan na inaasahan ngayon.', 'Suriin muna ang lupa bago magdilig.'));
        $this->assertSame('Rain is low.', $check('Rain is low. The air is humid.', 'Check the soil before watering.')['insight'], 'A second sentence is cut, not rejected.');
        $this->assertNull($check('The API shows rain is low today.', 'Check the soil.'), 'Technical word.');
        $this->assertNull($check('Machine learning expects little rain today.', 'Check the soil.'), 'Technical word.');
    }

    public function test_climora_explains_how_it_works_without_technical_words(): void
    {
        $kb = app(KnowledgeBaseService::class);
        foreach (['how do you predict weather' => 'English', 'how do you predict rice yield' => 'English', 'paano mo pinipredict ang weather' => 'Tagalog'] as $question => $language) {
            $answer = (string) data_get($kb->builtInAnswer($question, 'system_help', $language), 'answer');
            $this->assertNotSame('', $answer, $question);
            $this->assertDoesNotMatchRegularExpression('/\b(model|machine[- ]learning|trained|features?|inputs?)\b/i', $answer, $question);
        }
    }
}
