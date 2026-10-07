<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\User;
use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase 2 (2026-10) farmer pages: six-item menu, "Aking Bukid", read-only Announcements, removed farmer pages that
 * redirect to their replacements, farm size/type and the setup checklist on Profile, and the one season calendar.
 */
class FarmerPagesSimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response([], 500)]);
        Queue::fake();
    }

    private function farmer(string $barangay = 'Binubusan', array $profile = []): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay]);
        FarmerProfile::factory()->create($profile + ['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => 2.5, 'farm_type' => 'Irrigated']);

        return $user;
    }

    private function schedule(User $farmer, array $attributes = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($attributes + [
            'user_id' => $farmer->id, 'planting_date' => now()->addDays(5)->toDateString(), 'scheduled_harvest_date' => now()->addDays(120)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2.5,
        ]);
    }

    // ------------------------------------------------------------ menu

    public function test_the_farmer_menu_has_six_items_in_the_farmers_language(): void
    {
        $farmer = $this->farmer();
        $html = $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'class="ic-sidebar'), 6000);

        preg_match_all('/<span class="sidebar-label">([^<]+)<\/span>/', $menu, $m);
        $this->assertSame(['Dashboard', 'Forecast', 'Heat Map', 'Advisories', 'My Farm', 'Messages'], array_slice($m[1], 0, 6));
        foreach (['Climate Records', 'Rice Production', 'Community', 'Calendar'] as $gone) {
            $this->assertNotContains($gone, $m[1]);
        }

        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        $html = $this->actingAs($farmer)->get(route('farmer.my-farm'))->getContent();
        preg_match_all('/<span class="sidebar-label">([^<]+)<\/span>/', substr($html, strpos($html, 'class="ic-sidebar'), 6000), $m);
        $this->assertSame(['Dashboard', 'Taya ng Panahon', 'Mapa ng Panganib', 'Payo', 'Aking Bukid', 'Mensahe'], array_slice($m[1], 0, 6));
    }

    // ------------------------------------------------------------ Aking Bukid

    public function test_my_farm_shows_only_the_farmers_own_farm_schedule_and_harvests(): void
    {
        $farmer = $this->farmer();
        $this->schedule($farmer);
        $this->schedule($farmer, ['planting_date' => now()->subDays(150)->toDateString(), 'scheduled_harvest_date' => now()->subDays(30)->toDateString(),
            'progress_status' => 'Harvested', 'actual_yield' => 4.35, 'harvested_on' => now()->subDays(30)->toDateString()]);
        $other = $this->farmer('Prenza');
        $this->schedule($other, ['actual_yield' => 9.99, 'harvested_on' => now()->subDays(3)->toDateString(), 'progress_status' => 'Harvested']);

        $html = $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()->getContent();

        $this->assertStringContainsString('2.50 ha', $html);
        $this->assertStringContainsString('Irrigated', $html);
        $this->assertStringContainsString('My Planting Schedule', $html);
        $this->assertStringContainsString('4.35', $html);
        $this->assertStringNotContainsString('9.99', $html, "another farmer's harvest is never shown");
        $this->assertStringContainsString('My Calendar', $html);
        $this->assertStringContainsString(route('calendar.index'), $html);
    }

    public function test_harvest_records_are_printable_with_the_office_header_and_print_date(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('farmer.my-farm'))->assertOk()->getContent();

        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('Municipal Agricultural Office – Lian, Batangas', $html);
        $this->assertStringContainsString('Printed: ', $html);
        $css = (string) file_get_contents(resource_path('css/farmer-records.css'));
        $this->assertStringContainsString('@media print', $css);
    }

    public function test_my_farm_is_for_farmers_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->get(route('farmer.my-farm'))->assertForbidden();
    }

    public function test_saving_a_planting_returns_to_my_farm(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), [
            'planting_date' => now()->addDays(3)->toDateString(), 'scheduled_harvest_date' => now()->addDays(118)->toDateString(),
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan',
        ])->assertSessionHasNoErrors()->assertRedirect(route('farmer.my-farm'));
    }

    // ------------------------------------------------------------ removed farmer pages redirect to their replacements

    public function test_every_removed_farmer_url_redirects_to_its_replacement_never_an_error(): void
    {
        $farmer = $this->farmer();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $post = FeedPost::query()->create(['user_id' => $mao->id, 'title' => 'Training', 'body' => 'Friday.', 'category' => 'Training', 'visibility' => 'All Farmers']);
        $record = \App\Models\ClimateRecord::factory()->create();
        $announcements = route('planting-advisories.index').'#announcements';

        $cases = [
            // Climate Records and the MAO's Rice Production records: every page, by typed URL, lands on Home.
            ['get', route('climate-records.index'), route('farmer.dashboard')],
            ['get', route('climate-records.show', $record), route('farmer.dashboard')],
            ['get', route('climate-records.create'), route('farmer.dashboard')],
            ['get', route('rice-productions.index'), route('farmer.dashboard')],
            ['get', route('rice-productions.create'), route('farmer.dashboard')],
            // Community: reading moves to Announcements; posting, editing, archiving, comments, reactions and deleting too.
            ['get', route('community-feed.index'), $announcements],
            ['post', route('community-feed.store'), $announcements],
            ['patch', route('community-feed.update', $post), $announcements],
            ['patch', route('community-feed.archive', $post), $announcements],
            ['post', route('community-feed.comments.store', $post), $announcements],
            ['post', route('community-feed.reactions.store', $post), $announcements],
            ['delete', route('community-feed.destroy', $post), $announcements],
            // Weather forecast feedback.
            ['post', route('weather-feedback.store'), route('weather-predictions.index')],
            // Farm map drawing: the editor, its save and delete, and the old dashboard pop-up's save.
            ['get', route('farmer.boundary.edit'), route('farmer.my-farm')],
            ['put', route('farmer.boundary.update'), route('farmer.my-farm')],
            ['delete', route('farmer.boundary.destroy'), route('farmer.my-farm')],
            ['post', route('farmer.dashboard.farm-map'), route('farmer.my-farm')],
            ['postJson', route('farmer.dashboard.farm-map'), route('farmer.my-farm')],
        ];
        foreach ($cases as [$method, $url, $to]) {
            $this->actingAs($farmer)->{$method}($url, ['body' => 'x', 'type' => 'Like'])->assertRedirect($to);
        }

        // Nothing was written by any of them.
        $this->assertDatabaseMissing('feed_comments', ['user_id' => $farmer->id]);
        $this->assertDatabaseMissing('feed_reactions', ['user_id' => $farmer->id]);
        $this->assertSame(1, FeedPost::query()->count());
        $this->assertNotNull(FeedPost::query()->find($post->id));
    }

    public function test_staff_keep_climate_records_rice_production_and_the_community_feed(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get(route('climate-records.index'))->assertOk();
        $this->actingAs($mao)->get(route('rice-productions.index'))->assertOk();
        $this->actingAs($mao)->get(route('community-feed.index'))->assertOk();
    }

    // ------------------------------------------------------------ announcements

    public function test_farmers_read_announcements_on_the_advisories_page_without_reactions_or_comments(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $post = FeedPost::query()->create(['user_id' => $mao->id, 'title' => 'Seed distribution on Friday', 'body' => 'Bring your RSBSA ID.', 'category' => 'Activity', 'visibility' => 'All Farmers']);
        $farmer = $this->farmer();

        $html = $this->actingAs($farmer)->get(route('planting-advisories.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="announcements"', $html);
        $this->assertStringContainsString('Seed distribution on Friday', $html);
        $this->assertStringNotContainsString('community-feed/'.$post->id.'/reactions', $html);
        $this->assertStringNotContainsString('community-feed/'.$post->id.'/comments', $html);

        $this->actingAs($farmer)->post(route('community-feed.comments.store', $post), ['body' => 'Hi'])->assertRedirect(route('planting-advisories.index').'#announcements');
        $this->actingAs($farmer)->post(route('community-feed.reactions.store', $post), ['type' => 'Like'])->assertRedirect(route('planting-advisories.index').'#announcements');
        $this->assertDatabaseMissing('feed_comments', ['user_id' => $farmer->id]);
        $this->assertDatabaseMissing('feed_reactions', ['user_id' => $farmer->id]);
    }

    public function test_the_typhoon_check_in_is_no_longer_on_the_farmers_advisories_page(): void
    {
        $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->assertOk()->assertDontSee('typhoonSafetyTitle', false);
    }

    // ------------------------------------------------------------ profile

    public function test_profile_saves_farm_size_and_type_and_shows_what_is_missing(): void
    {
        $farmer = $this->farmer(profile: ['farm_area' => null]);

        $html = $this->actingAs($farmer)->get(route('profile.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('data-farm-setup', $html);
        $this->assertStringContainsString('Add your farm size', $html);
        $this->assertStringContainsString('name="farm_area"', $html);
        $this->assertStringNotContainsString(route('farmer.boundary.edit'), $html);

        $this->actingAs($farmer)->patch(route('profile.update'), [
            'name' => $farmer->name, 'email' => $farmer->email, 'barangay' => 'Binubusan', 'farm_area' => '1.75', 'farm_type' => 'Rainfed',
        ])->assertRedirect(route('profile.edit'));

        $profile = $farmer->farmerProfile()->first();
        $this->assertEquals(1.75, (float) $profile->farm_area);
        $this->assertSame('Rainfed', $profile->farm_type);
        $this->assertStringContainsString('Your farm information is complete.', $this->actingAs($farmer->fresh())->get(route('profile.edit'))->getContent());
    }

    public function test_profile_rejects_an_impossible_farm_size(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($farmer)->patch(route('profile.update'), ['name' => $farmer->name, 'email' => $farmer->email, 'farm_area' => '-3'])
            ->assertSessionHasErrors('farm_area');
    }

    // ------------------------------------------------------------ one season calendar

    public function test_wet_season_is_may_to_october_and_dry_is_november_to_april(): void
    {
        foreach (range(1, 12) as $month) {
            $this->assertSame(in_array($month, [5, 6, 7, 8, 9, 10], true) ? 'Wet' : 'Dry', RiceSeason::forMonth($month), "month {$month}");
            $this->assertSame(RiceSeason::forMonth($month), \App\Models\ClimateRecord::seasonForMonth($month));
        }

        $dry = RiceSeason::window(CarbonImmutable::create(2027, 2, 10));
        $this->assertSame(['Dry', '2026-11-01', '2027-04-30'], [$dry['season'], $dry['start']->toDateString(), $dry['end']->toDateString()]);
        $wet = RiceSeason::window(CarbonImmutable::create(2026, 10, 31));
        $this->assertSame(['Wet', '2026-05-01', '2026-10-31'], [$wet['season'], $wet['start']->toDateString(), $wet['end']->toDateString()]);
    }

    public function test_the_trained_yield_model_keeps_the_season_encoding_it_was_trained_with(): void
    {
        // October is Wet on every page, but the deployed rice-yield model learned October as Dry.
        $this->assertSame('Wet', RiceSeason::forMonth(10));
        $this->assertSame('Dry', RiceSeason::forTrainedYieldModel(10));
        $this->assertSame('Wet', RiceSeason::forTrainedYieldModel(9));
    }
}
