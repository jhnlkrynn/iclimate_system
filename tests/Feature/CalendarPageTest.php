<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Calendar (2026-10): planning and activities only — planting dates, expected harvest dates, the recommended planting
 * window and MAO activities. No advisories or weather. Farmers see only their own dates; MAO sees all, by barangay.
 */
class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-03 02:00:00', 'UTC'));   // 10:00 AM, Saturday Oct 3 in Lian
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_advisories_and_weather_are_not_on_the_calendar(): void
    {
        $this->advisory(['title' => 'Delay planting', 'summary' => 'Heavy rain expected', 'valid_from' => '2026-10-01 00:00:00', 'valid_until' => '2026-10-12 00:00:00']);
        $this->advisory(['title' => 'PAGASA Batangas Rainfall Advisory', 'source' => PlantingAdvisory::SOURCE_PAGASA_LEGACY, 'valid_from' => '2026-10-02 00:00:00', 'valid_until' => '2026-10-09 00:00:00']);

        foreach ([$this->farmer(), User::factory()->maoPersonnel()->create()] as $user) {
            $html = $this->actingAs($user)->get(route('calendar.index'))->assertOk()->getContent();

            foreach (['Delay planting', 'Heavy rain expected', 'PAGASA Batangas Rainfall Advisory', 'See all advisories', 'data-kind="climate"', 'data-kind="irrigation"'] as $gone) {
                $this->assertStringNotContainsString($gone, $html, $gone);
            }
        }
    }

    public function test_the_legend_names_the_four_kinds_and_the_page_keeps_its_parts(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('calendar.index'))->assertOk()->getContent();

        foreach (['<h1>Calendar</h1>', 'Add My Planting Plan', 'aria-label="Previous month', '>Today<', 'aria-label="Next month', 'My Planting and Harvest Records', 'No farm schedule yet.', 'fcal-upcoming'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        $this->assertSame(1, preg_match('#<ul class="fcal-legend".*?</ul>#s', $html, $m));
        $this->assertSame('Planting Expected harvest Recommended planting window MAO activity', trim((string) preg_replace('/\s+/', ' ', strip_tags($m[0]))));
        $this->assertSame(4, substr_count($m[0], 'class="fcal-dot"'));
    }

    public function test_the_recommended_planting_window_is_marked_on_its_days_and_listed_in_upcoming(): void
    {
        config(['farm_calendar.planting_windows' => ['Wet' => ['from' => '06-01', 'until' => '07-31'], 'Dry' => ['from' => '11-15', 'until' => '12-31']]]);

        // November 2026: the Dry-season window opens on the 15th.
        $html = $this->actingAs($this->farmer())->get(route('calendar.index', ['month' => 11, 'year' => 2026]))->assertOk()->getContent();
        $this->assertStringContainsString('data-fcal-window="Dry"', $this->cell($html, 'Sunday, November 15'));
        $this->assertStringContainsString('In the recommended planting window', $this->cell($html, 'Monday, November 30'));
        $this->assertStringNotContainsString('data-fcal-window', $this->cell($html, 'Saturday, November 14'));

        // Whatever month is shown, Upcoming names the next window (today is Oct 3).
        $upcoming = $this->upcoming($this->actingAs($this->farmer())->get(route('calendar.index'))->getContent());
        $this->assertStringContainsString('Recommended planting window (Dry season)', $upcoming);
        $this->assertStringContainsString('Nov 15 to Dec 31, 2026', $upcoming);

        // While a window is open it reads "Now", with its last day.
        Carbon::setTestNow(Carbon::parse('2026-12-01 02:00:00', 'UTC'));
        $open = $this->upcoming($this->actingAs($this->farmer())->get(route('calendar.index'))->getContent());
        $this->assertMatchesRegularExpression('#fcal-up-date is-now.*?Now.*?Recommended planting window \(Dry season\).*?Until Dec 31#s', $open);
    }

    public function test_a_farmers_own_dates_show_on_the_calendar_in_upcoming_and_in_the_records_list(): void
    {
        $farmer = $this->farmer();
        $schedule = $this->schedule($farmer, 'Prenza');

        $html = $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()->getContent();

        $day = $this->cell($html, 'Tuesday, October 20');
        $this->assertStringContainsString('My Planting', $day);
        $this->assertStringContainsString('data-kind="planting"', $day);
        $this->assertStringContainsString('My Planting', $this->upcoming($html));
        $this->assertStringContainsString('Oct 20, 2026 planting', $html);
        $this->assertStringContainsString('Feb 15, 2027', $html);
        $this->assertStringContainsString('3.50 tons/hectare', $html);
        $this->assertStringContainsString(route('farmer.planting-schedules.edit', $schedule), $html);
        $this->assertStringContainsString(route('farmer.planting-schedules.destroy', $schedule), $html);
        $this->assertStringNotContainsString('No farm schedule yet.', $html);

        // The expected harvest date is on its own day, in its own colour.
        $february = $this->actingAs($farmer)->get(route('calendar.index', ['month' => 2, 'year' => 2027]))->getContent();
        $harvest = $this->cell($february, 'Monday, February 15');
        $this->assertStringContainsString('My Expected Harvest', $harvest);
        $this->assertStringContainsString('data-kind="harvest"', $harvest);
    }

    public function test_a_farmer_sees_only_their_own_dates_and_mao_sees_all_with_a_barangay_filter(): void
    {
        $farmer = $this->farmer();
        $other = User::factory()->farmer()->create(['name' => 'Otilia Cumba', 'barangay' => 'Cumba']);
        $farmer->forceFill(['name' => 'Pedro Prenza'])->save();
        $this->schedule($farmer, 'Prenza');
        $this->schedule($other, 'Cumba');

        // The farmer: their own planting, never the other farmer's; no barangay filter to reach it.
        $own = $this->actingAs($farmer)->get(route('calendar.index', ['barangay' => 'Cumba']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($this->cell($own, 'Tuesday, October 20'), 'class="fcal-event"'));
        $this->assertStringNotContainsString('Otilia Cumba', $own);
        $this->assertStringNotContainsString('data-fcal-filter', $own);

        // MAO: every farmer's dates, named; then one barangay.
        $mao = User::factory()->maoPersonnel()->create();
        $all = $this->actingAs($mao)->get(route('calendar.index'))->assertOk()->getContent();
        $day = $this->cell($all, 'Tuesday, October 20');
        $this->assertStringContainsString('Planting – Pedro Prenza', $day);
        $this->assertStringContainsString('Planting – Otilia Cumba', $day);
        $this->assertStringContainsString('data-fcal-filter', $all);
        $this->assertStringNotContainsString('Add My Planting Plan', $all);

        $cumba = $this->actingAs($mao)->get(route('calendar.index', ['barangay' => 'Cumba']))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="Cumba" selected>', $cumba);
        $this->assertStringContainsString('Planting – Otilia Cumba', $this->cell($cumba, 'Tuesday, October 20'));
        $this->assertStringNotContainsString('Pedro Prenza', $cumba);
        // The month links keep the barangay.
        $this->assertStringContainsString('barangay=Cumba', html_entity_decode($cumba));
    }

    public function test_mao_activities_show_for_the_farmers_barangay_and_for_all_of_lian(): void
    {
        $this->advisory(['title' => 'Planting Advisory – Prenza', 'target_barangay' => 'Prenza', 'target_scope' => 'barangay',
            'metadata' => ['mao_activity' => 'Seed distribution at Prenza hall', 'mao_activity_date' => '2026-10-12']]);
        $this->advisory(['title' => 'Planting Advisory – Cumba', 'target_barangay' => 'Cumba', 'target_scope' => 'barangay',
            'metadata' => ['mao_activity' => 'Soil testing in Cumba', 'mao_activity_date' => '2026-10-12']]);
        $this->advisory(['title' => 'Planting Advisory – All of Lian',
            'metadata' => ['mao_activity' => 'Farmers assembly', 'mao_activity_date' => '2026-10-14']]);
        // An activity on an advisory that is not published yet stays off the calendar.
        $this->advisory(['title' => 'Draft', 'status' => 'pending_review', 'metadata' => ['mao_activity' => 'Unapproved activity', 'mao_activity_date' => '2026-10-13']]);

        $html = $this->actingAs($this->farmer())->get(route('calendar.index'))->assertOk()->getContent();
        $day = $this->cell($html, 'Monday, October 12');
        $this->assertStringContainsString('Seed distribution at Prenza hall', $day);
        $this->assertStringContainsString('data-kind="activity"', $day);
        $this->assertStringNotContainsString('Soil testing in Cumba', $html);
        $this->assertStringContainsString('Farmers assembly', $this->cell($html, 'Wednesday, October 14'));
        $this->assertStringNotContainsString('Unapproved activity', $html);
        // The advisory's own title and text are not calendar entries.
        $this->assertStringNotContainsString('Planting Advisory – Prenza', $html);

        $mao = $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('calendar.index'))->getContent();
        $this->assertStringContainsString('Seed distribution at Prenza hall', $mao);
        $this->assertStringContainsString('Soil testing in Cumba', $mao);
    }

    public function test_days_and_today_follow_the_lian_clock_not_utc(): void
    {
        // A MAO post's event at 17:00 UTC on Oct 4 is 1:00 AM on Monday Oct 5 in Lian.
        FeedPost::query()->create(['user_id' => User::factory()->maoPersonnel()->create()->id, 'title' => 'Early morning training',
            'body' => 'Rice pest management.', 'category' => 'Training', 'visibility' => 'All Farmers', 'event_date' => '2026-10-04 17:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 17:00:00', 'UTC'));                          // already Sunday Oct 4 in Lian

        $html = $this->actingAs($this->farmer())->get(route('calendar.index'))->getContent();

        $this->assertStringContainsString('Early morning training', $this->cell($html, 'Monday, October 5'));
        $this->assertStringNotContainsString('Early morning training', $this->cell($html, 'Sunday, October 4'));
        $this->assertMatchesRegularExpression('/fcal-day is-today[^"]*"\s+role="group" aria-label="Sunday, October 4, today/', $html);
    }

    public function test_upcoming_lists_what_is_coming_whatever_month_is_shown(): void
    {
        $farmer = $this->farmer();
        $this->schedule($farmer, 'Prenza');                                                           // planting Oct 20
        $this->advisory(['metadata' => ['mao_activity' => 'Seed distribution', 'mao_activity_date' => '2026-10-08']]);
        $this->advisory(['metadata' => ['mao_activity' => 'Already over', 'mao_activity_date' => '2026-09-02']]);

        foreach ([[], ['month' => 1, 'year' => 2027], ['month' => 8, 'year' => 2026]] as $query) {
            $upcoming = $this->upcoming($this->actingAs($farmer)->get(route('calendar.index', $query))->getContent());

            $this->assertLessThan(strpos($upcoming, 'My Planting'), strpos($upcoming, 'Seed distribution'));   // date order
            $this->assertStringNotContainsString('Already over', $upcoming);
        }
    }

    public function test_a_filipino_farmer_reads_the_calendars_new_words_in_filipino(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza', 'preferred_language' => 'fil']);
        $this->schedule($farmer, 'Prenza');

        $html = $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()->getContent();

        foreach (['<h1>Kalendaryo</h1>', 'Inaasahang ani', 'Mungkahing panahon ng pagtatanim', 'Gawain ng MAO', 'Aking Pagtatanim', 'Mga Paparating'] as $words) {
            $this->assertStringContainsString($words, $html, $words);
        }
    }

    public function test_add_my_planting_plan_opens_the_plan_form_as_a_pop_up(): void
    {
        $farmer = $this->farmer();
        $html = $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, ' data-plan-open aria-haspopup="dialog"'));                               // the one Add button opens the pop-up
        $this->assertStringContainsString('<div class="fad-modal" id="plantingPlanModal" data-open-on-load="false" hidden>', $html);   // same pop-up parts as Advisories, closed until asked for
        $this->assertStringContainsString('<button class="fad-btn is-primary" type="submit">Save Schedule</button>', $html);
        $this->assertStringContainsString('action="'.route('farmer.planting-schedules.store').'"', $html);
        $this->assertStringContainsString('<option value="Prenza" selected>', $html);              // prefilled from the farmer's barangay

        // A plan with mistakes comes back to the calendar with the pop-up reopened and the entries kept.
        $response = $this->actingAs($farmer)->from(route('calendar.index'))
            ->post(route('farmer.planting-schedules.store'), ['planting_date' => '2026-10-20', 'season' => 'Dry']);
        $response->assertRedirect(route('calendar.index'))->assertSessionHasErrors(['farm_type', 'scheduled_harvest_date']);

        $html = $this->followRedirects($response)->getContent();
        $this->assertStringContainsString('id="plantingPlanModal" data-open-on-load="true" hidden>', $html);
        $this->assertStringContainsString('Please check the highlighted information.', $html);
        $this->assertStringContainsString('<div class="fcal-plan-error">The farm type field is required.</div>', $html);   // beside the field
        $this->assertStringContainsString('value="2026-10-20"', $html);
    }

    private function farmer(): User
    {
        return User::query()->where('email', 'cal-farmer@example.test')->first()
            ?? User::factory()->farmer()->create(['email' => 'cal-farmer@example.test', 'barangay' => 'Prenza']);
    }

    private function schedule(User $farmer, string $barangay): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'planting_date' => '2026-10-20',
            'scheduled_harvest_date' => '2027-02-15',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => $barangay,
            'predicted_yield' => 3.5,
        ]);
    }

    /** The HTML of one day cell, found by its accessible name. */
    private function cell(string $html, string $day): string
    {
        $start = strpos($html, 'aria-label="'.$day);
        $this->assertNotFalse($start, "no cell for $day");
        $end = strpos($html, 'role="group" aria-label="', $start + 1);                         // the next day cell

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    /** The Upcoming list's HTML. */
    private function upcoming(string $html): string
    {
        $start = strpos($html, 'fcal-up-list');
        $this->assertNotFalse($start, 'no Upcoming list');

        return substr($html, $start, strpos($html, '</ol>', $start) - $start);
    }

    private function advisory(array $overrides): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(array_merge([
            'title' => 'Advisory',
            'content' => 'Message',
            'message' => 'Message',
            'type' => 'Planting',
            'advisory_type' => 'planting',
            'summary' => 'Summary',
            'severity' => 'information',
            'target_scope' => 'municipality',
            'source' => 'MAO-Reviewed Advisory',
            'status' => 'published',
            'valid_from' => '2026-10-01 00:00:00',
            'valid_until' => '2026-10-30 00:00:00',
            'posted_by' => User::factory()->maoPersonnel()->create()->id,
        ], $overrides));
    }
}
