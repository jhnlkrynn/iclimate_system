<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Analytics\AnalyticsPayloadFactory;
use App\Services\Prediction\PredictionOrchestrator;
use App\Support\ClimateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Data-accuracy and account-safety rules added in the 2026-10-06 deployment-readiness pass. */
class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role] + $attributes);
    }

    private function accountFields(User $user, array $changes = []): array
    {
        return $changes + ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'status' => $user->status, 'barangay' => ''];
    }

    // ------------------------------------------------------------ accounts

    public function test_it_cannot_delete_deactivate_or_demote_their_own_account(): void
    {
        $it = $this->user(User::ROLE_IT_EXPERT);
        $this->user(User::ROLE_IT_EXPERT);

        $this->actingAs($it)->delete(route('users.destroy', $it))->assertRedirect(route('users.index'))->assertSessionHas('error');
        $this->actingAs($it)->put(route('users.update', $it), $this->accountFields($it, ['status' => User::STATUS_INACTIVE]))->assertSessionHasErrors('status');
        $this->actingAs($it)->put(route('users.update', $it), $this->accountFields($it, ['role' => User::ROLE_MAO]))->assertSessionHasErrors('status');

        $it->refresh();
        $this->assertSame([User::ROLE_IT_EXPERT, User::STATUS_ACTIVE], [$it->role, $it->status]);

        // Ordinary edits of one's own account still work.
        $this->actingAs($it)->put(route('users.update', $it), $this->accountFields($it, ['name' => 'Renamed Admin']))->assertSessionHasNoErrors();
        $this->assertSame('Renamed Admin', $it->fresh()->name);
    }

    public function test_the_last_active_it_expert_is_always_kept_but_another_one_can_be_removed(): void
    {
        $it = $this->user(User::ROLE_IT_EXPERT);
        $second = $this->user(User::ROLE_IT_EXPERT);

        // IT deletes with a reason; the account is closed, not erased (App\Services\Accounts\AccountDeletion).
        $this->actingAs($it)->delete(route('users.destroy', $second), ['reason' => 'moved_out', 'confirm_name' => $second->name])->assertSessionHasNoErrors();
        $this->assertSame(User::STATUS_DELETED, User::withDeleted()->findOrFail($second->id)->status);
        $this->assertNull(User::query()->find($second->id));

        // Now the only administrator: deleting the account from the profile page is refused too.
        $this->actingAs($it)->delete('/profile', ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertDatabaseHas('users', ['id' => $it->id]);

        // A farmer can still delete their own account.
        $farmer = $this->user(User::ROLE_FARMER);
        $this->actingAs($farmer)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertDatabaseMissing('users', ['id' => $farmer->id]);
    }

    public function test_one_password_rule_applies_wherever_a_password_is_chosen(): void
    {
        $it = $this->user(User::ROLE_IT_EXPERT);
        $new = ['name' => 'New Staff', 'email' => 'new.staff@example.com', 'role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE];

        $this->actingAs($it)->post(route('users.store'), $new + ['password' => 'password1'])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'new.staff@example.com']);
        $this->actingAs($it)->post(route('users.store'), $new + ['password' => 'Strong#Pass2026'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'new.staff@example.com']);

        $this->actingAs($it)->put('/password', ['current_password' => 'password', 'password' => 'weakpassword', 'password_confirmation' => 'weakpassword'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->actingAs($it)->put('/password', ['current_password' => 'password', 'password' => 'Strong#Pass2026', 'password_confirmation' => 'Strong#Pass2026'])
            ->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------- climate data

    private function climate(string $date, float $rainfall, float $temperature = 28.0): void
    {
        ClimateRecord::query()->create(['record_date' => $date, 'rainfall' => $rainfall, 'temperature' => $temperature, 'humidity' => 80, 'wind_speed' => 5, 'season' => ClimateRecord::seasonForMonth((int) substr($date, 5, 2)), 'source' => 'MAO station log']);
    }

    public function test_entered_records_are_read_month_by_month_and_need_six_different_months(): void
    {
        // Six entries, but all in one month: that is one month of history, so the station record is used.
        foreach ([1, 5, 10, 15, 20, 25] as $day) {
            $this->climate(sprintf('2026-03-%02d', $day), 10.0 * $day);
        }
        $this->assertStringContainsString('Ambulong', (string) ClimateHistory::months()->first()->source);
        $this->assertSame(1, ClimateHistory::enteredMonths()->count());

        // Five more months entered: now the entered records are the history, one row per month, newest first.
        foreach (['2025-10-12' => 200.0, '2025-11-12' => 150.0, '2025-12-12' => 80.0, '2026-01-12' => 40.0, '2026-02-12' => 30.0] as $date => $rain) {
            $this->climate($date, $rain);
        }
        $months = ClimateHistory::months();

        $this->assertSame(6, $months->count());
        $this->assertSame('2026-03-01', $months->first()->record_date);
        $this->assertSame('MAO station log', $months->first()->source);
        // March: the average of its six entries (10..250 mm), not six separate "months".
        $this->assertSame(round((10 + 50 + 100 + 150 + 200 + 250) / 6, 2), $months->first()->rainfall);
        $this->assertSame(['2025-10-01', '2026-03-01'], [ClimateHistory::forModels()[0]['record_date'], ClimateHistory::forModels()[5]['record_date']]);
    }

    public function test_impossible_readings_never_reach_the_models(): void
    {
        foreach (['2025-10-12', '2025-11-12', '2025-12-12', '2026-01-12', '2026-02-12', '2026-03-12'] as $date) {
            $this->climate($date, 100.0);
        }
        $this->climate('2026-04-12', 5000.0, 100.0);                                   // impossible reading
        $this->climate(now()->addYear()->toDateString(), 100.0);                       // dated in the future

        $this->assertSame(6, ClimateHistory::months()->count());
        $this->assertSame('2026-03-01', ClimateHistory::months()->first()->record_date);
        $this->assertSame(100.0, (float) collect(ClimateHistory::forModels())->max('rainfall'));
    }

    public function test_an_on_demand_prediction_is_sent_the_same_monthly_forecast_the_heat_map_shows(): void
    {
        $prepared = ['ready' => true, 'predictions' => ['rainfall' => 152.3, 'temperature' => 27.6, 'humidity' => 81.4, 'wind_speed' => 2.1, 'season' => 'Dry'], 'confidence' => ['label' => 'Moderate']];
        $this->mock(PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('monthlyWeatherForecast')->andReturn($prepared));

        foreach (['weather_prediction', 'rice_yield_prediction'] as $type) {
            $payload = app(AnalyticsPayloadFactory::class)->make(new AnalyticsRun(['type' => $type, 'input' => ['target_date' => '2026-11-06', 'area' => 2.5, 'farm_type' => 'Irrigated']]));

            $this->assertSame($prepared, $payload['prepared_forecast'], $type);
            $this->assertGreaterThanOrEqual(ClimateHistory::WINDOW, count($payload['climate_records']), $type);
        }

        // A month without a usable forecast sends none; the Python service then makes its own, as before.
        $this->mock(PredictionOrchestrator::class, fn ($mock) => $mock->shouldReceive('monthlyWeatherForecast')->andReturn(['ready' => false]));
        $payload = app(AnalyticsPayloadFactory::class)->make(new AnalyticsRun(['type' => 'weather_prediction', 'input' => ['target_date' => '2026-11-06']]));
        $this->assertNull($payload['prepared_forecast']);
    }

    // -------------------------------------------------------------- reports

    public function test_report_rows_and_timestamps_use_philippine_time(): void
    {
        $farmer = $this->user(User::ROLE_FARMER);
        $profile = FarmerProfile::query()->create(['user_id' => $farmer->id, 'full_name' => 'Juan Dela Cruz', 'barangay' => 'Prenza', 'farm_area' => 1.5, 'farm_type' => FarmerProfile::FARM_TYPE_RAINFED]);
        // 17:30 UTC on 5 October is 01:30 on 6 October in the Philippines.
        FarmerProfile::query()->whereKey($profile->id)->update(['created_at' => '2026-10-05 17:30:00']);

        $payload = app(AnalyticsPayloadFactory::class)->make(new AnalyticsRun(['type' => 'report_analytics', 'input' => ['report_type' => 'farmer_registration', 'filters' => []]]));

        $this->assertSame('2026-10-06 01:30', $payload['source_rows'][0]['created_at']);
        $this->assertIsObject($payload['filters']);

        $mao = $this->user(User::ROLE_MAO);
        $this->travelTo('2026-10-05 17:30:00');
        $stats = $this->actingAs($mao)->get(route('reports.index'))->assertOk()->viewData('sourceStats');
        $this->assertSame('2026-10-06 01:30', $stats['last_refreshed']->format('Y-m-d H:i'));
    }

    public function test_each_rate_limit_counts_on_its_own(): void
    {
        $this->user(User::ROLE_MAO);

        // Laravel keys an unnamed limit by visitor only, so every throttled route shared one counter: five map
        // requests used up the contact form's three-per-ten-minutes allowance before it was ever used.
        foreach (range(1, 5) as $i) {
            $this->getJson('/api/boundaries/lian-barangays');
        }

        $this->postJson(route('contact.send'), ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'message' => 'Is the office open on Saturday?'])
            ->assertOk()->assertJsonPath('ok', true);

        // Its own limit still applies.
        foreach (range(1, 2) as $i) {
            $this->postJson(route('contact.send'), ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'message' => 'Is the office open on Saturday?'])->assertOk();
        }
        $this->postJson(route('contact.send'), ['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'message' => 'Is the office open on Saturday?'])->assertStatus(429);
    }

    public function test_small_display_rules(): void
    {
        // The IT role is stored as "IT Expert" and shown as "IT Personnel" everywhere.
        $this->assertSame('IT Personnel', User::labelForRole(User::ROLE_IT_EXPERT));
        $this->assertSame('IT Personnel', $this->user(User::ROLE_IT_EXPERT)->roleLabel());
        $this->assertSame(User::ROLE_MAO, User::labelForRole(User::ROLE_MAO));

        // A small file is not shown as "0.00 MB".
        $this->assertSame('3.1 KB', \App\Services\SystemHealthService::fileSize(3171));
        $this->assertSame('4.63 MB', \App\Services\SystemHealthService::fileSize(4855571));
        $this->assertSame('512 bytes', \App\Services\SystemHealthService::fileSize(512));

        // Staff lists: the role reads "IT Personnel". (The "Showing 1-10 of N" header and the wording of an empty value,
        // "Not set" / "Not available", are main's and are covered by StaffDatasetRecordsTest.)
        $it = $this->user(User::ROLE_IT_EXPERT, ['barangay' => null]);
        $html = $this->actingAs($it)->get(route('users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('IT Personnel', $html);
        $this->assertStringContainsString('Not set', $html);
    }

    public function test_test_runs_never_write_into_the_application_log(): void
    {
        // phpunit.xml sets LOG_CHANNEL=null: failures triggered on purpose by tests used to fill storage/logs/laravel.log
        // (96% of a 22 MB file), which the IT dashboard and the assistant read as real errors.
        // (.env values spelled "null" are read as PHP null; Laravel then uses the null log driver during tests.)
        $this->assertContains(config('logging.default'), [null, 'null']);

        $marker = 'written-by-a-test-'.uniqid();
        \Illuminate\Support\Facades\Log::error($marker);

        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $file) {
            $this->assertStringNotContainsString($marker, (string) file_get_contents($file), basename($file));
        }
        $this->addToAssertionCount(1);
    }

    public function test_scheduled_commands_do_not_share_an_output_file_and_run_on_philippine_time(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $outputs = $events->pluck('output')->filter(fn ($output) => is_string($output) && str_contains($output, 'automation-'));

        // Two commands appending to one file fail on Windows whenever they overlap.
        $this->assertGreaterThanOrEqual(14, $outputs->count());
        $this->assertSame($outputs->count(), $outputs->unique()->count());

        // "03:15" for the backup means 3:15 in the morning in Lian, not 11:15 (03:15 UTC).
        $backup = $events->first(fn ($event) => str_contains((string) $event->command, 'iclimate:backup-database'));
        $this->assertSame('Asia/Manila', (string) $backup->timezone);
        $this->assertSame('15 3 * * *', $backup->expression);
    }

    public function test_pages_send_the_security_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $policy = (string) $response->headers->get('Content-Security-Policy');
        foreach (["frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'", "object-src 'none'"] as $rule) {
            $this->assertStringContainsString($rule, $policy);
        }
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_staff_forms_require_the_farm_size_and_the_harvest_month(): void
    {
        $mao = $this->user(User::ROLE_MAO);
        $farmer = $this->user(User::ROLE_FARMER);

        $profile = ['user_id' => $farmer->id, 'full_name' => 'Juan Dela Cruz', 'barangay' => 'Prenza', 'farm_type' => 'Rainfed'];
        $this->actingAs($mao)->post(route('farmer-profiles.store'), $profile)->assertSessionHasErrors('farm_area');
        $this->actingAs($mao)->post(route('farmer-profiles.store'), $profile + ['farm_area' => 1.25])->assertSessionHasNoErrors();

        $harvest = ['barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Rainfed', 'yield_per_hectare' => 4.1, 'area_hectares' => 10, 'total_production' => 41, 'year' => 2025];
        $this->actingAs($mao)->post(route('rice-productions.store'), $harvest)->assertSessionHasErrors('production_month');
        $this->actingAs($mao)->post(route('rice-productions.store'), $harvest + ['production_month' => '2025-09'])->assertSessionHasNoErrors();
    }
}
