<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Farmer → Climate Records: "View" opens the record in the same pop-up the Advisories page uses, instead of leaving the list.
 */
class ClimateRecordPopupTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(): User
    {
        return User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
    }

    private function record(array $attributes = []): ClimateRecord
    {
        return ClimateRecord::query()->create($attributes + [
            'record_date' => '2026-10-04', 'rainfall' => 12, 'temperature' => 31, 'humidity' => 82.5, 'wind_speed' => 2.4,
            'season' => 'Wet', 'source' => 'PAGASA Lian Monthly Data',
        ]);
    }

    public function test_the_view_buttons_open_the_shared_pop_up_and_still_work_as_links(): void
    {
        $record = $this->record();
        $html = $this->actingAs($this->farmer())->get(route('climate-records.index'))->assertOk()->getContent();

        // Both layouts of the list (table on wide screens, cards on narrow ones) carry the pop-up hook and the real link.
        $this->assertSame(2, preg_match_all('/<a class="fad-btn is-quiet[^"]*" href="'.preg_quote(route('climate-records.show', $record), '/').'" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog"/', $html));
        // The same shell as the Advisories page, with Climate Record wording.
        $this->assertSame(1, substr_count($html, 'data-fad-modal'));
        $this->assertStringContainsString('class="fad-modal" data-fad-modal hidden', $html);
        $this->assertStringContainsString('id="fadModalLoading"', $html);
        $this->assertStringContainsString('id="fadModalError"', $html);
        $this->assertStringContainsString('Loading climate record details…', $html);
        $this->assertStringContainsString('aria-label="Close climate record"', $html);
    }

    public function test_the_pop_up_body_is_the_record_in_plain_words_without_the_page_layout(): void
    {
        $record = $this->record();
        $html = $this->actingAs($this->farmer())->get(route('climate-records.show', ['climate_record' => $record, 'modal' => 1]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<html', $html);                                    // the body only: no sidebar, no second page
        $this->assertStringNotContainsString('sidebar', $html);
        // The same parts as the Advisories pop-up.
        foreach (['class="fad-m-head"', 'id="fadModalTitle"', 'class="fad-m-x" data-fad-close', 'class="fad-m-scroll"', 'class="fad-m-weather"', 'class="fad-m-details"', 'class="fad-m-foot"'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES));
        foreach (['Climate Record Details', 'Date October 4, 2026', 'Location Lian, Batangas', 'Temperature 31°C', 'Rainfall 12 mm', 'Humidity 82.5%', 'Wind Speed 2.4', 'Season Wet season',
            'Source PAGASA Lian Monthly Data', 'Recorded On', 'Close'] as $expected) {
            $this->assertStringContainsString($expected, $text, $expected);
        }
        // No raw field names, payloads or database columns.
        foreach (['record_date', 'temperature_2m', 'relative_humidity', 'precipitation', 'wind_speed', 'created_at', 'updated_at', 'recorded_at', '{"', 'null'] as $raw) {
            $this->assertStringNotContainsStringIgnoringCase($raw, $text, $raw);
        }
        $this->assertSame(1, substr_count($html, '<button type="button" class="fad-btn is-primary" data-fad-close>Close</button>'));   // one action: Close
    }

    public function test_a_value_the_record_does_not_have_is_said_plainly_never_shown_as_zero(): void
    {
        $record = $this->record(['humidity' => null, 'wind_speed' => null]);
        $html = $this->actingAs($this->farmer())->get(route('climate-records.show', ['climate_record' => $record, 'modal' => 1]))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'Not available'));
        $this->assertStringNotContainsString('0%', $html);
    }

    public function test_the_full_page_stays_for_direct_links_and_staff_keep_their_own_view(): void
    {
        $record = $this->record();

        $this->actingAs($this->farmer())->get(route('climate-records.show', $record))->assertOk()->assertSee('Back to Climate Records');

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        // Staff get their own record pop-up (crud._modal): the record's fields without the page layout.
        $this->actingAs($mao)->get(route('climate-records.show', ['climate_record' => $record, 'modal' => 1]))->assertOk()
            ->assertDontSee('<html', false)->assertSee('Climate Record Details')->assertSee('data-ic-close', false);
        $this->actingAs($mao)->get(route('climate-records.show', $record))->assertOk()->assertSee('<html', false);
    }
}
