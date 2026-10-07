<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\MaoActivity;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Mao\MaoMissingDataNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MAO/IT – Naicel (2026-10-07): the MAO dashboard shows no empty sections. Data the MAO has not added yet is a bell
 * notification instead (one per item, never duplicated, removed once the data exists), like the farmer reminders.
 */
class MaoMissingDataNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ITEMS = ['climate_month', 'production_season', 'advisories', 'planner'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));   // Wet season 2026 (May–October)
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function mao(array $attributes = []): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE] + $attributes);
    }

    /** @return array<string, Notification> this user's MAO missing-data notifications, by item */
    private function notes(User $user): array
    {
        return Notification::query()->where('user_id', $user->id)->where('category', 'like', MaoMissingDataNotifier::CATEGORY_PREFIX.'%')
            ->get()->keyBy(fn (Notification $n): string => substr($n->category, strlen(MaoMissingDataNotifier::CATEGORY_PREFIX)))->all();
    }

    private function dashboard(User $mao): string
    {
        Cache::flush();   // a fresh visit: the once-a-minute check and the prepared dashboard copy are dropped

        return $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();
    }

    /** The every-minute check (iclimate:warm-dashboards runs it); a dashboard visit never does. */
    private function scheduledCheck(): void
    {
        Cache::flush();
        app(MaoMissingDataNotifier::class)->syncAll();
    }

    /** One record of each kind the notifications ask for, dated inside this month / season / from today. */
    private function addAllData(User $mao): void
    {
        ClimateRecord::query()->create(['record_date' => '2026-10-02', 'rainfall' => 180.5, 'temperature' => 28.4, 'humidity' => 84, 'wind_speed' => 12, 'season' => 'Wet', 'source' => 'MAO Lian weather log']);
        RiceProduction::query()->create(['barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.2, 'area_hectares' => 10, 'total_production' => 42, 'year' => 2026, 'production_month' => '2026-09-01', 'remarks' => 'Harvest report from the barangay technician']);
        PlantingAdvisory::query()->create(['title' => 'Clear the drainage canals', 'content' => 'Heavy rain is expected this week.', 'message' => 'Heavy rain is expected this week.', 'type' => 'Planting', 'advisory_type' => 'planting',
            'severity' => 'low', 'target_scope' => 'municipality', 'status' => 'published', 'valid_from' => now()->subDay(), 'valid_until' => now()->addWeek(), 'posted_by' => $mao->id]);
        MaoActivity::query()->create(['user_id' => $mao->id, 'activity_type' => 'Training', 'barangay' => null, 'starts_on' => '2026-10-20', 'title' => 'Rice crop care training']);
    }

    public function test_with_no_data_the_check_creates_one_notification_per_missing_item_in_filipino(): void
    {
        $mao = $this->mao(['preferred_language' => 'fil']);
        $this->scheduledCheck();

        $notes = $this->notes($mao);
        $this->assertSame(self::ITEMS, array_values(array_intersect(self::ITEMS, array_keys($notes))));
        $this->assertCount(4, $notes);
        $this->assertSame('Walang climate records ngayong buwan – magdagdag', $notes['climate_month']->title);
        $this->assertSame('Walang production records ngayong season – magdagdag', $notes['production_season']->title);
        $this->assertFalse((bool) $notes['planner']->is_read);
    }

    public function test_the_scheduled_warm_up_creates_them_and_a_dashboard_visit_alone_does_not(): void
    {
        $mao = $this->mao();
        $this->dashboard($mao);
        $this->assertSame([], $this->notes($mao), 'the dashboard stays a read of its prepared copy');

        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);
        $this->assertCount(4, $this->notes($mao));
    }

    /** English is the MAO account's language unless Filipino was picked on the MAO Dashboard (2026-10-08). */
    public function test_the_notifications_are_in_english_unless_the_mao_picked_filipino(): void
    {
        $mao = $this->mao();
        $this->scheduledCheck();

        $this->assertSame('No climate records this month – add one', $this->notes($mao)['climate_month']->title);
    }

    public function test_they_are_never_duplicated(): void
    {
        $mao = $this->mao();
        $this->scheduledCheck();
        $this->scheduledCheck();
        app(MaoMissingDataNotifier::class)->syncAll();
        $this->assertSame(4, Notification::query()->where('user_id', $mao->id)->count());

        // A stray copy (e.g. two requests at once) is removed on the next check.
        Notification::query()->create(['user_id' => $mao->id, 'title' => 'copy', 'message' => 'copy', 'type' => 'Announcement', 'category' => 'mao-setup:planner', 'is_read' => false]);
        app(MaoMissingDataNotifier::class)->syncAll();
        $this->assertSame(1, Notification::query()->where('user_id', $mao->id)->where('category', 'mao-setup:planner')->count());
    }

    public function test_each_notification_in_the_bell_links_to_the_page_where_the_data_is_added(): void
    {
        $mao = $this->mao(['preferred_language' => 'fil']);
        $this->scheduledCheck();

        $items = collect($this->actingAs($mao)->getJson(route('notifications.feed'))->assertOk()->json('items'))->keyBy('title');
        $expected = [
            'Walang climate records ngayong buwan – magdagdag' => route('climate-records.create'),
            'Walang production records ngayong season – magdagdag' => route('rice-productions.create'),
            'Walang aktibong advisory – gumawa' => route('planting-advisories.create'),
            'Walang paparating na gawain sa Planner ng MAO – magdagdag' => route('mao.planner.index'),
        ];
        foreach ($expected as $title => $url) {
            $this->assertSame($url, $items[$title]['link_url'] ?? null, $title);
            $this->assertSame('Magdagdag', $items[$title]['link_label'] ?? null, $title);
        }
        // Each link opens for the MAO.
        foreach ($expected as $url) {
            $this->actingAs($mao)->get($url)->assertOk();
        }
    }

    public function test_they_are_removed_automatically_once_the_data_exists(): void
    {
        $mao = $this->mao();
        $other = $this->mao();
        $this->scheduledCheck();
        app(MaoMissingDataNotifier::class)->syncAll();
        $this->assertCount(4, $this->notes($other));

        // Saved outside a request; the check runs after the next response (here: any page), for every MAO account.
        $this->addAllData($mao);
        $this->actingAs($other)->get(route('profile.edit'))->assertOk();

        $this->assertSame([], $this->notes($mao));
        $this->assertSame([], $this->notes($other));
    }

    public function test_one_item_comes_back_when_its_data_is_gone_and_a_new_month_asks_again(): void
    {
        $mao = $this->mao();
        $this->addAllData($mao);
        $this->scheduledCheck();
        $this->assertSame([], $this->notes($mao));

        MaoActivity::query()->delete();   // a query delete fires no model events: the next scheduled check notices
        $this->scheduledCheck();
        $this->assertSame(['planner'], array_keys($this->notes($mao)));

        // November: October's climate record no longer counts for "this month".
        $this->travelTo(CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Manila'));
        $this->scheduledCheck();
        $this->assertArrayHasKey('climate_month', $this->notes($mao));
    }

    public function test_farmers_and_it_never_get_them(): void
    {
        $farmer = User::factory()->farmer()->create();
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $this->mao();

        app(MaoMissingDataNotifier::class)->syncAll();
        app(MaoMissingDataNotifier::class)->sync($farmer);
        app(MaoMissingDataNotifier::class)->sync($it);

        $this->assertSame([], $this->notes($farmer));
        $this->assertSame([], $this->notes($it));
    }

    public function test_the_dashboard_shows_no_empty_sections(): void
    {
        $html = $this->dashboard($this->mao());

        $this->assertStringNotContainsString('Walang datos pa', $html);
        $this->assertStringNotContainsString('data-kpi-empty', $html);
        $this->assertStringNotContainsString('class="ic-no-data', $html);
        // No planting dates, no survey answers and no saved forecast here: those cards are left out. (The yield, climate
        // and weather-impact charts also read the project's bundled history, so they have data even on an empty database.)
        foreach (['data-mao-planted', 'data-mao-survey', 'data-heat-map-preview', 'mhdMapTitle'] as $empty) {
            $this->assertStringNotContainsString($empty, $html, $empty);
        }
        // Real figures stay: the farmer count (0 is a real number) and Needs Action.
        $this->assertStringContainsString('data-kpi="farmers"', $html);
        $this->assertStringContainsString('Needs Action', $html);
    }

    public function test_a_section_appears_once_it_has_data(): void
    {
        $mao = $this->mao();
        foreach (range(1, 3) as $back) {
            ClimateRecord::query()->create(['record_date' => CarbonImmutable::parse('2026-10-01')->subMonths($back)->toDateString(), 'rainfall' => 150 + $back, 'temperature' => 28, 'humidity' => 80, 'wind_speed' => 10, 'season' => 'Wet', 'source' => 'MAO Lian weather log']);
        }

        $html = $this->dashboard($mao);

        $this->assertStringContainsString('data-mao-climate', $html);
        $this->assertStringContainsString('id="maoClimateChart"', $html);
    }
}
