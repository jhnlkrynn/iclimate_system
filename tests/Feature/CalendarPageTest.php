<?php

namespace Tests\Feature;

use App\Models\AdvisoryRule;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Database\Seeders\AdvisoryRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The farmer Calendar: which days an advisory is shown on, and what Upcoming lists. */
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

    public function test_a_newer_forecast_run_replaces_the_older_one_from_its_first_day(): void
    {
        $this->seed(AdvisoryRuleSeeder::class);
        $rule = AdvisoryRule::query()->firstOrFail();
        $this->advisory(['title' => 'Delay planting', 'summary' => 'First run', 'advisory_rule_id' => $rule->id, 'valid_from' => '2026-09-10 00:00:00', 'valid_until' => '2026-10-10 00:00:00']);
        $this->advisory(['title' => 'Delay planting', 'summary' => 'Second run', 'advisory_rule_id' => $rule->id, 'valid_from' => '2026-09-12 00:00:00', 'valid_until' => '2026-10-12 00:00:00']);

        $html = $this->actingAs($this->farmer())->get(route('calendar.index', ['month' => 9, 'year' => 2026]))->assertOk()->getContent();

        // The September grid runs Aug 30 – Oct 3: the advice is on Sep 10 – Oct 3, once a day (24 days), not twice.
        $this->assertSame(24, substr_count($html, 'title="Delay planting"'));
        $this->assertStringContainsString('First run', $this->cell($html, 'Thursday, September 10'));
        $this->assertStringContainsString('Second run', $this->cell($html, 'Saturday, September 12'));
        $this->assertStringNotContainsString('First run', $this->cell($html, 'Saturday, September 12'));
        $this->assertSame(2, PlantingAdvisory::query()->count());                                   // nothing is deleted
    }

    public function test_identical_pagasa_checks_on_the_same_day_are_shown_once(): void
    {
        foreach (['2026-09-09 01:00:00', '2026-09-09 03:00:00', '2026-09-09 05:00:00'] as $checkedAt) {
            $this->advisory(['title' => 'PAGASA Batangas Rainfall Advisory', 'summary' => 'Light rain', 'source' => PlantingAdvisory::SOURCE_PAGASA_LEGACY, 'valid_from' => $checkedAt, 'valid_until' => Carbon::parse($checkedAt)->addHours(6)]);
        }

        $html = $this->actingAs($this->farmer())->get(route('calendar.index', ['month' => 9, 'year' => 2026]))->getContent();

        $this->assertSame(1, substr_count($html, 'title="PAGASA Batangas Rainfall Advisory"'));
    }

    public function test_days_and_today_follow_the_lian_clock_not_utc(): void
    {
        // 17:00–23:00 UTC on Oct 4 is 1:00–7:00 AM on Monday Oct 5 in Lian.
        $this->advisory(['title' => 'Early morning rain', 'valid_from' => '2026-10-04 17:00:00', 'valid_until' => '2026-10-04 23:00:00']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 17:00:00', 'UTC'));                          // already Sunday Oct 4 in Lian

        $html = $this->actingAs($this->farmer())->get(route('calendar.index'))->getContent();

        $this->assertStringContainsString('Early morning rain', $this->cell($html, 'Monday, October 5'));
        $this->assertStringNotContainsString('Early morning rain', $this->cell($html, 'Sunday, October 4'));
        $this->assertMatchesRegularExpression('/fcal-day is-today[^"]*"\s+role="group" aria-label="Sunday, October 4, today/', $html);
    }

    public function test_upcoming_lists_what_is_active_or_coming_whatever_month_is_shown(): void
    {
        $this->advisory(['title' => 'Active now', 'valid_from' => '2026-10-01 00:00:00', 'valid_until' => '2026-10-08 00:00:00']);
        $this->advisory(['title' => 'Starts later', 'valid_from' => '2026-10-20 00:00:00', 'valid_until' => '2026-10-21 00:00:00']);
        $this->advisory(['title' => 'Already over', 'valid_from' => '2026-09-01 00:00:00', 'valid_until' => '2026-09-02 00:00:00']);

        foreach ([[], ['month' => 1, 'year' => 2027], ['month' => 8, 'year' => 2026]] as $query) {
            $html = $this->actingAs($this->farmer())->get(route('calendar.index', $query))->getContent();
            $upcoming = substr($html, strpos($html, 'fcal-up-list'));

            $this->assertLessThan(strpos($upcoming, 'Starts later'), strpos($upcoming, 'Active now'));   // date order
            $this->assertStringContainsString('Active until Oct 8', $upcoming);
            $this->assertStringNotContainsString('Already over', $upcoming);
        }
    }

    public function test_the_page_keeps_its_parts_and_plain_wording(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('calendar.index'))->assertOk()->getContent();

        foreach (['<h1>Calendar</h1>', 'Add My Planting Plan', 'aria-label="Previous month', '>Today<', 'aria-label="Next month', 'My Planting and Harvest Records', 'No farm schedule yet.', 'fcal-upcoming'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        foreach (['Weather', 'Planting', 'Harvesting', 'Watering', 'Events'] as $legend) {
            $this->assertMatchesRegularExpression('/fcal-dot" data-kind="[a-z]+" aria-hidden="true"><\/span>'.$legend.'</', $html, $legend);
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

    public function test_a_farmers_own_records_show_on_the_calendar_in_upcoming_and_in_the_records_list(): void
    {
        $farmer = $this->farmer();
        $schedule = \App\Models\FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'planting_date' => '2026-10-20',
            'scheduled_harvest_date' => '2027-02-15',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => 'Prenza',
            'predicted_yield' => 3.5,
        ]);

        $html = $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Planting - Prenza', $this->cell($html, 'Tuesday, October 20'));
        $this->assertStringContainsString('My Planting', substr($html, strpos($html, 'fcal-up-list')));
        $this->assertStringContainsString('Oct 20, 2026 planting', $html);
        $this->assertStringContainsString('Feb 15, 2027', $html);
        $this->assertStringContainsString('3.50 tons/hectare', $html);
        $this->assertStringContainsString(route('farmer.planting-schedules.edit', $schedule), $html);
        $this->assertStringContainsString(route('farmer.planting-schedules.destroy', $schedule), $html);
        $this->assertStringNotContainsString('No farm schedule yet.', $html);
    }

    private function farmer(): User
    {
        return User::query()->where('email', 'cal-farmer@example.test')->first()
            ?? User::factory()->farmer()->create(['email' => 'cal-farmer@example.test', 'barangay' => 'Prenza']);
    }

    /** The HTML of one day cell, found by its accessible name. */
    private function cell(string $html, string $day): string
    {
        $start = strpos($html, 'aria-label="'.$day);
        $this->assertNotFalse($start, "no cell for $day");
        $end = strpos($html, 'role="group" aria-label="', $start + 1);                         // the next day cell

        return substr($html, $start, $end === false ? null : $end - $start);
    }

    private function advisory(array $overrides): PlantingAdvisory
    {
        return PlantingAdvisory::query()->create(array_merge([
            'title' => 'Advisory',
            'content' => 'Message',
            'message' => 'Message',
            'type' => 'Climate',
            'advisory_type' => 'climate',
            'summary' => 'Summary',
            'severity' => 'information',
            'target_scope' => 'municipality',
            'source' => 'Open-Meteo + iClimate Rules',
            'status' => 'published',
            'posted_by' => User::factory()->maoPersonnel()->create()->id,
        ], $overrides));
    }
}
