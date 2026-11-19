<?php

namespace Tests\Feature;

use App\Broadcasting\ResilientBroadcastManager;
use App\Broadcasting\RetryBroadcastEvent;
use App\Events\NotificationChanged;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Reverb being stopped must not break the action that triggered a realtime update. Failed updates are logged
 * (without payload or URL secrets), retried a bounded number of times from the "broadcasts" queue, and dropped
 * when too old; the saved records stay authoritative.
 */
class ResilientBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private ?string $bootDefault = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDefault = config('broadcasting.default');
        SwitchableBroadcaster::$down = false;
        SwitchableBroadcaster::$reject = false;
        SwitchableBroadcaster::$sent = [];
        SwitchableBroadcaster::$attempts = 0;
        Broadcast::extend('switchable', fn () => new SwitchableBroadcaster);
        config([
            'broadcasting.default' => 'switchable',
            'broadcasting.connections.switchable' => ['driver' => 'switchable'],
            'queue.default' => 'database',
            'broadcasting.retry.connection' => 'database',
        ]);
    }

    public function test_the_application_uses_the_resilient_manager(): void
    {
        $this->assertInstanceOf(ResilientBroadcastManager::class, app(\Illuminate\Contracts\Broadcasting\Factory::class));
        $this->assertInstanceOf(ResilientBroadcastManager::class, Broadcast::getFacadeRoot());
    }

    public function test_reverb_running_delivers_immediately_without_queueing_a_retry(): void
    {
        [$mao, $farmer] = $this->people();

        $this->createNotification($mao, $farmer)->assertOk();

        $sent = $this->sent('notification.changed');
        $this->assertCount(1, $sent);
        $this->assertSame(['private-user.'.$farmer->id], $sent[0]['channels']);
        $this->assertSame(0, $this->retriesWaiting());
    }

    public function test_reverb_stopped_notification_is_saved_and_a_retry_is_queued_without_leaking_secrets(): void
    {
        Log::spy();
        SwitchableBroadcaster::$down = true;
        [$mao, $farmer] = $this->people();

        $this->createNotification($mao, $farmer)->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $farmer->id)->where('title', 'Instant notice')->count());
        $this->assertSame(1, $this->retriesWaiting(NotificationChanged::class));
        // One warning per outage window (it names the first update that failed), never one per update.
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $logged = $message.json_encode($context);

            return str_contains($message, 'queued for retry')
                && str_contains($context['reason'] ?? '', 'cURL error 7')
                && ! str_contains($logged, 'SECRET-APP-KEY')
                && ! str_contains($logged, 'auth_signature')
                && ! str_contains($logged, 'This should arrive now.');
        })->once();
    }

    public function test_reverb_stopped_mark_as_read_and_mark_all_read_still_succeed(): void
    {
        $farmer = User::factory()->farmer()->create();
        $first = Notification::query()->create(['user_id' => $farmer->id, 'title' => 'One', 'message' => 'One', 'type' => 'Announcement', 'is_read' => false]);
        Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Two', 'message' => 'Two', 'type' => 'Announcement', 'is_read' => false]);
        SwitchableBroadcaster::$down = true;

        $this->actingAs($farmer)->patchJson(route('notifications.mark-read', $first))->assertOk()->assertJsonPath('unread', 1);
        $this->actingAs($farmer)->postJson(route('notifications.mark-all-read'))->assertOk()->assertJsonPath('unread', 0);

        $this->assertSame(0, Notification::query()->where('user_id', $farmer->id)->where('is_read', false)->count());
    }

    public function test_reverb_stopped_message_is_saved_once_and_a_retry_is_queued(): void
    {
        SwitchableBroadcaster::$down = true;
        [$farmer, , $conversation] = $this->conversation();
        $payload = ['body' => 'Saved while Reverb is down', 'client_message_id' => 'offline-1'];

        $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();
        $this->actingAs($farmer)->postJson(route('messages.reply', $conversation), $payload)->assertOk();

        $this->assertSame(1, ConversationMessage::query()->where('conversation_id', $conversation->id)->count());
        $this->assertSame(1, $this->retriesWaiting(\App\Events\MessageCreated::class), 'the idempotent client retry adds no second broadcast');
    }

    public function test_a_rolled_back_change_neither_broadcasts_nor_queues_a_retry(): void
    {
        SwitchableBroadcaster::$down = true;
        $farmer = User::factory()->farmer()->create();

        try {
            DB::transaction(function () use ($farmer): void {
                Notification::query()->create(['user_id' => $farmer->id, 'title' => 'Rolled back', 'message' => 'x', 'type' => 'Announcement', 'is_read' => false]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Notification::query()->where('title', 'Rolled back')->count());
        $this->assertSame([], $this->sent('notification.changed'));
        $this->assertSame(0, $this->retriesWaiting(NotificationChanged::class));
    }

    public function test_with_the_worker_stopped_the_retry_waits_and_is_delivered_once_reverb_and_the_worker_are_back(): void
    {
        SwitchableBroadcaster::$down = true;
        [$mao, $farmer] = $this->people();
        $this->createNotification($mao, $farmer)->assertOk();
        $notificationId = Notification::query()->where('title', 'Instant notice')->value('id');

        // Worker stopped: nothing is delivered, the retry stays queued.
        $this->travel(5)->minutes();
        $this->assertSame(1, $this->retriesWaiting(NotificationChanged::class));
        $this->assertSame([], SwitchableBroadcaster::$sent);

        // Reverb recovers and the worker runs.
        SwitchableBroadcaster::$down = false;
        $this->runWorker();

        $this->assertCount(1, $this->sent('notification.changed'));
        $sent = $this->sent('notification.changed')[0];
        $this->assertSame(['private-user.'.$farmer->id], $sent['channels'], 'same private channel as the original event');
        $this->assertSame('notification.changed', $sent['event']);
        $this->assertSame($notificationId, $sent['payload']['notification_id']);
        $this->assertSame(1, $sent['payload']['unread'], 'unread count is read again when the retry runs');
        $this->assertSame(0, $this->retriesWaiting());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_a_circuit_open_retry_releases_through_the_queue_contract(): void
    {
        $job = (new RetryBroadcastEvent(new NotificationChanged(1, 'sync')))->withFakeQueueInteractions();
        $delay = (int) config('broadcasting.retry.circuit_seconds', 30);
        Cache::put('broadcasting:reverb-unavailable-until', now()->addSeconds($delay)->getTimestamp(), $delay);

        $job->handle(Broadcast::getFacadeRoot());

        $job->assertReleased($delay);
    }

    public function test_a_real_isolated_worker_releases_then_recovers_without_duplicate_delivery(): void
    {
        SwitchableBroadcaster::$down = true;
        [$mao, $farmer] = $this->people();
        $this->createNotification($mao, $farmer)->assertOk();
        $notificationId = Notification::query()->where('title', 'Instant notice')->value('id');

        // The first retry is available, but the circuit is still open. The actual queue:work command must release it,
        // not fail the job with an undefined method or create a second notification.
        $this->travel((int) config('broadcasting.retry.first_delay_seconds', 10))->seconds();
        $this->runWorker();
        $this->assertSame(1, $this->retriesWaiting(NotificationChanged::class));
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame([], $this->sent('notification.changed'));

        // Reverb recovers after the release delay; the same queued event is delivered exactly once.
        SwitchableBroadcaster::$down = false;
        $this->travel((int) config('broadcasting.retry.circuit_seconds', 30) + 1)->seconds();
        $this->runWorker();

        $sent = $this->sent('notification.changed');
        $this->assertCount(1, $sent);
        $this->assertSame($notificationId, $sent[0]['payload']['notification_id']);
        $this->assertSame(0, $this->retriesWaiting());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, Notification::query()->where('title', 'Instant notice')->count());
    }

    public function test_a_retry_that_keeps_failing_gives_up_after_its_attempts_and_lands_in_failed_jobs(): void
    {
        Log::spy();
        config(['broadcasting.retry.fresh_minutes' => 60]);
        SwitchableBroadcaster::$down = true;
        [$mao, $farmer] = $this->people();
        $this->createNotification($mao, $farmer)->assertOk();
        $tries = (int) config('broadcasting.retry.tries');
        $queued = $this->retriesWaiting();
        $this->assertGreaterThan(0, $queued);

        for ($attempt = 1; $attempt <= $tries; $attempt++) {
            $this->travel(4)->minutes();
            $this->runWorker();
        }

        $this->assertSame(0, $this->retriesWaiting(), 'no endless retrying');
        $this->assertSame($queued, DB::table('failed_jobs')->count(), 'every exhausted retry is recorded once');
        $this->assertStringContainsString('RetryBroadcast', (string) DB::table('failed_jobs')->value('payload'));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'gave up after its last attempt')
            && ($context['event'] ?? null) === NotificationChanged::class)->once();
        $this->assertSame([], SwitchableBroadcaster::$sent);
    }

    public function test_a_retry_picked_up_after_the_freshness_window_is_dropped_instead_of_sent_late(): void
    {
        SwitchableBroadcaster::$down = true;
        [$mao, $farmer] = $this->people();
        $this->createNotification($mao, $farmer)->assertOk();

        SwitchableBroadcaster::$down = false;
        $this->travel((int) config('broadcasting.retry.fresh_minutes') + 1)->minutes();
        $this->runWorker();

        $this->assertSame([], SwitchableBroadcaster::$sent);
        $this->assertSame(0, $this->retriesWaiting());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_the_action_still_succeeds_when_the_retry_cannot_be_queued(): void
    {
        Log::spy();
        SwitchableBroadcaster::$down = true;
        config(['queue.connections.broken' => ['driver' => 'database', 'table' => 'no_such_jobs_table', 'queue' => 'default', 'retry_after' => 90],
            'broadcasting.retry.connection' => 'broken']);
        [$mao, $farmer] = $this->people();

        $this->createNotification($mao, $farmer)->assertOk();

        $this->assertSame(1, Notification::query()->where('title', 'Instant notice')->count());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'could not be delivered or queued')
            && ($context['event'] ?? null) === NotificationChanged::class
            && ! str_contains(json_encode($context), 'SECRET-APP-KEY'))->once();
    }

    public function test_a_notification_to_every_farmer_with_reverb_down_tries_once_logs_once_and_caps_retries(): void
    {
        Log::spy();
        config(['broadcasting.retry.max_queued_per_window' => 5]);
        SwitchableBroadcaster::$down = true;
        $mao = User::factory()->maoPersonnel()->create();
        User::factory()->farmer()->count(12)->create();

        $this->actingAs($mao)->postJson(route('notifications.store'), [
            'recipient_scope' => 'all', 'title' => 'Typhoon signal', 'message' => 'Secure your harvest.', 'type' => 'Announcement',
        ])->assertOk();

        $this->assertGreaterThanOrEqual(12, Notification::query()->where('title', 'Typhoon signal')->count(), 'every notification is saved');
        $this->assertSame(1, SwitchableBroadcaster::$attempts, 'only the first update waits for Reverb; the circuit skips the rest');
        $this->assertSame(5, $this->retriesWaiting(), 'retries are capped per window');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'treated as unavailable'))->once();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'retry limit (5) reached'))->once();
    }

    public function test_the_circuit_closes_after_its_window_and_updates_are_sent_immediately_again(): void
    {
        [$mao, $farmer] = $this->people();
        SwitchableBroadcaster::$down = true;
        $this->createNotification($mao, $farmer)->assertOk();
        $attemptsWhileDown = SwitchableBroadcaster::$attempts;

        // Still inside the window: no new immediate attempt.
        $this->createNotification($mao, $farmer)->assertOk();
        $this->assertSame($attemptsWhileDown, SwitchableBroadcaster::$attempts);

        // Reverb is back and the window has passed.
        SwitchableBroadcaster::$down = false;
        $this->travel((int) config('broadcasting.retry.circuit_seconds') + 1)->seconds();
        $this->createNotification($mao, $farmer)->assertOk();

        $this->assertGreaterThan($attemptsWhileDown, SwitchableBroadcaster::$attempts);
        $this->assertNotEmpty($this->sent('notification.changed'));
    }

    public function test_a_permanent_rejection_is_logged_as_an_error_and_not_retried(): void
    {
        Log::spy();
        SwitchableBroadcaster::$reject = true;
        [$mao, $farmer] = $this->people();

        $this->createNotification($mao, $farmer)->assertOk();

        $this->assertSame(1, Notification::query()->where('title', 'Instant notice')->count());
        $this->assertSame(0, $this->retriesWaiting());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'not retried')
            && ($context['event'] ?? null) === NotificationChanged::class)->once();
    }

    public function test_barangay_channels_are_valid_channel_names_and_still_authorize(): void
    {
        $event = new \App\Events\WeatherChanged(['San Diego', 'Binubusan'], 'evt-1', '2026-11-10T00:00:00Z');
        $names = array_map(fn ($channel): string => $channel->name, $event->broadcastOn());

        $this->assertContains('private-barangay.San_Diego', $names);
        $this->assertContains('private-barangay.Binubusan', $names);
        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^[-a-zA-Z0-9_=@,.;]+$/', $name, 'valid Pusher/Reverb channel name');
        }

        $farmer = User::factory()->farmer()->create(['barangay' => 'San Diego']);
        $other = User::factory()->farmer()->create(['barangay' => 'Binubusan']);
        // routes/channels.php registered its callbacks on the connection that was the default at boot.
        config(['broadcasting.default' => $this->bootDefault]);
        $authorize = Broadcast::connection()->getChannels()->get('barangay.{barangay}');
        $this->assertTrue($authorize($farmer, 'San_Diego'));
        $this->assertFalse($authorize($other, 'San_Diego'));
    }

    public function test_the_retry_job_is_bounded(): void
    {
        $farmer = User::factory()->farmer()->create();
        $job = new RetryBroadcastEvent(new NotificationChanged($farmer->id, 'sync'));

        $this->assertSame((int) config('broadcasting.retry.tries'), $job->tries);
        $this->assertNotEmpty($job->backoff);
        $this->assertSame(30, $job->timeout);
        $this->assertTrue($job->deleteWhenMissingModels);
        $this->assertGreaterThan(now()->getTimestamp(), $job->freshUntil);
    }

    private function createNotification(User $mao, User $farmer): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($mao)->postJson(route('notifications.store'), [
            'recipient_scope' => 'specific',
            'user_id' => $farmer->id,
            'title' => 'Instant notice',
            'message' => 'This should arrive now.',
            'type' => 'Announcement',
        ]);
    }

    /** Retries waiting on the broadcasts queue, optionally for one event class. */
    private function retriesWaiting(?string $eventClass = null): int
    {
        return DB::table('jobs')->where('queue', 'broadcasts')->pluck('payload')
            ->filter(fn (string $payload): bool => $eventClass === null || str_contains($payload, 'RetryBroadcast:'.str_replace('\\', '\\\\', $eventClass)))
            ->count();
    }

    /** @return list<array{channels: list<string>, event: string, payload: array}> */
    private function sent(string $event): array
    {
        return array_values(array_filter(SwitchableBroadcaster::$sent, fn (array $sent): bool => $sent['event'] === $event));
    }

    private function runWorker(): void
    {
        // The worker runs inside the test process, which holds the whole suite (~300 MB): the default 128 MB
        // limit would stop it after one job with exit code 12 (EXIT_MEMORY_LIMIT).
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'broadcasts', '--stop-when-empty' => true, '--sleep' => 0, '--memory' => 4096])->assertExitCode(0);
    }

    /** @return array{0: User, 1: User} */
    private function people(): array
    {
        return [User::factory()->maoPersonnel()->create(), User::factory()->farmer()->create()];
    }

    private function conversation(): array
    {
        $farmer = User::factory()->farmer()->create();
        $mao = User::factory()->maoPersonnel()->create();
        $conversation = Conversation::query()->create([
            'participant_one_id' => min($farmer->id, $mao->id),
            'participant_two_id' => max($farmer->id, $mao->id),
        ]);

        return [$farmer, $mao, $conversation];
    }
}

/** Stands in for Reverb: delivers, or fails the way the Pusher client does when Reverb is stopped. */
class SwitchableBroadcaster extends Broadcaster
{
    public static bool $down = false;

    /** Rejects the event the way the Pusher client rejects an invalid channel name (not a delivery failure). */
    public static bool $reject = false;

    /** @var list<array{channels: list<string>, event: string, payload: array}> */
    public static array $sent = [];

    /** Every delivery attempt, successful or not. */
    public static int $attempts = 0;

    public function auth($request)
    {
        return true;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        self::$attempts++;

        if (self::$reject) {
            throw new \Pusher\PusherException('Invalid channel name private-barangay.San Diego');
        }

        if (self::$down) {
            throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to 127.0.0.1:8080 after 0 ms: Could not connect to server (see https://curl.se/libcurl/c/libcurl-errors.html) for http://127.0.0.1:8080/apps/iclimate-local/events?auth_key=SECRET-APP-KEY&auth_timestamp=1&auth_version=1.0&body_md5=x&auth_signature=deadbeef');
        }

        unset($payload['socket']);
        self::$sent[] = ['channels' => array_map('strval', $this->formatChannels($channels)), 'event' => $event, 'payload' => $payload];
    }
}
