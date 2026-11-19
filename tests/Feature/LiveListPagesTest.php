<?php

namespace Tests\Feature;

use App\Events\DashboardChanged;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Live list pages (resources/js/live-page.js): a list others change marks its rows with data-live-region and the data
 * areas it shows, and the server signals those areas on change. Browser timing is measured separately (isolated
 * Reverb probe); these tests pin the server contract the browser code relies on.
 */
class LiveListPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_record_lists_mark_their_rows_with_the_areas_they_show(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        $it = User::factory()->maoHead()->create();

        $this->actingAs($mao)->get(route('climate-records.index'))->assertOk()
            ->assertSee('data-live-region="records" data-live-areas="climate"', false);
        $this->actingAs($mao)->get(route('rice-productions.index'))->assertOk()
            ->assertSee('data-live-region="records" data-live-areas="rice', false);
        $this->actingAs($it)->get(route('users.index'))->assertOk()
            ->assertSee('data-live-region="records" data-live-areas="users"', false);
    }

    public function test_farmer_lists_and_queues_refresh_in_place(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($farmer)->get(route('notifications.index'))->assertOk()
            ->assertSee('data-live-region="records" data-live-areas="notifications', false);
        $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()
            ->assertSee('data-live-region="calendar" data-live-areas="planting,advisories,community-posts"', false);
        $this->actingAs($mao)->get(route('mao.farmer-approvals.index'))->assertOk()
            ->assertSee('data-live-region="approvals" data-live-areas="users,farmers"', false);
    }

    public function test_a_new_message_signals_the_recipients_conversation_list_without_its_text(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        Event::fake([DashboardChanged::class]);

        $this->actingAs($mao)->postJson(route('messages.store'), ['recipient_id' => $farmer->id, 'body' => 'Private words'])->assertSessionHasNoErrors();

        $this->assertNotNull(Conversation::query()->first());
        Event::assertDispatched(DashboardChanged::class, function (DashboardChanged $event) use ($farmer, $mao): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

            return $event->areas === ['messages']
                && $channels === ["private-user.{$farmer->id}"]
                && ! in_array("private-user.{$mao->id}", $channels, true)
                && ! str_contains(json_encode($event->broadcastWith()), 'Private words');
        });
    }
}
