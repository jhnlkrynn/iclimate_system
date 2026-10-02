<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\BackupVerification;
use App\Models\ExternalWeatherData;
use App\Models\FarmerProfile;
use App\Models\MlModel;
use App\Models\MonthlyWeatherPrediction;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItDashboardTest extends TestCase
{
    use RefreshDatabase;

    private ?string $backupDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
        // An empty, isolated backup folder so the developer machine's real backups never leak into tests.
        $this->backupDir = storage_path('framework/testing/it-dashboard-'.uniqid());
        File::ensureDirectoryExists($this->backupDir);
        config(['backup.directory' => $this->backupDir]);
    }

    protected function tearDown(): void
    {
        if ($this->backupDir) {
            File::deleteDirectory($this->backupDir);
        }
        parent::tearDown();
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'name' => 'IT Person']);
    }

    private function page(?User $as = null)
    {
        Cache::flush();

        return $this->actingAs($as ?? $this->it())->get(route('admin.dashboard'))->assertOk();
    }

    private function payload(?User $as = null): array
    {
        return $this->page($as)->viewData('it');
    }

    /** The dashboard body only: from the first KPI to the end of Quick Actions (the sidebar is excluded). */
    private function body(string $html): string
    {
        $start = strpos($html, 'data-it-kpis');

        return substr($html, $start, strpos($html, 'Quick Actions') - $start);
    }

    private function service(array $payload, string $key): ?array
    {
        return collect($payload['services'])->firstWhere('key', $key);
    }

    private function alert(array $payload, string $title): ?array
    {
        return collect($payload['alerts'])->firstWhere('title', $title);
    }

    private function analyticsRun(string $type, string $status, ?string $at = null, ?User $by = null): AnalyticsRun
    {
        $at ??= now()->toDateTimeString();

        return AnalyticsRun::query()->forceCreate([
            'type' => $type, 'status' => $status, 'requested_by' => $by?->id,
            'input' => ['schema' => 'rice_yield_v1'], 'completed_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function weather(int $minutesAgo): void
    {
        ExternalWeatherData::query()->forceCreate([
            'source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.0389, 'longitude' => 120.6503,
            'forecast_date' => now()->toDateString(), 'fetched_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function backupFile(int $hoursOld): void
    {
        $path = $this->backupDir.'/iclimate_2026-09-25_0315.backup';
        file_put_contents($path, "PGDMP\x01\x0e-body");
        touch($path, now()->subHours($hoursOld)->getTimestamp());
    }

    private function schedulerIsRunning(): void
    {
        $this->analyticsRun('advisory_ai', AnalyticsRun::STATUS_COMPLETED, now()->subMinutes(5)->toDateTimeString());
    }

    private function failedJob(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'connection' => 'database', 'queue' => 'x', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
    }

    // ------------------------------------------------------------ access + structure

    public function test_only_it_personnel_can_open_the_dashboard_and_its_data_endpoints(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        // Guest first: actingAs() stays in effect for the rest of the test.
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        foreach (['admin.dashboard', 'admin.dashboard.live', 'admin.dashboard.activity'] as $route) {
            $this->actingAs($mao)->get(route($route))->assertForbidden();
            $this->actingAs($farmer)->get(route($route))->assertForbidden();
        }
    }

    public function test_page_follows_the_revised_structure_in_order(): void
    {
        $html = $this->page()->getContent();
        $order = ['IT Dashboard', 'data-it-kpis', 'data-it-alerts', 'data-it-health', 'data-it-distribution', 'data-it-activity',
            'data-it-farmers', 'data-it-recent', 'Quick Actions'];

        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker);
            $this->assertNotFalse($pos, "Missing section: $marker");
            $this->assertGreaterThan($last, $pos, "Section out of order: $marker");
            $last = $pos;
        }
        foreach (['Manage Users', 'View System Logs', 'System Health', 'Generate Report', 'Refresh', 'Last refreshed', 'Monitor users, system health, data, and system activity.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_calm_empty_dashboard_has_none_of_the_removed_or_negative_content(): void
    {
        $html = $this->page()->getContent();

        foreach (['Prediction Service', 'Prediction Success Rate', 'Prediction Calls', 'API Status', 'Model Status', 'Model Evaluation',
            'Evaluation unavailable', 'Last Successful Prediction', 'No data available', 'Unavailable', 'Unknown', 'Critical', 'None recorded',
            'data-kpi="prediction"', 'data-it-monitor', 'data-it-evaluation', 'data-it-data'] as $removed) {
            $this->assertStringNotContainsString($removed, $html, $removed);
        }
        $this->assertStringContainsString('No system issues requiring attention.', $html);
    }

    // ------------------------------------------------------------ users + KPIs

    public function test_user_kpis_come_from_the_database_and_reconcile(): void
    {
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]);
        User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_INACTIVE]);
        User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
        $it = $this->it();

        $u = $this->payload($it)['users'];

        $this->assertSame(5, $u['total']);
        $this->assertSame(4, $u['active']);
        $this->assertSame(1, $u['inactive']);
        $this->assertSame(['farmer' => 3, 'mao' => 1, 'it' => 1], $u['roles']);
        $this->assertSame($u['total'], array_sum($u['roles']));
        $this->assertSame($u['total'], $u['active'] + $u['inactive']);
        $this->assertSame([], $u['notes']);

        $html = $this->page($it)->getContent();
        $this->assertStringContainsString('3 Farmers • 1 MAO • 1 IT', $html);
        $this->assertStringContainsString('80% Active', $html);
        $this->assertStringContainsString('1 Inactive', $html);
    }

    public function test_active_percentage_is_rounded_down_and_100_only_when_everyone_is_active(): void
    {
        $it = $this->it();
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]);
        $this->assertSame(100.0, $this->payload($it)['users']['active_pct']);
        $this->assertStringContainsString('100% Active', $this->page($it)->getContent());

        User::query()->where('role', User::ROLE_FARMER)->first()->update(['status' => User::STATUS_INACTIVE]);
        $this->assertSame(66.6, $this->payload($it)['users']['active_pct']);      // 2 of 3 = 66.67, never rounded up
        $this->assertStringNotContainsString('100% Active', $this->page($it)->getContent());
    }

    public function test_there_are_four_kpi_cards_when_weather_data_exists_and_three_when_it_does_not(): void
    {
        $it = $this->it();
        $without = $this->payload($it)['kpis'];
        $this->assertSame(['users', 'active', 'health'], array_column($without, 'key'));      // no empty/"No data" card

        $this->weather(18);
        $with = $this->payload($it)['kpis'];
        $this->assertSame(['users', 'active', 'health', 'freshness'], array_column($with, 'key'));
        $this->assertNotContains('prediction', array_column($with, 'key'));

        $html = $this->page($it)->getContent();
        $this->assertStringContainsString('Updated 18 minutes ago', $html);
        $this->assertStringContainsString('Latest climate/weather update', $html);
        $this->assertStringContainsString('--itd-kpi-count: 4', $html);
    }

    // ------------------------------------------------------------ health: verified only

    public function test_a_fresh_dashboard_is_healthy_because_nothing_verified_is_failing(): void
    {
        $data = $this->payload();

        $this->assertSame('ok', $data['overall']['level']);
        $this->assertSame('Healthy', $data['overall']['label']);
        $this->assertSame([], $data['alerts']);
        $this->assertSame(['application', 'database', 'jobs'], array_column($data['services'], 'key'));   // nothing unverifiable is listed
        $this->assertEmpty(array_diff(array_column($data['services'], 'level'), ['ok', 'warn', 'crit']));  // never "unknown"
    }

    public function test_optional_prediction_service_problems_never_affect_the_overview(): void
    {
        MlModel::query()->forceCreate([
            'purpose' => MlModel::PURPOSE_RICE_YIELD, 'name' => 'rice_yield_model_final.pkl', 'model_type' => 'RandomForestRegressor',
            'version' => '1.0.0', 'file_path' => 'storage/models/rice_yield_model_final.pkl',
            'status' => MlModel::STATUS_ACTIVE, 'is_active' => true, 'active_slot' => 'rice_yield',
        ]);
        Cache::put('metrics:python-service:attempts:'.today()->toDateString(), 5, now()->addDay());
        Cache::put('metrics:python-service:failures:'.today()->toDateString(), 5, now()->addDay());

        $data = $this->payload();
        $html = $this->page()->getContent();

        $this->assertSame('ok', $data['overall']['level']);
        $this->assertSame([], $data['alerts']);
        $this->assertStringNotContainsString('Prediction', $this->body($html));
        // ...but the real diagnostics are still reachable on the System Health page.
        $this->actingAs($this->it())->get(route('system-health.index'))->assertOk()->assertSee('Model sync');
    }

    public function test_database_failure_is_the_only_thing_that_makes_the_platform_critical(): void
    {
        $this->mock(SystemHealthService::class, function ($mock): void {
            $mock->shouldReceive('only')->andReturn([
                ['key' => 'database', 'title' => 'Database Connection', 'status' => SystemHealthService::UNAVAILABLE, 'summary' => 'This check could not be completed right now.', 'details' => []],
            ]);
        });

        $data = $this->payload();

        $this->assertSame('crit', $data['overall']['level']);
        $this->assertSame('critical', $this->alert($data, 'Database Connection Problem')['severity']);
        $this->assertStringContainsString('Critical', $this->page()->getContent());
    }

    public function test_failed_jobs_are_a_real_warning(): void
    {
        $this->failedJob();

        $data = $this->payload();

        $this->assertSame('warn', $data['overall']['level']);
        $this->assertSame('warning', $this->alert($data, 'Failed Jobs Need Review')['severity']);
        $this->assertSame('warn', $this->service($data, 'jobs')['level']);
    }

    public function test_stale_weather_is_a_warning_but_missing_weather_is_not(): void
    {
        $it = $this->it();
        $this->assertNull($this->alert($this->payload($it), 'Climate Data Outdated'));
        $this->assertNull($this->service($this->payload($it), 'weather'));

        $this->weather(8 * 60);
        $data = $this->payload($it);

        $this->assertNotNull($this->alert($data, 'Climate Data Outdated'));
        $this->assertSame('warn', $this->service($data, 'weather')['level']);
        $this->assertSame('warn', $data['overall']['level']);
    }

    public function test_current_weather_is_a_healthy_verified_service(): void
    {
        $this->weather(10);

        $data = $this->payload();

        $this->assertSame('ok', $this->service($data, 'weather')['level']);
        $this->assertSame('ok', $data['overall']['level']);
    }

    public function test_backup_alert_needs_a_backup_that_is_provably_overdue(): void
    {
        $it = $this->it();

        // No backup yet: nothing to judge.
        $data = $this->payload($it);
        $this->assertNull($this->alert($data, 'Database Backup Overdue'));
        $this->assertNull($this->service($data, 'backup'));

        // Overdue, but nothing shows the scheduler is running here (e.g. development): not a verified miss.
        $this->backupFile(hoursOld: 120);
        $data = $this->payload($it);
        $this->assertNull($this->alert($data, 'Database Backup Overdue'));
        $this->assertNull($this->service($data, 'backup'));
        $this->assertSame('ok', $data['overall']['level']);

        // Overdue while scheduled automation is demonstrably running: a real missed backup.
        $this->schedulerIsRunning();
        $data = $this->payload($it);
        $this->assertSame('warning', $this->alert($data, 'Database Backup Overdue')['severity']);
        $this->assertSame('warn', $this->service($data, 'backup')['level']);
        $this->assertSame('warn', $data['overall']['level']);
    }

    public function test_a_recent_backup_is_a_healthy_service(): void
    {
        $this->backupFile(hoursOld: 2);

        $data = $this->payload();

        $this->assertSame('ok', $this->service($data, 'backup')['level']);
        $this->assertNull($this->alert($data, 'Database Backup Overdue'));
    }

    public function test_backup_verification_only_alerts_when_a_check_actually_failed(): void
    {
        $it = $this->it();
        $this->assertNull($this->alert($this->payload($it), 'Backup Verification Failed'));           // never verified: not a failure

        BackupVerification::query()->forceCreate([
            'backup_filename' => 'iclimate_2026-09-25_0315.backup', 'verified_at' => now()->subHour(),
            'status' => BackupVerification::FAILED, 'failure_reason' => 'Archive could not be read',
        ]);

        $this->assertNotNull($this->alert($this->payload($it), 'Backup Verification Failed'));
    }

    public function test_dataset_processing_alerts_only_for_a_failed_or_stuck_run(): void
    {
        $it = $this->it();
        $this->assertNull($this->alert($this->payload($it), 'Dataset Processing Failed'));            // no run at all: normal

        $this->analyticsRun('dataset_processing', AnalyticsRun::STATUS_COMPLETED, now()->subDays(3)->toDateTimeString());
        $this->assertSame([], $this->payload($it)['alerts']);                                         // old success: normal

        $this->analyticsRun('dataset_processing', AnalyticsRun::STATUS_FAILED);
        $this->assertNotNull($this->alert($this->payload($it), 'Dataset Processing Failed'));
    }

    public function test_a_stuck_dataset_processing_run_is_reported(): void
    {
        $this->analyticsRun('dataset_processing', AnalyticsRun::STATUS_PENDING, now()->subHours(2)->toDateTimeString());

        $this->assertNotNull($this->alert($this->payload(), 'Dataset Processing Stuck'));
    }

    public function test_alerts_are_listed_critical_first_and_each_has_an_action(): void
    {
        $this->mock(SystemHealthService::class, function ($mock): void {
            $mock->shouldReceive('only')->andReturn([
                ['key' => 'failed-jobs', 'title' => 'Failed jobs', 'status' => SystemHealthService::NEEDS_ATTENTION, 'summary' => '1 failed job needs review.', 'details' => []],
                ['key' => 'database', 'title' => 'Database', 'status' => SystemHealthService::UNAVAILABLE, 'summary' => 'This check could not be completed right now.', 'details' => []],
            ]);
        });

        $alerts = $this->payload()['alerts'];

        $this->assertCount(2, $alerts);
        $this->assertSame('critical', $alerts[0]['severity']);
        foreach ($alerts as $alert) {
            $this->assertNotEmpty($alert['action']);
            $this->assertNotEmpty($alert['href']);
        }
    }

    // ------------------------------------------------------------ farmer database

    public function test_farmer_database_shows_real_values_including_average_size(): void
    {
        $it = $this->it();
        FarmerProfile::factory()->create(['farm_area' => 2.0]);
        FarmerProfile::factory()->create(['farm_area' => 4.0]);
        FarmerProfile::factory()->create(['farm_area' => null]);      // not averaged in

        $f = $this->payload($it)['farmers'];

        $this->assertSame(3, $f['total_farmers']);
        $this->assertEqualsWithDelta(6.0, $f['farm_area'], 0.001);
        $this->assertEqualsWithDelta(3.0, $f['average_area'], 0.001);
        $this->assertSame(2, $f['farms_with_area']);
        $this->assertStringContainsString('Average Farm Size', $this->page($it)->getContent());
    }

    public function test_account_profile_note_appears_only_when_the_numbers_actually_differ(): void
    {
        $farmers = User::factory()->count(2)->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmers[0]->id]);

        $note = $this->payload()['farmers']['note'];
        $this->assertNotNull($note);
        $this->assertStringContainsString('2 farmer accounts vs 1 farmer profiles', $note);

        Cache::flush();
        FarmerProfile::factory()->create(['user_id' => $farmers[1]->id]);
        $this->assertNull($this->payload()['farmers']['note']);       // 2 accounts, 2 profiles: nothing to explain
    }

    // ------------------------------------------------------------ data status: only what exists

    public function test_data_status_is_hidden_entirely_when_no_dataset_has_records(): void
    {
        $data = $this->payload();
        $html = $this->page()->getContent();

        $this->assertSame([], $data['data']);
        $this->assertStringNotContainsString('data-it-data', $html);
        $this->assertMatchesRegularExpression('/data-slot="data"\s+hidden/', $html);
    }

    public function test_data_status_lists_only_datasets_that_have_records(): void
    {
        MonthlyWeatherPrediction::query()->forceCreate([
            'prediction_year' => 2026, 'prediction_month' => 10, 'weather_model' => 'rf-v1', 'generated_at' => now()->subHour(),
            'status' => MonthlyWeatherPrediction::STATUS_READY, 'rainfall' => 200,
        ]);
        $this->analyticsRun('dataset_processing', AnalyticsRun::STATUS_COMPLETED);

        $data = collect($this->payload()['data']);
        $html = $this->page()->getContent();

        $this->assertSame(['forecasts', 'processing'], $data->pluck('key')->all());
        $this->assertSame('1 record', $data->firstWhere('key', 'forecasts')['value']);
        $this->assertStringContainsString('Weather Forecasts', $html);
        $this->assertStringContainsString('Latest successful processing', $html);
        foreach (['Climate Records', 'Rice Production Records', 'Saved Predictions', 'No data available'] as $absent) {
            $this->assertStringNotContainsString($absent, $this->body($html));
        }
    }

    public function test_saved_predictions_count_completed_results_only_and_never_failed_api_calls(): void
    {
        $this->analyticsRun('rice_yield_prediction', AnalyticsRun::STATUS_FAILED);
        $this->analyticsRun('weather_prediction', AnalyticsRun::STATUS_FAILED);
        $this->assertNull(collect($this->payload()['data'])->firstWhere('key', 'predictions'));       // failures are not prediction data

        $this->analyticsRun('rice_yield_prediction', AnalyticsRun::STATUS_COMPLETED);
        $this->analyticsRun('weather_prediction', AnalyticsRun::STATUS_COMPLETED);
        $item = collect($this->payload()['data'])->firstWhere('key', 'predictions');

        $this->assertSame('2 records', $item['value']);
        $html = $this->page()->getContent();
        $section = substr($html, strpos($html, 'data-it-data'), 1200);
        $this->assertStringContainsString('Saved Predictions', $section);
        $this->assertStringNotContainsString('Failed', $section);
    }

    // ------------------------------------------------------------ error states

    public function test_a_failing_data_source_shows_an_error_not_a_fake_zero(): void
    {
        $this->mock(FarmerOrganizationSummaryService::class, function ($mock): void {
            $mock->shouldReceive('summarize')->andThrow(new \RuntimeException('boom'));
        });

        $data = $this->payload();

        $this->assertSame('error', $data['farmers']['state']);
        $html = $this->page()->getContent();
        $this->assertStringContainsString('Unable to retrieve farmer database summary.', $html);
        $this->assertStringNotContainsString('boom', $html);
    }

    // ------------------------------------------------------------ recent activity + distribution

    public function test_recent_activity_lists_who_did_what_without_a_status_column(): void
    {
        $it = $this->it();
        SystemLog::create(['user_id' => $it->id, 'action' => 'User Login', 'details' => json_encode(['role' => 'IT Expert'])]);
        SystemLog::create(['user_id' => $it->id, 'action' => 'User Logout', 'details' => json_encode(['role' => 'IT Expert'])]);
        $this->analyticsRun('rice_yield_prediction', AnalyticsRun::STATUS_FAILED);       // a job failure is not "activity" here

        $recent = $this->payload($it)['recent'];
        $rows = collect($recent['rows']);
        $html = $this->page($it)->getContent();

        $this->assertSame(['Logged Out', 'Logged In'], $rows->pluck('action')->all());
        $this->assertSame('IT Person', $rows[0]['user']);
        $this->assertLessThanOrEqual(8, $rows->count());
        $section = substr($html, strpos($html, 'data-it-recent'), 2500);
        foreach (['<th scope="col">Time</th>', '<th scope="col">User</th>', '<th scope="col">Role</th>', '<th scope="col">Action</th>'] as $col) {
            $this->assertStringContainsString($col, $section);
        }
        $this->assertStringNotContainsString('Status', $section);
        $this->assertStringNotContainsString('Recorded', $section);
        $this->assertStringNotContainsString('prediction job', strtolower($section));
        $this->assertStringContainsString('View All Logs', $html);
    }

    public function test_distribution_shows_counts_and_percentages_in_one_donut(): void
    {
        $it = $this->it();
        User::factory()->count(3)->create(['role' => User::ROLE_FARMER]);
        $html = $this->page($it)->getContent();

        $rows = collect($this->payload($it)['distribution']['rows'])->keyBy('key');
        $this->assertSame(3, $rows['farmer']['count']);
        $this->assertSame(75.0, $rows['farmer']['pct']);
        $this->assertSame(1, $rows['it']['count']);
        $this->assertStringContainsString('TOTAL USERS', $html);
        $this->assertSame(1, substr_count($html, 'id="itUsersByRoleChart"'));
        $this->assertStringContainsString('75%', $html);
    }

    // ------------------------------------------------------------ activity endpoint

    public function test_activity_endpoint_charts_only_event_types_that_exist(): void
    {
        $it = $this->it();
        SystemLog::create(['user_id' => $it->id, 'action' => 'User Login', 'details' => '{}']);
        SystemLog::create(['user_id' => $it->id, 'action' => 'login', 'details' => '{}']);
        SystemLog::create(['user_id' => $it->id, 'action' => 'User Logout', 'details' => '{}']);
        SystemLog::create(['user_id' => $it->id, 'action' => 'Updated User', 'details' => json_encode(['context' => ['record_type' => 'User']])]);
        SystemLog::create(['user_id' => $it->id, 'action' => 'Created Advisory', 'details' => json_encode(['context' => ['record_type' => 'Advisory']])]);
        $this->analyticsRun('dataset_processing', AnalyticsRun::STATUS_COMPLETED);
        $this->analyticsRun('rice_yield_prediction', AnalyticsRun::STATUS_COMPLETED);
        $this->analyticsRun('rice_yield_prediction', AnalyticsRun::STATUS_FAILED);
        $this->failedJob();

        $json = $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => 7]))->assertOk()->json();
        $today = count($json['labels']) - 1;
        $series = collect($json['datasets'])->mapWithKeys(fn ($d) => [$d['label'] => $d['data'][$today]]);

        $this->assertSame('ok', $json['state']);
        $this->assertSame(7, $json['days']);
        $this->assertSame(2, $series['Logins']);                    // both spellings, once each
        $this->assertSame(1, $series['Account changes']);
        $this->assertSame(1, $series['Administrative actions']);    // the advisory; the logout and account change are not double counted
        $this->assertSame(1, $series['Data processing']);
        $this->assertSame(2, $series['System jobs']);               // job runs of any outcome are activity
        foreach ($series->keys() as $label) {
            $this->assertStringNotContainsStringIgnoringCase('fail', $label);          // no failure series
        }
    }

    public function test_series_with_no_events_are_left_out_of_the_chart(): void
    {
        $it = $this->it();
        SystemLog::create(['user_id' => $it->id, 'action' => 'User Login', 'details' => '{}']);

        $json = $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => 30]))->assertOk()->json();

        $this->assertSame(['Logins'], array_column($json['datasets'], 'label'));
        $this->assertCount(30, $json['labels']);
    }

    public function test_activity_endpoint_supports_the_three_ranges_and_reports_empty_neutrally(): void
    {
        $it = $this->it();

        foreach ([7, 30, 90] as $days) {
            $json = $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => $days]))->assertOk()->json();
            $this->assertSame($days, $json['days']);
            $this->assertCount($days, $json['labels']);
            $this->assertSame('empty', $json['state']);
            $this->assertSame([], $json['datasets']);
        }
        $this->assertSame(30, $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => 5]))->json('days'));
    }

    // ------------------------------------------------------------ refresh + sidebar

    public function test_refresh_endpoint_rebuilds_each_live_section(): void
    {
        $json = $this->actingAs($this->it())->getJson(route('admin.dashboard.live'))->assertOk()->json();

        $this->assertEqualsCanonicalizing(['kpis', 'alerts', 'health', 'distribution', 'data', 'farmers', 'recent'], array_keys($json['slots']));
        $this->assertStringContainsString('data-kpi="users"', $json['slots']['kpis']);
        $this->assertStringContainsString('No system issues requiring attention.', $json['slots']['alerts']);
        $this->assertSame('', trim($json['slots']['data']));           // nothing to show: the card disappears
        $this->assertArrayHasKey('refreshed_at', $json);
        $this->assertContains($json['level'], ['ok', 'warn', 'crit']);
    }

    public function test_sidebar_groups_it_navigation_and_lists_each_page_once(): void
    {
        $html = $this->page()->getContent();
        $sidebar = substr($html, strpos($html, 'sidebar-group'), strpos($html, 'IT Dashboard') - strpos($html, 'sidebar-group'));

        foreach (['>Main<', '>Management<', '>Data<', '>System<', '>Content<', '>Support<'] as $section) {
            $this->assertStringContainsString($section, $html);
        }
        $this->assertLessThan(strpos($html, '>Management<'), strpos($html, '>Main<'));
        $this->assertLessThan(strpos($html, '>Support<'), strpos($html, '>Content<'));
        // The menu is rendered twice (desktop sidebar + mobile offcanvas), so a page listed once per menu appears exactly twice.
        foreach ([route('users.index'), route('system-logs.index'), route('system-health.index'), route('reports.index'), route('farmer-profiles.index'), route('ai-admin.home')] as $url) {
            $this->assertSame(2, substr_count($sidebar, 'href="'.$url.'"'), $url);
        }
    }

    public function test_other_roles_keep_their_own_sidebar_sections(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('More Tools', $html);
        $this->assertStringNotContainsString('>Management<', $html);
    }
}
