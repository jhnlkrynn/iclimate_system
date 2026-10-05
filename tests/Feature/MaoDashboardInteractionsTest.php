<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** What an MAO officer can click on the dashboard actually works and reflects real records. */
class MaoDashboardInteractionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);       // no live network: the weather sections fall back to their error state
    }

    private function mao(): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'name' => 'MAO Officer']);
    }

    private function notify(User $user, string $title, bool $read = false): Notification
    {
        return Notification::query()->create(['user_id' => $user->id, 'title' => $title, 'message' => 'Body text', 'type' => 'Announcement', 'is_read' => $read]);
    }

    public function test_every_link_on_the_dashboard_opens_for_an_mao_officer(): void
    {
        $mao = $this->mao();
        $this->notify($mao, 'Unread one');
        PlantingAdvisory::factory()->create(['status' => PlantingAdvisory::STATUS_PENDING_REVIEW]);

        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();
        preg_match_all('/href="([^"#][^"]*)"/', $html, $m);
        $own = collect($m[1])->map(fn ($h) => html_entity_decode($h))
            ->filter(fn (string $h): bool => str_starts_with($h, url('/')) && ! str_contains($h, '/build/') && ! str_contains($h, '/images/') && ! str_contains($h, '/css/') && ! str_contains($h, '/js/'))
            ->reject(fn (string $h): bool => str_contains($h, 'logout'))
            ->unique()->values();

        $this->assertGreaterThan(5, $own->count());
        foreach ($own as $link) {
            $status = $this->actingAs($mao)->get($link)->getStatusCode();
            $this->assertContains($status, [200, 302], "{$link} answered {$status} for an MAO officer");
        }
    }

    public function test_the_notifications_button_shows_the_real_unread_count_and_an_attention_item(): void
    {
        $mao = $this->mao();
        $other = $this->mao();
        $this->notify($mao, 'One');
        $this->notify($mao, 'Two');
        $this->notify($mao, 'Already read', true);
        $this->notify($other, 'Someone else');

        $data = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->viewData('mao');

        $this->assertSame(2, $data['unread_notifications']);
        $this->assertContains('2 Unread Notifications', array_column($data['attention'], 'title'));
    }

    public function test_opening_a_notification_marks_it_read(): void
    {
        $mao = $this->mao();
        $note = $this->notify($mao, 'Heavy rain warning');

        $this->actingAs($mao)->get(route('notifications.show', $note))->assertOk();

        $this->assertTrue($note->fresh()->is_read);
        $this->assertSame(0, $this->actingAs($mao)->get(route('mao.dashboard'))->viewData('mao')['unread_notifications']);
    }
}
