<?php

namespace Tests\Feature;

use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\PagasaBulletinParser;
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

    /**
     * The Advisories page is one list since Phase 5 (2026-10): a farmer reads the advice for their own barangay and for
     * all of Lian, never another barangay's. The list itself is covered by AdvisoriesListTest.
     */
    public function test_a_farmer_reads_their_own_barangay_and_lian_wide_advice_only(): void
    {
        $this->seedAdvisories();

        $sanDiego = $this->page($this->farmer('San Diego'));
        foreach (['San Diego Field Check', 'Lian Drainage Reminder', 'Batangas Provincial Weather Forecast', 'PAGASA Weekly Weather Outlook'] as $shown) {
            $this->assertStringContainsString($shown, $sanDiego, $shown);
        }
        foreach (['Matabungkay Heavy Rain Advisory', 'Balibago Routine Advice', 'Prenza MAO Notice', 'Rainfall Advisory No. 3'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $sanDiego, $hidden);
        }

        // Matabungkay: its own advice and the PAGASA bulletin that names it.
        $matabungkay = $this->page($this->farmer('Matabungkay'));
        foreach (['Matabungkay Heavy Rain Advisory', 'Rainfall Advisory No. 3', 'Lian Drainage Reminder'] as $shown) {
            $this->assertSame(1, substr_count($matabungkay, '>'.$shown.'<'), $shown.' is listed once');
        }
        $this->assertStringNotContainsString('San Diego Field Check', $matabungkay);

        // The MAO notice for Prenza is Prenza's.
        $this->assertStringContainsString('Prenza MAO Notice', $this->page($this->farmer('Prenza')));
    }

    public function test_a_farmer_without_a_barangay_still_gets_lian_advice(): void
    {
        $this->seedAdvisories();
        $html = $this->page($this->farmer(null));

        $this->assertStringContainsString('Lian Drainage Reminder', $html);
        $this->assertStringContainsString('Batangas Provincial Weather Forecast', $html);
        foreach (['San Diego Field Check', 'Matabungkay Heavy Rain Advisory', 'Prenza MAO Notice'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html, $hidden);
        }
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
}
