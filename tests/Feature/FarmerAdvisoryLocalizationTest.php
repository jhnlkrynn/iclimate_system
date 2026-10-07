<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\PagasaBulletinParser;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Farmer Advisories are strictly for Lian, Batangas: the farmer's barangay first, then other Lian barangays where
 * something is happening, then all of Lian, then wider PAGASA bulletins that include Lian. Bulletins for other
 * Batangas towns are never shown, and PAGASA is never credited with a barangay it did not name.
 */
class FarmerAdvisoryLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->author = User::factory()->maoPersonnel()->create();
    }

    private function farmer(?string $barangay): User
    {
        return User::factory()->farmer()->create(['barangay' => $barangay]);
    }

    private function local(string $title, ?string $barangay, string $severity = 'information', array $extra = []): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(array_merge([
            'title' => $title, 'content' => $title.' details.', 'message' => $title.' details.', 'summary' => $title.' summary.',
            'type' => 'Climate', 'advisory_type' => 'climate', 'severity' => $severity,
            'priority' => ['information' => 10, 'low' => 25, 'moderate' => 55, 'high' => 80, 'critical' => 100][$severity],
            'target_barangay' => $barangay, 'target_scope' => $barangay ? 'barangay' : 'municipality',
            'source' => 'Open-Meteo + iClimate Local Guidance', 'generated_automatically' => true, 'status' => 'published',
            'valid_from' => now()->subHour(), 'valid_until' => now()->addHours(6), 'published_at' => now()->subHour(),
            'posted_by' => $this->author->id,
            'metadata' => ['source_type' => 'iclimate', 'scope_type' => $barangay ? 'barangay' : 'municipality'],
        ], $extra));
    }

    private function pagasa(string $title, string $text, string $scopeType, string $scopeName, array $barangays = []): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create([
            'title' => $title, 'content' => $text, 'message' => $text, 'summary' => $text,
            'type' => 'Climate', 'advisory_type' => 'climate', 'severity' => 'moderate', 'priority' => 60,
            'target_barangay' => count($barangays) === 1 ? $barangays[0] : null, 'target_scope' => $barangays ? 'barangay' : 'municipality',
            'source' => PlantingAdvisory::SOURCE_PAGASA_ONLINE_ADVISORY, 'source_url' => 'https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd',
            'generated_automatically' => true, 'status' => 'published',
            'valid_from' => now()->subHour(), 'valid_until' => now()->addHours(6), 'published_at' => now()->subHours(2),
            'posted_by' => $this->author->id,
            'metadata' => ['official_source' => true, 'is_official' => true, 'source_type' => 'pagasa', 'scope_type' => $scopeType,
                'scope_name' => $scopeName, 'pagasa_barangay_matches' => $barangays, 'fetched_at' => now()->subMinutes(5)->toIso8601String()],
        ]);
    }

    /** One realistic mix of advisories for every test. */
    private function seedAdvisories(): void
    {
        $this->local('San Diego Field Check', 'San Diego');
        $this->local('Matabungkay Heavy Rain Advisory', 'Matabungkay', 'high');
        $this->local('Balibago Routine Advice', 'Balibago', 'low');
        $this->local('Prenza MAO Notice', 'Prenza', 'information', ['source' => 'MAO', 'generated_automatically' => false, 'metadata' => []]);
        $this->local('Lian Drainage Reminder', null, 'moderate');
        $this->pagasa('Rainfall Advisory No. 3', 'Rainfall Advisory: Lian, Batangas barangays Matabungkay and Binubusan may experience moderate rain.', 'barangay', 'Binubusan, Matabungkay, Lian', ['Binubusan', 'Matabungkay']);
        $this->pagasa('Batangas Provincial Weather Forecast', 'Partly cloudy skies with isolated rainshowers.', 'province', 'Lian, Batangas · Province-wide advisory');
        // Stored before the fix: only other Batangas towns are named, so it must not reach Lian farmers.
        $this->pagasa('Thunderstorm Advisory No. 9', 'Thunderstorm Advisory: rainshowers over Nasugbu and Calatagan, Batangas within 2 hours.', 'province', 'Lian, Batangas · Province-wide advisory');
        $this->pagasa('Lipa Rain Warning', 'Heavy rain over Lipa City, Batangas.', 'province', 'Lian, Batangas · Province-wide advisory');
        $this->pagasa('PAGASA Weekly Weather Outlook', 'The rest of the country will have fair weather.', 'regional', 'National outlook (includes Lian, Batangas)');
    }

    private function page(User $farmer, array $query = []): string
    {
        return (string) $this->actingAs($farmer)->get(route('planting-advisories.index', $query))->assertOk()->getContent();
    }

    public function test_san_diego_farmer_sees_their_barangay_first_then_other_barangays_lian_and_pagasa(): void
    {
        $this->seedAdvisories();
        $html = $this->page($this->farmer('San Diego'));

        $this->assertSeeInOrderHtml($html, [
            'For your barangay', 'San Diego, Lian, Batangas', 'San Diego Field Check',
            'Other Lian Barangays', 'Matabungkay Heavy Rain Advisory',
            'All of Lian', 'Lian Drainage Reminder',
            'Official PAGASA Advisories', 'This PAGASA advisory includes Lian, Batangas. PAGASA scope: Batangas Province.', 'Batangas Provincial Weather Forecast',
            'Other PAGASA Information', 'PAGASA Weekly Weather Outlook',
        ]);
        // PAGASA's own barangay bulletin is listed with the barangays PAGASA named, under other barangays.
        $this->assertStringContainsString('Affected barangays: Binubusan, Matabungkay', $html);
        // Other Batangas towns are never shown; routine advice for other barangays waits for "All Lian Barangays".
        foreach (['Thunderstorm Advisory No. 9', 'Lipa Rain Warning', 'Balibago Routine Advice', 'Prenza MAO Notice'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html, $hidden);
        }
    }

    public function test_each_barangays_farmer_gets_their_own_advice_first(): void
    {
        $this->seedAdvisories();

        // Matabungkay: its own iClimate advice and the PAGASA bulletin that names it come first.
        $this->assertSeeInOrderHtml($this->page($this->farmer('Matabungkay')), [
            'For your barangay', 'Matabungkay, Lian, Batangas', 'Matabungkay Heavy Rain Advisory', 'Rainfall Advisory No. 3', 'All of Lian',
        ]);
        // Balibago: its routine advice is its own; Matabungkay's warning is listed under other barangays.
        $balibago = $this->page($this->farmer('Balibago'));
        $this->assertSeeInOrderHtml($balibago, ['For your barangay', 'Balibago Routine Advice', 'Other Lian Barangays', 'Matabungkay Heavy Rain Advisory', 'All of Lian']);
        $this->assertStringNotContainsString('Thunderstorm Advisory No. 9', $balibago);
        // Prenza: the MAO notice for Prenza is theirs.
        $this->assertSeeInOrderHtml($this->page($this->farmer('Prenza')), ['For your barangay', 'Prenza MAO Notice', 'Other Lian Barangays']);
    }

    public function test_the_barangay_picker_shows_my_barangay_all_and_each_barangay(): void
    {
        $this->seedAdvisories();
        $farmer = $this->farmer('San Diego');
        $html = $this->page($farmer);

        $this->assertMatchesRegularExpression('#<label class="fad-label" for="fadBarangayPicker">.*?Barangay</label>#s', $html);
        $this->assertStringContainsString('>My Barangay (San Diego)</option>', $html);
        $this->assertStringContainsString('<option value="all" >All Lian Barangays</option>', $html);
        foreach (LianBarangays::all() as $barangay) {
            $this->assertStringContainsString('<option value="'.e($barangay).'"', $html, $barangay);
        }

        // All Lian Barangays: every barangay's advice, no "For your barangay" section.
        $all = $this->page($farmer, ['target_barangay' => 'all']);
        $this->assertSeeInOrderHtml($all, ['Lian Barangays', 'All of Lian']);
        foreach (['San Diego Field Check', 'Balibago Routine Advice', 'Prenza MAO Notice', 'Matabungkay Heavy Rain Advisory'] as $shown) {
            $this->assertStringContainsString($shown, $all, $shown);
        }
        $this->assertStringNotContainsString('For your barangay', $all);
        $this->assertStringNotContainsString('Thunderstorm Advisory No. 9', $all);

        // One barangay: its advice first, labelled as that barangay (not "your barangay").
        $this->assertSeeInOrderHtml($this->page($farmer, ['target_barangay' => 'prenza']), ['Barangay', 'Prenza, Lian, Batangas', 'Prenza MAO Notice']);
    }

    public function test_every_advisory_is_listed_once_with_source_and_times(): void
    {
        $this->seedAdvisories();
        $html = $this->page($this->farmer('Matabungkay'));

        foreach (['Matabungkay Heavy Rain Advisory', 'Rainfall Advisory No. 3', 'Lian Drainage Reminder', 'Batangas Provincial Weather Forecast'] as $title) {
            $this->assertSame(1, substr_count($html, '<h3 class="fad-title">'.$title.'</h3>'), $title);
        }
        $this->assertStringContainsString('Source: PAGASA', $html);
        $this->assertStringContainsString('Published:', $html);
        $this->assertStringContainsString('Updated:', $html);
        $this->assertStringContainsString('Valid until:', $html);
        $this->assertStringContainsString('View Details', $html);
    }

    public function test_a_farmer_without_a_barangay_still_gets_lian_advice(): void
    {
        $this->seedAdvisories();
        $html = $this->page($this->farmer(null));

        $this->assertStringContainsString('Add your barangay to your profile', $html);
        $this->assertSeeInOrderHtml($html, ['Other Lian Barangays', 'Matabungkay Heavy Rain Advisory', 'All of Lian', 'Lian Drainage Reminder', 'Official PAGASA Advisories']);
        $this->assertStringNotContainsString('Lipa Rain Warning', $html);
    }

    public function test_the_parser_keeps_only_bulletins_that_reach_lian(): void
    {
        $parser = app(PagasaBulletinParser::class);
        $scope = fn (string $text) => $parser->location($text);

        $this->assertNull($scope('Thunderstorm Advisory: rainshowers over Nasugbu and Calatagan, Batangas within 2 hours.'));
        $this->assertNull($scope('Heavy rain over Lipa City, Batangas.'));
        $this->assertNull($scope('Heavy rain over Batangas City and nearby areas.'));
        $this->assertNull($scope('Rain expected in Barangay Balibago, Calatagan, Batangas.'), 'Another town\'s Balibago is not Lian\'s.');
        $this->assertNull($scope('Heavy Rainfall Warning: Batangas(Nasugbu, Tuy).'));

        $this->assertSame('province', $scope('Thunderstorm Advisory: rainshowers over Batangas and Cavite.')['scope_type']);
        $this->assertSame('municipality', $scope('Thunderstorm over Nasugbu, Batangas and Lian, Batangas.')['scope_type']);
        $this->assertSame(['Binubusan', 'Matabungkay'], $scope('Rainfall Advisory: Lian, Batangas barangays Matabungkay and Binubusan may experience rain.')['barangays']);
        // A barangay name without Lian is not credited to Lian.
        $this->assertSame([], $scope('Rain expected in San Diego and the province of Batangas.')['barangays']);
    }

    public function test_fetching_never_stores_a_bulletin_for_other_batangas_towns(): void
    {
        config(['services.pagasa.regional_forecast_urls' => ['https://www.pagasa.dost.gov.ph/regional-forecast/ncrprsd'], 'services.pagasa.weekly_outlook_url' => null]);
        Http::fake(['*' => Http::response('<html><body>Thunderstorm Advisory No. 4 Issued at: 2:00 PM, '.now('Asia/Manila')->format('j F Y').' Rainshowers over Nasugbu and Calatagan, Batangas within 2 hours.</body></html>')]);

        $summary = app(\App\Services\Advisories\PagasaAdvisoryService::class)->fetchAndStore(true);

        $this->assertTrue($summary['ok']);
        $this->assertSame(0, PlantingAdvisory::query()->fromPagasaOnline()->count());
        $this->assertSame(1, $summary['sources_without_lian_batangas_match']);
    }

    public function test_local_barangay_guidance_says_what_is_happening_in_plain_words(): void
    {
        $advisory = $this->local('San Diego Farm Weather Guidance: Rain Expected in the Next 24 Hours', 'San Diego', 'high', ['generation_key' => 'local-current:San Diego',
            'summary' => 'Current conditions in San Diego: 29.7 °C, 71% humidity, 0 mm current precipitation, and 7.8 km/h wind.']);

        // Not the list of readings (the card shows those separately), but what they mean.
        $this->assertSame('Rain expected in the next 24 hours.', (new \App\Support\FarmerAdvisoryView($advisory))->farmerEffect());

        // "What to do" is the first sentence that is an action, not the opening statement of what is happening.
        $advisory->update(['recommended_action' => 'Rain is likely today. Consider delaying fertilizer or pesticide application when possible.']);
        $this->assertSame('Consider delaying fertilizer or pesticide application when possible.', (new \App\Support\FarmerAdvisoryView($advisory->fresh()))->farmerAction());
    }

    private function assertSeeInOrderHtml(string $html, array $texts): void
    {
        $position = 0;
        foreach ($texts as $text) {
            $found = strpos($html, e($text), $position);
            $this->assertNotFalse($found, "\"{$text}\" was not found after the previous item.");
            $position = $found + strlen(e($text));
        }
    }
}
