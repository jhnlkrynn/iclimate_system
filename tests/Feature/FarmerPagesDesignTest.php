<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Messages, Notifications, Climate Records, Predictions and Rice Production use the farmer design system for farmers,
 * with the same data, filters and actions; MAO and IT keep their original pages.
 */
class FarmerPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_climate_records_show_real_figures_to_farmers_and_staff_keep_their_table(): void
    {
        foreach ([['2026-01-05', 20.0, 30.0, 80.0], ['2026-02-05', 40.0, 34.0, 70.0]] as [$date, $rain, $temp, $humidity]) {
            ClimateRecord::query()->create(['record_date' => $date, 'rainfall' => $rain, 'temperature' => $temp, 'humidity' => $humidity, 'wind_speed' => 5, 'season' => 'Dry', 'source' => 'PAGASA']);
        }

        // Only the entered rows here: the Ambulong station record is checked in ClimateRecordsFarmerTest.
        $this->app->instance(\App\Services\Climate\AmbulongHistoricalClimateDataset::class, new class extends \App\Services\Climate\AmbulongHistoricalClimateDataset
        {
            public function load(): array
            {
                return ['available' => true, 'message' => null, 'observations' => collect(), 'excluded_sentinel_count' => 0, 'invalid_row_count' => 0, 'duplicate_count' => 0];
            }
        });

        $html = $this->actingAs($this->farmer())->get(route('climate-records.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<h1>Climate Records for Lian, Batangas</h1>', $html);
        $this->assertStringContainsString('32.0<small>°C</small>', $html);            // average of 30 and 34
        $this->assertStringContainsString('60.0<small>mm</small>', $html);            // 20 + 40
        $this->assertStringContainsString('75<small>%</small>', $html);               // humidity shown as a whole percent
        $this->assertStringContainsString('The wettest record is Feb 5, 2026, with 40.0 mm of rain.', $html);
        $this->assertStringContainsString('Feb 5, 2026', $html);
        $this->assertStringNotContainsString('2026-02-05 00:00:00', $html);           // dates are readable

        $filtered = $this->actingAs($this->farmer())->get(route('climate-records.index', ['search' => '2026-01']))->getContent();
        $this->assertStringContainsString('30.0<small>°C</small>', $filtered);        // figures follow the filters

        $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('climate-records.index'))
            ->assertOk()->assertSee('Find, view, and update records here.')->assertDontSee('frec-kpis', false);
    }

    public function test_rice_production_is_read_only_for_farmers_and_marks_their_barangay(): void
    {
        RiceProduction::query()->create(['barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => 4.0, 'area_hectares' => 2, 'total_production' => 8, 'year' => 2024]);
        RiceProduction::query()->create(['barangay' => 'Lumaniag', 'season' => 'Dry', 'irrigation_type' => 'Rainfed', 'yield_per_hectare' => 5.0, 'area_hectares' => 3, 'total_production' => 15, 'year' => 2023]);

        $html = $this->actingAs($this->farmer())->get(route('rice-productions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<h1>Rice Production</h1>', $html);
        $this->assertStringContainsString('1 from your barangay (Prenza)', $html);
        $this->assertStringContainsString('23.0<small>t</small>', $html);             // 8 + 15
        $this->assertStringContainsString('4.50<small>t/ha</small>', $html);
        $this->assertStringNotContainsString('Add Production Record', $html);         // farmers cannot add records
        $this->assertStringNotContainsString(route('rice-productions.create'), $html);
        $this->actingAs($this->farmer())->get(route('rice-productions.create'))->assertForbidden();

        $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('rice-productions.index'))
            ->assertOk()->assertSee('Create Rice Production Record');
    }

    public function test_notifications_list_tabs_and_read_state(): void
    {
        $farmer = $this->farmer();
        $unread = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Heavy rain this week', 'message' => 'Check your drainage.', 'type' => 'Warning', 'is_read' => false]);
        Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Seed distribution', 'message' => 'Bring your ID.', 'type' => 'Announcement', 'is_read' => true]);

        $html = $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<h1>Notifications</h1>', $html);
        $this->assertStringContainsString('Mark All as Read', $html);
        $this->assertMatchesRegularExpression('/frec-noteitem is-unread.*?Heavy rain this week/s', $html);
        $this->assertStringContainsString(route('notifications.mark-read', $unread), $html);
        $this->assertMatchesRegularExpression('/<span>New<\/span>/', $html);              // status in words, not colour alone

        $unreadTab = $this->actingAs($farmer)->get(route('notifications.index', ['is_read' => 0]))->getContent();
        $this->assertStringContainsString('Heavy rain this week', $unreadTab);
        $this->assertStringNotContainsString('Seed distribution', $unreadTab);

        $this->actingAs($farmer)->get(route('notifications.show', $unread))->assertOk()->assertSee('Check your drainage.');
        $this->assertTrue($unread->fresh()->is_read);                                     // opening it reads it

        $after = $this->actingAs($farmer)->get(route('notifications.index'))->getContent();
        $this->assertStringNotContainsString('frec-noteitem is-unread', $after);
        $this->assertStringNotContainsString('Mark All as Read', $after);
    }

    public function test_an_empty_notification_list_says_so(): void
    {
        $this->actingAs($this->farmer())->get(route('notifications.index'))->assertOk()->assertSee('No notifications.');
    }

    public function test_messages_keep_every_hook_the_script_uses(): void
    {
        $farmer = $this->farmer();
        $mao = User::factory()->maoPersonnel()->create(['name' => 'MAO Desk']);
        $this->actingAs($farmer)->post(route('messages.store'), ['recipient_id' => $mao->id, 'body' => 'When is the seed distribution?']);
        $conversation = Conversation::query()->firstOrFail();

        $html = $this->actingAs($farmer)->get(route('messages.show', $conversation))->assertOk()->getContent();
        $this->assertStringContainsString('<div class="fad fmsg">', $html);
        $this->assertStringContainsString('When is the seed distribution?', $html);
        foreach (['class="msg-wrap"', 'data-poll-url=', 'data-send-url=', 'id="threadSearch"', 'id="threadList"', 'id="chatWindow"', 'id="composeForm"', 'id="composeInput"', 'id="composeSend"', 'id="attachmentInput"', 'id="unsendConfirm"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        // The unsend dialog is fixed-position, so it must sit outside the .fad size container.
        $this->assertGreaterThan(strrpos(substr($html, 0, strpos($html, 'id="unsendConfirm"')), '</main>'), strpos($html, 'id="unsendConfirm"'));

        // MAO gets the staff header (it-pages.css), not the farmer one.
        $this->actingAs($mao)->get(route('messages.index'))->assertOk()->assertDontSee('fad fmsg', false)->assertSee('<h1>Messages</h1>', false);
    }

    public function test_predictions_use_the_farmer_header_and_keep_the_confirm_dialog_outside_it(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('weather-predictions.index', ['tab' => 'rice-yield']))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="fad fpred">', $html);
        $this->assertStringContainsString('<h1>Predictions</h1>', $html);
        foreach (['id="rice-yield-tab"', 'id="weather-forecast-tab"', 'id="yieldPredictionForm"', 'id="yieldConfirmModal"'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }

        $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('weather-predictions.index'))
            ->assertOk()->assertDontSee('fad fpred', false);
    }

    private function farmer(): User
    {
        return User::query()->where('email', 'pages-farmer@example.test')->first()
            ?? User::factory()->farmer()->create(['email' => 'pages-farmer@example.test', 'barangay' => 'Prenza']);
    }
}
