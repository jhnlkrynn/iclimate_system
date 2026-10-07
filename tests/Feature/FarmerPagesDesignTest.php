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
        foreach (['class="msg-wrap"', 'data-poll-url=', 'data-send-url=', 'id="threadSearch"', 'id="threadList"', 'id="chatWindow"', 'id="composeForm"', 'id="composeInput"', 'id="composeSend"', 'id="attachmentInput"', 'id="unsendConfirm"', 'data-conversation-load-state', 'pushState', 'popstate', 'AbortController', 'Open normally'] as $hook) {
            $this->assertStringContainsString($hook, $html, $hook);
        }
        // The unsend dialog is fixed-position, so it must sit outside the .fad size container.
        $this->assertGreaterThan(strrpos(substr($html, 0, strpos($html, 'id="unsendConfirm"')), '</main>'), strpos($html, 'id="unsendConfirm"'));

        // MAO gets the staff header (it-pages.css), not the farmer one.
        $this->actingAs($mao)->get(route('messages.index'))->assertOk()->assertDontSee('fad fmsg', false)->assertSee('<h1>Messages</h1>', false);
    }

    public function test_predictions_use_the_farmer_header_and_staff_keep_theirs(): void
    {
        // One Predictions page (2026-10): farmers get the farmer page header; staff keep the staff header.
        $html = $this->actingAs($this->farmer())->get(route('weather-predictions.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<header class="fad-hero">', $html);
        $this->assertStringContainsString('<h1>Weather and Rice Yield Forecast</h1>', $html);
        $this->assertStringNotContainsString('mfd itd itp', $html);

        $this->actingAs(User::factory()->maoPersonnel()->create())->get(route('weather-predictions.index'))
            ->assertOk()->assertDontSee('<header class="fad-hero">', false)->assertSee('mfd itd itp', false);
    }

    private function farmer(): User
    {
        return User::query()->where('email', 'pages-farmer@example.test')->first()
            ?? User::factory()->farmer()->create(['email' => 'pages-farmer@example.test', 'barangay' => 'Prenza']);
    }
}
