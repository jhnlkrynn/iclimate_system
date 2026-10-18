<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4RuntimePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_heat_map_initialisation_and_refresh_are_singleton_and_cancellable(): void
    {
        $source = file_get_contents(resource_path('js/heat-map.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString("if (!root || root.dataset.ready) return;", $source);
        // Layer data, barangay detail, live refresh and the two boundary loads each have one.
        $this->assertSame(5, substr_count($source, 'new AbortController()'));
        // A new layer or period cancels the request still on its way.
        $this->assertStringContainsString("apiController?.abort();\n        apiController = new AbortController();", $source);
        $this->assertStringContainsString('if (sequence !== apiSequence) return;', $source);
        $this->assertStringContainsString("window.addEventListener('pagehide', cancelRefresh, { once: true });", $source);
        $this->assertStringContainsString("if (document.hidden || generation !== refreshGeneration) return;", $source);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $source);
    }

    public function test_heat_map_initial_html_contains_summaries_only_and_detail_is_on_demand(): void
    {
        $user = User::factory()->maoPersonnel()->create();
        $page = $this->actingAs($user)->get(route('heat-map.index'))->assertOk()->getContent();

        $this->assertSame(5, substr_count($page, 'data-heat-map-view='));
        $this->assertStringNotContainsString('data-heat-map-view="week:Prenza"', $page);
        $this->assertStringNotContainsString('data-heat-map-view="2weeks:Prenza"', $page);

        $this->actingAs($user)
            ->getJson(route('heat-map.detail', ['range' => 'week', 'barangay' => 'Prenza']))
            ->assertOk()
            ->assertJsonPath('range', 'week')
            ->assertJsonPath('barangay', 'Prenza')
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'data-heat-map-detail="week:Prenza"'));
    }

    public function test_messages_polling_has_one_timer_and_aborts_when_stopped(): void
    {
        $source = file_get_contents(resource_path('views/messages/index.blade.php'));

        $this->assertIsString($source);
        $this->assertSame(1, substr_count($source, 'setInterval(poll, POLL_INTERVAL_MS)'));
        $this->assertStringContainsString('let pollAbortController = null;', $source);
        $this->assertStringContainsString('signal: controller.signal,', $source);
        $this->assertStringContainsString('pollAbortController?.abort();', $source);
        $this->assertStringContainsString('if (!pollUrl || pollTimer || document.hidden) return;', $source);
        $this->assertStringContainsString("window.addEventListener('pagehide', stopPolling, { once: true });", $source);
    }

    public function test_message_poll_only_returns_messages_after_the_cursor(): void
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $conversation = Conversation::query()->create([
            'participant_one_id' => min($farmer->id, $mao->id),
            'participant_two_id' => max($farmer->id, $mao->id),
            'last_message_at' => now(),
        ]);
        $first = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $farmer->id,
            'body' => 'First',
        ]);
        $second = ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $mao->id,
            'body' => 'Second',
        ]);

        $this->actingAs($farmer)
            ->getJson(route('messages.poll', $conversation).'?after_id='.$first->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('messages.0.body', 'Second');
    }
}
