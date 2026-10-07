<?php

namespace Tests\Feature\Manuscript;

use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 6: MAO alerts (send now / schedule), delivery and read counts. */
class Phase6AlertsTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $barangay): User
    {
        return User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay]);
    }

    private function alert(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Typhoon signal no. 2', 'type' => 'typhoon', 'severity' => 'high',
            'message' => 'Strong winds expected tomorrow.', 'recommended_action' => 'Harvest mature palay today.',
            'recipients' => 'barangays', 'barangays' => ['Balibago', 'Cumba'], 'send' => 'now',
        ];
    }

    public function test_an_alert_to_two_barangays_reaches_only_their_farmers_as_weather_alerts(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $balibago = $this->farmer('Balibago');
        $this->farmer('Cumba');
        $prenza = $this->farmer('Prenza');

        $this->actingAs($mao)->post(route('mao.alerts.store'), $this->alert())->assertRedirect(route('mao.alerts.index'))
            ->assertSessionHas('success', 'Alert sent. 2 farmers were notified.');

        $this->assertSame(2, PlantingAdvisory::query()->where('source', 'MAO Alert')->where('status', 'published')->count());
        $notice = Notification::query()->where('user_id', $balibago->id)->sole();
        $this->assertSame(Notification::CATEGORY_WEATHER, $notice->category);
        $this->assertNotNull($notice->planting_advisory_id);
        $this->assertFalse(Notification::query()->where('user_id', $prenza->id)->exists());
    }

    public function test_history_counts_delivered_and_read(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $reader = $this->farmer('Balibago');
        $this->farmer('Cumba');
        $this->actingAs($mao)->post(route('mao.alerts.store'), $this->alert(['recipients' => 'all']));

        $notice = Notification::query()->where('user_id', $reader->id)->sole();
        $this->actingAs($reader)->patch(route('notifications.mark-read', $notice))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);

        $row = $this->actingAs($mao)->get(route('mao.alerts.index'))->assertOk()->assertSee('All farmers')->viewData('history')->first();
        $this->assertSame(2, $row['delivered']);
        $this->assertSame(1, $row['read']);
        $this->assertSame(50, $row['read_percent']);
    }

    public function test_a_scheduled_alert_waits_then_the_command_publishes_it(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = $this->farmer('Balibago');
        $when = now(\App\Support\BusinessClock::timezone())->addHour()->format('Y-m-d\TH:i');

        $this->actingAs($mao)->post(route('mao.alerts.store'), $this->alert(['send' => 'later', 'scheduled_for' => $when, 'barangays' => ['Balibago']]))
            ->assertSessionHas('success');
        $advisory = PlantingAdvisory::query()->sole();
        $this->assertSame(PlantingAdvisory::STATUS_SCHEDULED, $advisory->status);
        $this->assertFalse(Notification::query()->where('user_id', $farmer->id)->exists());

        $this->artisan('iclimate:publish-scheduled-alerts')->assertSuccessful();
        $this->assertSame(PlantingAdvisory::STATUS_SCHEDULED, $advisory->fresh()->status);   // not due yet

        $this->travel(2)->hours();
        $this->artisan('iclimate:publish-scheduled-alerts')->assertSuccessful();
        $this->assertSame(PlantingAdvisory::STATUS_PUBLISHED, $advisory->fresh()->status);
        $this->assertTrue(Notification::query()->where('user_id', $farmer->id)->where('planting_advisory_id', $advisory->id)->exists());
    }

    public function test_a_scheduled_alert_can_be_cancelled_and_a_past_time_is_refused(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $tz = \App\Support\BusinessClock::timezone();

        $this->actingAs($mao)->post(route('mao.alerts.store'), $this->alert(['send' => 'later', 'scheduled_for' => now($tz)->subHour()->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('scheduled_for');

        $this->actingAs($mao)->post(route('mao.alerts.store'), $this->alert(['send' => 'later', 'scheduled_for' => now($tz)->addDay()->format('Y-m-d\TH:i')]));
        $batch = PlantingAdvisory::query()->first()->metadata['alert_batch'];
        $this->actingAs($mao)->post(route('mao.alerts.cancel', $batch))->assertSessionHas('success', 'Scheduled alert cancelled.');
        $this->assertSame(0, PlantingAdvisory::query()->where('status', PlantingAdvisory::STATUS_SCHEDULED)->count());
    }

    public function test_mark_all_read_dates_the_notifications(): void
    {
        $farmer = $this->farmer('Balibago');
        Notification::query()->create(['user_id' => $farmer->id, 'title' => 'x', 'message' => 'y', 'type' => 'Announcement', 'is_read' => false]);

        $this->actingAs($farmer)->post(route('notifications.mark-all-read'));

        $this->assertNotNull(Notification::query()->where('user_id', $farmer->id)->value('read_at'));
    }

    public function test_only_mao_sends_alerts_and_the_advisories_page_links_to_it(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->get(route('planting-advisories.index'))->assertOk()->assertSee('data-send-alert', false);

        foreach ([User::ROLE_FARMER, User::ROLE_IT_EXPERT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('mao.alerts.store'), $this->alert())->assertStatus(403);
        }
    }
}
