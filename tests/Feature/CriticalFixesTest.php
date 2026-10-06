<?php

namespace Tests\Feature;

use App\Jobs\GenerateMonthlyWeatherForecast;
use App\Jobs\RefreshBarangayRiskMap;
use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\ConversationMessage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The fixes from the 2026-10-06 system review that had no test of their own. */
class CriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_account_is_signed_out_on_its_next_request(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($farmer)->get('/profile')->assertOk();

        $farmer->update(['status' => User::STATUS_INACTIVE]);

        $this->get('/profile')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_a_request_no_worker_picked_up_stops_waiting_and_offers_try_again(): void
    {
        Queue::fake();
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $run = AnalyticsRun::query()->create(['type' => 'rice_yield_prediction', 'status' => AnalyticsRun::STATUS_PENDING, 'requested_by' => $farmer->id, 'input' => ['target_date' => now()->addMonth()->toDateString()]]);

        // Still fresh: the page keeps waiting.
        $this->actingAs($farmer)->getJson(route('analytics-runs.show', $run))->assertOk()->assertJsonPath('status', 'pending');

        $this->travel(5)->minutes();

        $this->actingAs($farmer)->getJson(route('analytics-runs.show', $run))->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'This Is Taking Longer Than Usual');
        $this->assertTrue($run->fresh()->timedOut());

        // A worker that comes back later does not spend time on a request the user was already told to retry.
        (new RunPythonAnalytics($run->id))->handle(
            app(\App\Services\Analytics\AnalyticsPayloadFactory::class),
            app(\App\Services\Analytics\PythonAnalyticsClient::class),
            app(\App\Services\Analytics\AnalyticsResultPersister::class),
        );
        $this->assertSame(AnalyticsRun::STATUS_FAILED, $run->fresh()->status);
    }

    public function test_queue_cleanup_keeps_one_refresh_job_and_drops_jobs_of_closed_requests(): void
    {
        config(['queue.default' => 'database']);
        $run = AnalyticsRun::query()->create(['type' => 'weather_prediction', 'status' => AnalyticsRun::STATUS_PENDING, 'input' => []]);
        RunPythonAnalytics::dispatch($run->id)->onConnection('database')->onQueue('analytics');
        foreach (range(1, 3) as $i) {
            RefreshBarangayRiskMap::dispatch()->onConnection('database')->onQueue('analytics');
            GenerateMonthlyWeatherForecast::dispatch('2026-11-01')->onConnection('database')->onQueue('analytics');
        }
        GenerateMonthlyWeatherForecast::dispatch('2026-12-01')->onConnection('database')->onQueue('analytics');
        $this->assertSame(8, DB::table('jobs')->count());

        $this->travel(10)->minutes();
        config(['backup.directory' => storage_path('framework/testing/backups')]);
        $this->artisan('iclimate:clean-stale-queue')->assertSuccessful();

        // One risk refresh, one forecast per month (November, December); the timed-out request's job is gone.
        $this->assertSame(3, DB::table('jobs')->count());
        $this->assertTrue($run->fresh()->timedOut());
    }

    public function test_message_attachments_are_private_to_the_two_people_in_the_conversation(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $outsider = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->post(route('messages.store'), [
            'recipient_id' => $mao->id,
            'body' => 'Photo of my field',
            'attachment' => UploadedFile::fake()->image('field.jpg'),
        ])->assertRedirect();

        $message = ConversationMessage::query()->firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        Storage::disk('public')->assertMissing($message->attachment_path);

        $url = route('messages.attachment', [$message->conversation_id, $message]);
        $this->actingAs($farmer)->get($url)->assertOk();
        $this->actingAs($mao)->get($url)->assertOk();
        $this->actingAs($outsider)->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_the_home_page_contact_form_reaches_mao_staff(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_INACTIVE]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->get('/')->assertOk()->assertSee(route('contact.send'), false);

        $this->postJson(route('contact.send'), ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'role' => 'Rice Farmer', 'message' => 'When is the next seed distribution?'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(1, Notification::query()->count());
        $notification = Notification::query()->firstOrFail();
        $this->assertSame($mao->id, $notification->user_id);
        $this->assertStringContainsString('juan@example.com', $notification->message);
        $this->assertStringContainsString('seed distribution', $notification->message);
        $this->assertSame(0, Notification::query()->where('user_id', $farmer->id)->count());

        // Missing message, and an automated form filler (hidden field completed): nothing is delivered.
        $this->postJson(route('contact.send'), ['name' => 'Juan', 'email' => 'juan@example.com'])->assertStatus(422);
        $this->postJson(route('contact.send'), ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy now, cheap', 'website' => 'http://spam.example'])->assertStatus(422);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_forgot_password_does_not_promise_an_email_that_cannot_be_sent(): void
    {
        $user = User::factory()->create();

        config(['mail.default' => 'log']);
        $this->get('/forgot-password')->assertOk()->assertSee('data-reset-unavailable', false)->assertDontSee('we will email you');
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('password_reset_tokens', 0);

        config(['mail.default' => 'array']);
        $this->get('/forgot-password')->assertOk()->assertSee('we will email you')->assertDontSee('data-reset-unavailable', false);
    }

    public function test_one_season_calendar_is_used_everywhere(): void
    {
        // App\Support\RiceSeason (decided 2026-10-06): wet May to October, dry November to April.
        foreach ([5, 6, 7, 8, 9, 10] as $month) {
            $this->assertSame(ClimateRecord::SEASON_WET, ClimateRecord::seasonForMonth($month));
        }
        foreach ([11, 12, 1, 2, 3, 4] as $month) {
            $this->assertSame(ClimateRecord::SEASON_DRY, ClimateRecord::seasonForMonth($month));
        }

        // The farmer wording states the same calendar in both languages.
        $this->assertStringContainsString('May–October', (string) trans('farmer.climate.season.wet', [], 'en'));
        $this->assertStringContainsString('Mayo–Oktubre', (string) trans('farmer.climate.season.wet', [], 'fil'));
    }
}
