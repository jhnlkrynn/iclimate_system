<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\AdvisoryActionRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdvisoryActionRulesTest extends TestCase
{
    use RefreshDatabase;

    private AdvisoryActionRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = app(AdvisoryActionRules::class);
    }

    public function test_each_hazard_produces_one_to_three_short_verb_first_actions(): void
    {
        $cases = [
            'typhoon' => ['Typhoon Signal No. 2 approaching Batangas', 'Harvest mature palay'],
            'flood' => ['Heavy rainfall and flooding warning in Lian', 'Open the canals'],
            'thunder' => ['THE REST OF THE COUNTRY WILL HAVE PARTLY CLOUDY TO CLOUDY SKIES WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.', "Don't spray or fertilize"],
            'heat' => ['Hot and dry weather expected this week', 'Water the field'],
            'fair' => ['Fair weather with clear skies today', 'Spray, fertilize, or dry palay'],
        ];

        foreach ($cases as $hazard => [$text, $expectedSubstring]) {
            $actions = $this->rules->forAttributes($text, 'moderate', 'en');
            $this->assertGreaterThanOrEqual(1, count($actions), "Hazard {$hazard} returned no actions");
            $this->assertLessThanOrEqual(3, count($actions), "Hazard {$hazard} returned more than 3 actions");
            $this->assertStringContainsString($expectedSubstring, implode(' ', $actions));
            $this->assertStringNotContainsStringIgnoringCase('Check field moisture', implode(' ', $actions));

            foreach ($actions as $action) {
                $this->assertLessThanOrEqual(12, str_word_count($action), "Action too long: {$action}");
            }
        }
    }

    public function test_priority_order_picks_most_urgent_hazard_first(): void
    {
        // Typhoon beats flood, thunder, heat, fair.
        $typhoonAndHeat = $this->rules->forAttributes('Hot morning then Typhoon Signal No. 1 and heavy rain flooding', 'critical', 'en');
        $this->assertStringContainsString('Harvest mature palay now', implode(' ', $typhoonAndHeat));

        // Flood / heavy rain beats thunder and heat.
        $floodAndThunder = $this->rules->forAttributes('Heavy rainfall flooding and isolated thunderstorms', 'high', 'en');
        $this->assertStringContainsString('Open the canals to drain the field.', implode(' ', $floodAndThunder));

        // Thunder beats heat and fair.
        $thunderAndFair = $this->rules->forAttributes('Partly cloudy with isolated thunderstorms in the afternoon', 'moderate', 'en');
        $this->assertStringContainsString("Don't spray or fertilize if rain is near.", implode(' ', $thunderAndFair));
    }

    public function test_filipino_output_uses_simple_farming_terms(): void
    {
        $thunderFil = implode(' ', $this->rules->forAttributes('Isolated rainshowers and thunderstorms', 'moderate', 'fil'));
        $this->assertStringContainsString('kanal', $thunderFil);
        $this->assertStringContainsString('kulog', $thunderFil);

        $floodFil = implode(' ', $this->rules->forAttributes('Heavy rain and flooding warning', 'high', 'fil'));
        $this->assertStringContainsString('punla', $floodFil);
        $this->assertStringContainsString('kanal', $floodFil);

        $heatFil = implode(' ', $this->rules->forAttributes('Hot and dry weather', 'moderate', 'fil'));
        $this->assertStringContainsString('Magpatubig', $heatFil);
    }

    public function test_crop_stage_adds_or_replaces_an_action(): void
    {
        $actions = $this->rules->forAttributes('Fair weather today', 'low', 'en', 'flowering');
        $this->assertContains('Keep water in the field during flowering.', $actions);

        $seedlingFlood = $this->rules->forAttributes('Heavy rain and flood risk', 'high', 'en', 'seedling');
        $this->assertCount(3, $seedlingFlood);
        $this->assertContains('Protect young punla from deep standing water.', $seedlingFlood);
    }

    public function test_unmatched_text_uses_safe_default_by_risk_level(): void
    {
        $high = implode(' ', $this->rules->forAttributes('General municipal notice', 'high', 'en'));
        $low = implode(' ', $this->rules->forAttributes('General municipal notice', 'low', 'en'));

        $this->assertStringContainsString('Pause spraying', $high);
        $this->assertStringContainsString('Walk your field', $low);
        $this->assertStringNotContainsStringIgnoringCase('Check field moisture', $high.' '.$low);
    }

    public function test_advisories_page_uses_sentence_case_and_merges_near_identical_national_outlooks(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $from = now()->startOfDay();
        $until = now()->addDays(7)->endOfDay();

        PlantingAdvisory::query()->create([
            'title' => 'National outlook (includes Lian, Batangas)',
            'summary' => 'THE REST OF THE COUNTRY WILL HAVE PARTLY CLOUDY TO CLOUDY SKIES WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'content' => 'THE REST OF THE COUNTRY WILL HAVE PARTLY CLOUDY TO CLOUDY SKIES WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'message' => 'THE REST OF THE COUNTRY WILL HAVE PARTLY CLOUDY TO CLOUDY SKIES WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'recommended_action' => 'Check field moisture and the latest forecast before irrigating, fertilizing, or spraying.',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'severity' => 'low',
            'priority' => 25,
            'source' => PlantingAdvisory::SOURCE_PAGASA_WEEKLY_OUTLOOK,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => $from,
            'valid_until' => $until,
            'published_at' => now(),
            'posted_by' => $mao->id,
            'metadata' => ['scope_name' => 'National outlook (includes Lian, Batangas)'],
        ]);

        PlantingAdvisory::query()->create([
            'title' => 'National outlook (includes Lian, Batangas)',
            'summary' => 'THE REST OF THE COUNTRY WILL BE PARTLY CLOUDY TO CLOUDY WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'content' => 'THE REST OF THE COUNTRY WILL BE PARTLY CLOUDY TO CLOUDY WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'message' => 'THE REST OF THE COUNTRY WILL BE PARTLY CLOUDY TO CLOUDY WITH ISOLATED RAINSHOWERS OR THUNDERSTORMS.',
            'recommended_action' => 'Check field moisture and the latest forecast before irrigating, fertilizing, or spraying.',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'severity' => 'low',
            'priority' => 25,
            'source' => PlantingAdvisory::SOURCE_PAGASA_WEEKLY_OUTLOOK,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => $from,
            'valid_until' => $until,
            'published_at' => now(),
            'posted_by' => $mao->id,
            'metadata' => ['scope_name' => 'National outlook (includes Lian, Batangas)'],
        ]);

        // Refresh existing active advisories via artisan command.
        Artisan::call('iclimate:refresh-advisory-actions');

        $html = $this->actingAs($mao)
            ->get(route('planting-advisories.index', ['type' => 'climate']))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-adv-item="'));
        $this->assertStringContainsString('The rest of the country will', $html);
        $this->assertStringNotContainsString('THE REST OF THE COUNTRY', $html);
        $this->assertStringContainsString("Don&#039;t spray or fertilize if rain is near.", $html);
        $this->assertStringNotContainsString('Check field moisture and the latest forecast', $html);
    }
}
