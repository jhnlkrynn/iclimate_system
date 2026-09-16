<?php

namespace Tests\Feature;

use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\Report;
use App\Models\User;
use App\Services\Analytics\AnalyticsPayloadFactory;
use App\Services\Analytics\AnalyticsResultPersister;
use App\Services\Analytics\PythonAnalyticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PythonAnalyticsQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_request_is_queued_and_python_result_is_saved(): void
    {
        Queue::fake();
        Config::set('services.farming_ai.url', 'http://python.test');
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        ClimateRecord::factory()->create([
            'record_date' => '2026-09-10',
            'rainfall' => 14.25,
            'temperature' => 29.5,
            'humidity' => 81,
            'wind_speed' => 7.75,
            'season' => 'Wet',
            'source' => 'PAGASA',
        ]);

        $this->actingAs($user)->post(route('reports.generate'), [
            'report_type' => 'climate_records',
            'season' => 'Wet',
        ])->assertRedirect();

        $run = AnalyticsRun::query()->firstOrFail();
        Queue::assertPushed(RunPythonAnalytics::class, fn (RunPythonAnalytics $job): bool => $job->analyticsRunId === $run->id);

        Http::fake([
            'python.test/analytics/report_analytics' => Http::response([
                'report_type' => 'climate_records',
                'filters' => ['season' => 'Wet'],
                'columns' => ['record_date' => 'Date', 'rainfall' => 'Rainfall'],
                'records' => [['record_date' => '2026-09-10', 'rainfall' => '14.25']],
                'row_count' => 1,
                'processor' => 'python',
            ]),
        ]);

        (new RunPythonAnalytics($run->id))->handle(
            app(AnalyticsPayloadFactory::class),
            app(PythonAnalyticsClient::class),
            app(AnalyticsResultPersister::class),
        );

        $run->refresh();
        $this->assertSame(AnalyticsRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('python', data_get($run->result, 'processor'));
        $this->assertSame(1, data_get($run->result, 'row_count'));
        $this->assertDatabaseCount('reports', 1);
        $this->assertSame('2026-09-10', data_get(Report::query()->firstOrFail()->payload, 'records.0.record_date'));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://python.test/analytics/report_analytics'
                && data_get($request->data(), 'source_rows.0.source') === 'PAGASA';
        });
    }

    public function test_run_status_hides_results_until_processing_completes(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $run = AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_PENDING,
            'requested_by' => $user->id,
            'input' => ['target_date' => '2026-11-01'],
        ]);

        $this->actingAs($user)
            ->getJson(route('analytics-runs.show', $run))
            ->assertOk()
            ->assertJsonPath('status', AnalyticsRun::STATUS_PENDING)
            ->assertJsonPath('result', null);
    }

    public function test_completed_python_weather_prediction_and_api_snapshot_are_saved(): void
    {
        Config::set('services.farming_ai.url', 'http://python.test');
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        ClimateRecord::factory()->count(6)->sequence(
            fn ($sequence) => [
                'record_date' => now()->subMonths(6 - $sequence->index)->startOfMonth()->addDays(10),
                'source' => 'PAGASA',
            ],
        )->create();
        $liveWeather = [
            'source' => 'Open-Meteo',
            'location' => 'Lian, Batangas',
            'current_temperature_c' => 29.4,
            'current_condition' => 'Light Rain',
        ];
        $run = AnalyticsRun::query()->create([
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_PENDING,
            'requested_by' => $user->id,
            'input' => [
                'target_date' => now()->addMonths(2)->toDateString(),
                'location' => 'Lian, Batangas',
                'live_weather' => $liveWeather,
            ],
        ]);

        Http::fake([
            'python.test/analytics/weather_prediction' => Http::response([
                'ready' => true,
                'processor' => 'python',
                'predictions' => ['rainfall' => 145.2, 'temperature' => 28.7, 'humidity' => 81, 'wind_speed' => 9, 'season' => 'Wet'],
                'confidence' => ['value' => 82, 'label' => 'High confidence'],
                'risk_guidance' => 'Manageable rainfall outlook',
                'farming_recommendation' => 'Continue normal field monitoring.',
                'live_weather' => $liveWeather,
            ]),
        ]);

        (new RunPythonAnalytics($run->id))->handle(
            app(AnalyticsPayloadFactory::class),
            app(PythonAnalyticsClient::class),
            app(AnalyticsResultPersister::class),
        );

        $run->refresh();
        $this->assertSame(AnalyticsRun::STATUS_COMPLETED, $run->status);
        $this->assertSame('python', data_get($run->result, 'processor'));
        $this->assertSame(145.2, data_get($run->result, 'predictions.rainfall'));
        $this->assertSame('Open-Meteo', data_get($run->result, 'live_weather.source'));
        $this->assertNotNull($run->completed_at);
        $this->assertDatabaseHas('analytics_runs', [
            'id' => $run->id,
            'type' => 'weather_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
        ]);
    }
}
