<?php

namespace Tests\Feature;

use App\Jobs\RunPythonAnalytics;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\FeedPost;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Analytics\AnalyticsResultPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsHeatmapNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mao_can_generate_and_export_climate_report(): void
    {
        Queue::fake();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $climateRecord = ClimateRecord::factory()->create([
            'record_date' => '2026-09-10',
            'rainfall' => 14.25,
            'temperature' => 29.50,
            'humidity' => 81.00,
            'wind_speed' => 7.75,
            'season' => 'Wet',
            'source' => 'PAGASA',
        ]);

        $response = $this->actingAs($mao)->post(route('reports.generate'), [
            'report_type' => 'climate_records',
            'season' => 'Wet',
        ]);

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $this->assertSame('report_analytics', $run->type);
        $this->assertSame(['season' => 'Wet'], data_get($run->input, 'filters'));
        Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);

        app(AnalyticsResultPersister::class)->persist($run, [
            'report_type' => 'climate_records',
            'filters' => ['season' => 'Wet'],
            'columns' => [
                'record_date' => 'Date', 'rainfall' => 'Rainfall', 'temperature' => 'Temperature',
                'humidity' => 'Humidity', 'wind_speed' => 'Wind Speed', 'season' => 'Season', 'source' => 'Source',
            ],
            'records' => [[
                'record_date' => '2026-09-10', 'rainfall' => '14.25', 'temperature' => '29.50',
                'humidity' => '81.00', 'wind_speed' => '7.75', 'season' => 'Wet', 'source' => 'PAGASA',
            ]],
            'row_count' => 1,
            'processor' => 'python',
        ]);

        $generatedReport = Report::query()->latest('id')->firstOrFail();
        $this->assertSame('Climate Records Report', $generatedReport->report_type);
        $this->assertStringStartsWith('Climate Records Report - ', $generatedReport->title);
        $this->assertSame($mao->id, $generatedReport->generated_by);
        $this->assertSame(1, data_get($generatedReport->payload, 'row_count'));
        $this->assertSame('climate_records', data_get($generatedReport->payload, 'report_type'));
        $this->assertSame(['season' => 'Wet'], data_get($generatedReport->payload, 'filters'));
        $this->assertSame([
            'record_date' => 'Date',
            'rainfall' => 'Rainfall',
            'temperature' => 'Temperature',
            'humidity' => 'Humidity',
            'wind_speed' => 'Wind Speed',
            'season' => 'Season',
            'source' => 'Source',
        ], data_get($generatedReport->payload, 'columns'));
        $this->assertSame([
            'record_date' => '2026-09-10',
            'rainfall' => '14.25',
            'temperature' => '29.50',
            'humidity' => '81.00',
            'wind_speed' => '7.75',
            'season' => 'Wet',
            'source' => 'PAGASA',
        ], data_get($generatedReport->payload, 'records.0'));
        $this->assertNotEmpty(data_get($generatedReport->payload, 'generated_at'));
        $response->assertRedirect(route('reports.index', ['run' => $run->uuid]));

        $climateRecord->delete();

        $this->actingAs($mao)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Climate Records Report')
            ->assertSee('2026-09-10')
            ->assertSee('14.25')
            ->assertSee('29.50')
            ->assertSee('81.00')
            ->assertSee('7.75')
            ->assertSee('PAGASA');

        $export = $this->actingAs($mao)->get(route('reports.export', [
            'report' => $generatedReport->id,
        ]));
        $export->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('2026-09-10', $export->streamedContent());
    }

    public function test_reports_page_auto_loads_latest_saved_rows_without_showing_history_panel(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        foreach (range(1, 12) as $number) {
            Report::factory()->create([
                'report_type' => 'Climate Records Report',
                'title' => sprintf('Real report %02d', $number),
                'generated_by' => $mao->id,
                'payload' => [
                    'report_type' => 'climate_records',
                    'filters' => [],
                    'columns' => ['record_date' => 'Date'],
                    'records' => [['record_date' => "2026-09-{$number}"]],
                    'row_count' => 1,
                    'generated_at' => now()->toDateTimeString(),
                ],
                'created_at' => now()->subMinutes(13 - $number),
                'updated_at' => now()->subMinutes(13 - $number),
            ]);
        }

        Report::factory()->create([
            'report_type' => 'Climate',
            'title' => 'Sample report that must stay hidden',
            'generated_by' => $mao->id,
            'is_generated' => false,
            'created_at' => now()->addMinute(),
        ]);

        $firstPage = $this->actingAs($mao)->get(route('reports.index'));

        $firstPage->assertOk()
            ->assertSee('2026-09-12')
            ->assertDontSee('Report History')
            ->assertDontSee('Latest report')
            ->assertDontSee('Older reports')
            ->assertDontSee('Sample report that must stay hidden');

        $this->assertSame('Real report 12', $firstPage->viewData('selectedReport')->title);
    }

    public function test_every_report_type_counts_real_filtered_database_rows_and_saves_applied_filters(): void
    {
        Queue::fake();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        RiceProduction::factory()->create(['year' => 2026, 'barangay' => 'Matabungkay', 'season' => 'Dry']);
        RiceProduction::factory()->create(['year' => 2025, 'barangay' => 'Lumaniag', 'season' => 'Wet']);

        FarmerProfile::factory()->create(['barangay' => 'Matabungkay', 'created_at' => '2026-09-05 08:00:00']);
        FarmerProfile::factory()->create(['barangay' => 'Lumaniag', 'created_at' => '2026-08-05 08:00:00']);

        PlantingAdvisory::factory()->create(['target_barangay' => 'Matabungkay', 'created_at' => '2026-09-06 08:00:00']);
        PlantingAdvisory::factory()->create(['target_barangay' => 'Lumaniag', 'created_at' => '2026-08-06 08:00:00']);

        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'September field update',
            'body' => 'A real database post.',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ])->forceFill([
            'created_at' => '2026-09-07 08:00:00',
            'updated_at' => '2026-09-07 08:00:00',
        ])->save();
        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'August field update',
            'body' => 'An older real database post.',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ])->forceFill([
            'created_at' => '2026-08-07 08:00:00',
            'updated_at' => '2026-08-07 08:00:00',
        ])->save();

        $cases = [
            ['rice_production', ['year' => 2026, 'barangay' => 'Matabungkay', 'season' => 'Dry']],
            ['farmer_registration', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'barangay' => 'Matabungkay']],
            ['advisory', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30', 'barangay' => 'Matabungkay']],
            ['community_feed', ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']],
        ];

        foreach ($cases as [$reportType, $filters]) {
            $this->actingAs($mao)
                ->post(route('reports.generate'), ['report_type' => $reportType] + $filters)
                ->assertRedirect();

            $run = AnalyticsRun::query()->latest('id')->firstOrFail();
            $this->assertSame('report_analytics', $run->type);
            $this->assertSame($reportType, data_get($run->input, 'report_type'));
            $this->assertSame($filters, data_get($run->input, 'filters'));
            Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);
        }
    }

    public function test_empty_filtered_report_is_saved_with_zero_rows_and_shows_no_records_found(): void
    {
        Queue::fake();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        ClimateRecord::factory()->create(['season' => 'Wet']);

        $response = $this->actingAs($mao)->post(route('reports.generate'), [
            'report_type' => 'climate_records',
            'season' => 'Dry',
        ]);

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('reports.index', ['run' => $run->uuid]));
        $this->assertSame(['season' => 'Dry'], data_get($run->input, 'filters'));
        Queue::assertPushed(RunPythonAnalytics::class, fn ($job) => $job->analyticsRunId === $run->id);
    }

    public function test_generic_report_crud_cannot_create_or_edit_history_rows(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $report = Report::factory()->create(['generated_by' => $mao->id]);

        $this->actingAs($mao)->post('/reports')->assertMethodNotAllowed();
        $this->actingAs($mao)->get('/reports/create')->assertNotFound();
        $this->actingAs($mao)->get("/reports/{$report->id}/edit")->assertNotFound();
        $this->actingAs($mao)->put("/reports/{$report->id}")->assertMethodNotAllowed();
    }

    public function test_rice_production_rejects_invalid_barangay_season_and_irrigation(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('rice-productions.store'), [
            'barangay' => 'Not A Lian Barangay',
            'season' => 'Summer',
            'irrigation_type' => 'Unknown',
            'yield_per_hectare' => 4.2,
            'area_hectares' => 10,
            'total_production' => 42,
            'year' => 2026,
        ])->assertSessionHasErrors(['barangay', 'season', 'irrigation_type']);
    }

    public function test_report_shows_purpose_summary_and_weather_basis(): void
    {
        Queue::fake();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        ClimateRecord::factory()->create([
            'record_date' => '2026-09-05',
            'rainfall' => 100.0,
            'temperature' => 28.0,
        ]);
        ClimateRecord::factory()->create([
            'record_date' => '2026-09-15',
            'rainfall' => 200.0,
            'temperature' => 30.0,
        ]);

        $run = AnalyticsRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => 'report_analytics',
            'status' => AnalyticsRun::STATUS_PROCESSING,
            'requested_by' => $mao->id,
            'input' => ['report_type' => 'rice_production', 'filters' => ['date_from' => '2026-09-01', 'date_to' => '2026-09-30']],
        ]);

        $storedResult = app(AnalyticsResultPersister::class)->persist($run, [
            'report_type' => 'rice_production',
            'filters' => ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'],
            'columns' => ['barangay' => 'Barangay', 'yield_per_hectare' => 'Yield/Ha', 'total_production' => 'Total'],
            'records' => [
                ['barangay' => 'Matabungkay', 'yield_per_hectare' => '4.00', 'total_production' => '10.00'],
                ['barangay' => 'Lumaniag', 'yield_per_hectare' => '2.00', 'total_production' => '5.00'],
            ],
            'row_count' => 2,
            'processor' => 'python',
        ]);
        $run->update(['status' => AnalyticsRun::STATUS_COMPLETED, 'result' => $storedResult]);

        $this->actingAs($mao)
            ->get(route('reports.index', ['run' => $run->uuid]))
            ->assertOk()
            ->assertSee('About this report')
            ->assertSee('Comparing barangay or season performance', false)
            ->assertSee('Result Summary')
            ->assertSee('15.00 tons')
            ->assertSee('3.00 t/ha')
            ->assertSee('Weather During This Period')
            // Entered records are summarised on their own (September 2026 has no historical station month).
            ->assertSee('Entered climate records:', false)
            ->assertSee('150.0 mm rainfall per record', false)
            ->assertSee('29.0°C')
            ->assertSee('no station months for 2026-09-01 to 2026-09-30');
    }

    public function test_mao_can_queue_live_advisory_generation_and_return_to_management(): void
    {
        Queue::fake();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)
            ->post(route('management.advisories.regenerate'));

        $run = AnalyticsRun::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('management.advisories.index', ['run' => $run->uuid]));
        $this->assertSame('advisory_ai', $run->type);
        Queue::assertPushed(RunPythonAnalytics::class);
    }

    public function test_mao_can_send_notifications_to_farmers_and_farmer_can_mark_read(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($mao)->post(route('notifications.store'), [
            'recipient_scope' => 'farmers',
            'title' => 'Weather Warning',
            'message' => 'Heavy rainfall expected.',
            'type' => 'Warning',
        ])->assertRedirect(route('notifications.index'));

        $notification = Notification::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertFalse($notification->is_read);

        $this->actingAs($farmer)
            ->patch(route('notifications.mark-read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->is_read);
        $this->assertNull($notification->category);                                            // no topic chosen: a general warning
    }

    public function test_a_notification_marked_as_weather_is_stored_with_that_category(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($mao)->post(route('notifications.store'), [
            'recipient_scope' => 'farmers', 'title' => 'Storm signal', 'message' => 'Stay alert.', 'type' => 'Warning', 'category' => 'weather',
        ])->assertRedirect(route('notifications.index'));

        $this->assertSame(Notification::CATEGORY_WEATHER, Notification::query()->where('user_id', $farmer->id)->firstOrFail()->category);

        $this->actingAs($mao)->post(route('notifications.store'), [
            'recipient_scope' => 'farmers', 'title' => 'Pickup', 'message' => 'Bring a bag.', 'type' => 'Warning', 'category' => 'not-a-topic',
        ])->assertSessionHasErrors('category');
    }

    public function test_mao_can_publish_announcement_category_to_community_feed(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('community-feed.store'), [
            'title' => 'Seed Distribution Schedule',
            'body' => 'Seeds will be distributed this Friday.',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ])->assertRedirect(route('community-feed.index'));

        $this->assertDatabaseHas('feed_posts', [
            'user_id' => $mao->id,
            'title' => 'Seed Distribution Schedule',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ]);
    }

    public function test_farmer_sees_community_feed_announcements(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'Visible Update',
            'body' => 'Published as a community feed announcement.',
            'category' => 'Announcement',
            'visibility' => 'All Farmers',
        ]);
        FeedPost::query()->create([
            'user_id' => $mao->id,
            'title' => 'General Update',
            'body' => 'A normal community feed update.',
            'category' => 'Update',
            'visibility' => 'All Farmers',
        ]);

        // Farmers read them as Announcements on the Advisories page (2026-10); the feed itself is for MAO and IT.
        $this->actingAs($farmer)
            ->get(route('planting-advisories.index'))
            ->assertOk()
            ->assertSee('Visible Update')
            ->assertSee('General Update');
    }

    public function test_notifications_index_is_current_users_inbox(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        Notification::factory()->create(['user_id' => $mao->id, 'title' => 'MAO Inbox']);
        Notification::factory()->create(['user_id' => $farmer->id, 'title' => 'Farmer Inbox']);

        $this->actingAs($mao)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('MAO Inbox')
            ->assertDontSee('Farmer Inbox');
    }
}
