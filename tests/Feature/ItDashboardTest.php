<?php

namespace Tests\Feature;

use App\Models\AIChat;
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

    private function service(array $payload, string $key): ?array
    {
        return collect($payload['services'])->firstWhere('key', $key);
    }

    /** The forecasting service answers its health check (setUp makes every outside call fail). */
    private function predictionApiOnline(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'models_loaded' => ['rice', 'weather']]), '*' => Http::response([], 500)]);
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

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        foreach (['admin.dashboard', 'admin.dashboard.live', 'admin.dashboard.activity', 'admin.dashboard.recent'] as $route) {
            $this->actingAs($mao)->get(route($route))->assertForbidden();
            $this->actingAs($farmer)->get(route($route))->assertForbidden();
        }
    }

    public function test_page_follows_the_layout_in_order_and_has_no_removed_sections(): void
    {
        $html = $this->page()->getContent();
        // IT Dashboard (2026-10): System Status, Users by Role, Errors and Failed Jobs (with retry), Security.
        $order = ['IT Personnel Dashboard', 'data-it-section="status"', 'data-it-kpis', 'data-it-health', 'data-it-section="users"', 'data-it-roles',
            'data-it-section="errors"', 'data-it-failed-jobs', 'data-it-ops', 'data-it-section="security"', 'data-it-security'];

        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker);
            $this->assertNotFalse($pos, "Missing section: $marker");
            $this->assertGreaterThan($last, $pos, "Section out of order: $marker");
            $last = $pos;
        }
        $this->assertSame(4, substr_count($html, 'data-it-section='));
        foreach (['System status, users, errors and security at a glance', 'Updated at', 'System Status', 'Errors and Failed Jobs'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // No tabs, no activity chart, no heat map, and nothing that moved to System Health or System Logs.
        foreach (['role="tablist"', 'data-panel=', 'itSystemActivityTrendChart', 'id="barangayRiskMap"', 'data-it-activity', 'data-it-brief', 'data-it-ai', 'data-it-usage',
            'data-it-resources', 'data-it-integrity', 'data-it-pipeline', 'data-it-recent', 'Recent System Activity'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        // The simpler dashboard has no attention banner, no alert list and no second row of cards.
        foreach (['Quick Actions', 'Farmer Database', 'User Distribution', 'Average Farm Size', 'itUsersByRoleChart', 'data-it-summary', 'data-it-alerts', 'Attention Required', 'Needs your attention', 'Data Sources Overdue'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    public function test_the_five_cards_show_real_counts_and_the_same_statuses_as_the_health_rows(): void
    {
        $it = $this->it();
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER]);
        $this->predictionApiOnline();
        $this->weather(minutesAgo: 10);

        $data = $this->payload($it);
        $card = fn (string $key) => collect($data['kpis'])->firstWhere('key', $key);

        $this->assertSame(['total-users', 'active-users', 'status', 'database', 'api'], array_column($data['kpis'], 'key'));
        $this->assertSame(['3', '2 farmers · 0 MAO · 1 IT'], [$card('total-users')['value'], $card('total-users')['note']]);
        $this->assertSame((string) $data['roles']['active'], $card('active-users')['value']);
        $this->assertSame($data['overall']['label'], $card('status')['value']);
        $this->assertSame($this->service($data, 'database')['label'], $card('database')['value']);
        $this->assertSame('Online', $card('database')['value']);
        $this->assertSame(['Online', 'Weather API and Prediction API are responding'], [$card('api')['value'], $card('api')['note']]);
        foreach ($data['kpis'] as $k) {
            $this->assertNotSame('Not available', $k['value'], $k['key']);
            $this->assertNotEmpty($k['note'], $k['key']);
            $this->assertNotEmpty($k['href'], $k['key']);
        }
    }

    public function test_api_status_follows_the_real_checks_and_leaves_out_a_service_with_nothing_to_check(): void
    {
        $it = $this->it();
        $card = fn (array $data) => collect($data['kpis'])->firstWhere('key', 'api');

        // setUp: the forecasting service does not answer, and no weather has been fetched yet.
        $data = $this->payload($it);
        $this->assertSame(['warn', 'Needs attention'], [$this->service($data, 'prediction-api')['level'], $this->service($data, 'prediction-api')['label']]);
        $this->assertSame(['unk', 'Waiting for first update'], [$this->service($data, 'weather')['level'], $this->service($data, 'weather')['label']]);
        $this->assertSame(['Needs attention', '0 of 1 services responding'], [$card($data)['value'], $card($data)['note']]);

        $this->predictionApiOnline();
        $data = $this->payload($it);
        $this->assertSame(['ok', 'Online'], [$this->service($data, 'prediction-api')['level'], $this->service($data, 'prediction-api')['label']]);
        $this->assertStringContainsString('2 models loaded', $this->service($data, 'prediction-api')['detail']);
        $this->assertSame(['Online', 'Prediction API is responding'], [$card($data)['value'], $card($data)['note']]);

        config(['services.farming_ai.url' => '']);
        $data = $this->payload($it);
        $this->assertSame('unk', $this->service($data, 'prediction-api')['level']);
        $this->assertSame('Not set up yet', $card($data)['value']);
    }

    // ------------------------------------------------------------ users

    public function test_active_users_counts_recent_logins_not_enabled_accounts(): void
    {
        $it = $this->it();
        $it->forceFill(['last_active_at' => now()->subDays(40)])->save();
        $recent = User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]);
        $recent->forceFill(['last_active_at' => now()->subDays(3)])->save();
        $loggedIn = User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]);
        SystemLog::query()->create(['user_id' => $loggedIn->id, 'action' => 'User Login', 'details' => '{}']);
        User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE])->forceFill(['last_active_at' => now()->subDays(60)])->save();

        $roles = $this->payload($it)['roles'];
        $farmers = collect($roles['rows'])->firstWhere('role', User::ROLE_FARMER);

        $this->assertSame(3, $farmers['total']);
        $this->assertSame(2, $farmers['active']);            // enabled accounts would have said 3
        $this->assertEqualsWithDelta(66.7, $farmers['pct'], 0.05);
        $this->assertSame(4, $roles['total']);
        $this->assertSame(2, $roles['active']);
    }

    public function test_users_by_role_is_a_six_column_table_that_fits_its_card(): void
    {
        User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->page()->getContent();

        $this->assertStringContainsString('data-it-roles', $html);
        // No fixed minimum width (it used to force sideways scrolling), and a stacked layout for phones.
        $css = file_get_contents(resource_path('css/it-dashboard.css'));
        $this->assertStringContainsString('.itd-table.is-roles { min-width: 0; }', $css);
        $this->assertStringContainsString('.itd-table.is-roles td::before { content: attr(data-label);', $css);
        foreach (['Farmer', 'MAO Personnel', 'IT Expert', 'Total accounts', 'Share', 'Enabled', 'Disabled', 'Active users', 'data-label="Active users"'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_users_by_role_counts_enabled_and_disabled_accounts_from_the_account_status(): void
    {
        $it = $this->it();
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE]);
        User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_INACTIVE]);

        $roles = $this->payload($it)['roles'];
        $farmers = collect($roles['rows'])->firstWhere('role', User::ROLE_FARMER);

        $this->assertSame([3, 2, 1], [$farmers['total'], $farmers['enabled'], $farmers['disabled']]);
        $this->assertSame([4, 3, 1], [$roles['total'], $roles['enabled'], $roles['disabled']]);     // the IT account is enabled too
    }

    public function test_page_uses_the_farmer_dashboard_design_system(): void
    {
        $html = $this->page()->getContent();
        $css = file_get_contents(resource_path('css/it-dashboard.css'));

        $this->assertStringContainsString('class="is-it-dashboard"', $html);                     // same sidebar colour and Georgia + Inter as the farmer page
        $this->assertStringContainsString('class="mfd itd"', $html);
        foreach (['mfd-header', 'mfd-kicker', 'mfd-kpi ', 'mfd-kpi-value', 'mfd-card-head', 'mfd-pill is-', 'mfd-btn is-quiet'] as $shared) {
            $this->assertStringContainsString($shared, $html, $shared);
        }
        $this->assertStringNotContainsString('--itd-ink', $html);                                // no page-only palette any more
        $this->assertDoesNotMatchRegularExpression('/font-family/', preg_replace('#/\*.*?\*/#s', '', $css));   // families come from the shared system
        $this->assertStringNotContainsString('backdrop-filter', $css);
        preg_match_all('/font-size: ([\d.]+)rem/', $css, $sizes);
        foreach ($sizes[1] as $rem) {
            $this->assertGreaterThanOrEqual(0.75, (float) $rem, 'no text under 12px');
        }
    }

    public function test_it_personnel_get_the_notification_bell_with_their_own_unread_count(): void
    {
        $it = $this->it();
        \App\Models\Notification::query()->create(['user_id' => $it->id, 'title' => 'Maintenance window', 'message' => 'Tonight at 10 PM.', 'type' => 'Announcement', 'is_read' => false]);

        $this->page($it)->assertSee('aria-label="Notifications, 1 unread"', false);
        $this->actingAs($it)->get(route('notifications.index'))->assertOk()->assertSee('Maintenance window');
    }

    public function test_farmer_account_and_profile_gap_is_shown_on_the_data_integrity_card(): void
    {
        $with = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $with->id]);
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER]);

        $data = $this->payload();

        $this->assertSame(3, $data['integrity']['farmer_accounts']);
        $this->assertSame(1, $data['integrity']['farmer_profiles']);
        $this->assertSame(2, $data['integrity']['without_profile']);
        // The data integrity card is on System Health since 2026-10 (it reads the overview prepared by the dashboard).
        $this->actingAs($this->it())->get(route('system-health.index'))->assertSee('data-integrity-gap', false)->assertSee('3 farmer accounts but 1 farmer profile records');
    }

    public function test_no_gap_warning_when_accounts_and_profiles_match(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $data = $this->payload();

        $this->assertSame(0, $data['integrity']['account_gap']);
    }

    // ------------------------------------------------------------ health rows, the header status and the cards share one source

    public function test_a_fresh_system_is_healthy_with_positive_status_words(): void
    {
        $this->predictionApiOnline();
        $data = $this->payload();

        $this->assertSame(['ok', 'Healthy', 0], [$data['overall']['level'], $data['overall']['label'], $data['overall']['issues']]);
        $this->assertSame(['application', 'database', 'weather', 'prediction-api', 'jobs', 'scheduler', 'storage', 'backup'], array_column($data['services'], 'key'));
        $this->assertSame(['Online', 'Online', 'Online', 'Active', 'Healthy'], array_map(fn ($k) => $this->service($data, $k)['label'], ['application', 'database', 'prediction-api', 'jobs', 'storage']));
        foreach ($data['services'] as $s) {
            $this->assertNotContains($s['level'], ['warn', 'crit'], $s['key']);
            $this->assertNotContains($s['label'], ['Warning', 'Critical', 'Unknown'], $s['key']);     // plain words only
            $this->assertNotEmpty($s['detail'], $s['key']);
        }
        $this->predictionApiOnline();
        $this->page()->assertSee('data-service="application" data-level="ok"', false)->assertSee('Healthy');
    }

    public function test_database_backup_row_reports_a_failed_verification(): void
    {
        $it = $this->it();
        $this->backupFile(hoursOld: 2);
        $data = $this->payload($it);
        $this->assertSame('ok', $this->service($data, 'backup')['level']);
        $this->assertNull($this->service($data, 'backup')['issue']);

        BackupVerification::query()->forceCreate([
            'backup_filename' => 'iclimate_2026-09-25_0315.backup', 'verified_at' => now()->subHour(),
            'status' => BackupVerification::FAILED, 'failure_reason' => 'Archive could not be read',
        ]);
        $data = $this->payload($it);

        $this->assertSame('verification', $this->service($data, 'backup')['issue']);
        $this->assertSame(['warn', 'Needs attention'], [$this->service($data, 'backup')['level'], $this->service($data, 'backup')['label']]);
        $this->assertStringContainsString('Archive could not be read', $this->service($data, 'backup')['detail']);
        $this->assertSame('warn', $data['overall']['level']);
    }

    public function test_overdue_backup_is_only_a_warning_when_provable(): void
    {
        $it = $this->it();
        $this->backupFile(hoursOld: 120);

        $data = $this->payload($it);                                        // overdue but nothing proves the scheduler runs
        $this->assertSame('unk', $this->service($data, 'backup')['level']);
        $this->assertNull($this->service($data, 'backup')['issue']);

        $this->schedulerIsRunning();
        $data = $this->payload($it);
        $this->assertSame('warn', $this->service($data, 'backup')['level']);
        $this->assertSame('overdue', $this->service($data, 'backup')['issue']);
    }

    public function test_failed_jobs_and_stale_weather_need_attention_while_the_system_stays_operational(): void
    {
        $it = $this->it();
        $this->predictionApiOnline();
        $this->failedJob();
        $this->weather(minutesAgo: 60 * 8);

        $data = $this->payload($it);

        $this->assertSame(['warn', 'Needs attention'], [$this->service($data, 'jobs')['level'], $this->service($data, 'jobs')['label']]);
        $this->assertSame(['crit', 'Needs attention'], [$this->service($data, 'weather')['level'], $this->service($data, 'weather')['label']]);
        // The application and the database are up, so the system is still working: two rows ask for a look.
        $this->assertSame(['warn', 'Operational', 2], [$data['overall']['level'], $data['overall']['label'], $data['overall']['issues']]);
        $this->assertStringEndsWith('2 to review', $data['overall']['text']);
        $this->assertSame('Needs attention', collect($data['kpis'])->firstWhere('key', 'api')['value']);
    }

    public function test_weather_row_shows_the_last_successful_update_and_the_header_shows_when_the_page_was_built(): void
    {
        $this->weather(minutesAgo: 37);

        $data = $this->payload();
        $row = $this->service($data, 'weather');

        $this->assertSame(['ok', 'Operational'], [$row['level'], $row['label']]);
        $this->assertStringContainsString('Live weather was last updated', $row['detail']);
        $this->assertStringContainsString('37 minutes ago', $row['detail']);
        $this->assertNotNull($data['weather_synced_at']);
        $this->page()->assertSee('Updated at')->assertDontSee('Weather data synced at');
    }

    // ------------------------------------------------------------ pipelines, security, resources, AI

    public function test_pipeline_lists_the_five_sources_and_reports_unknown_when_empty(): void
    {
        $data = $this->payload();

        $this->assertSame(['weather', 'climate', 'rice', 'predictions', 'advisories'], array_column($data['pipeline'], 'key'));
        $this->assertSame('unk', collect($data['pipeline'])->firstWhere('key', 'weather')['level']);
    }

    public function test_security_counts_come_from_the_audit_log(): void
    {
        SystemLog::query()->create(['action' => 'Failed Login', 'details' => '{"ip":"10.0.0.9"}']);
        SystemLog::query()->create(['action' => 'Failed Login', 'details' => '{"ip":"10.0.0.9"}']);
        $old = SystemLog::query()->create(['action' => 'Failed Login', 'details' => '{}']);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        SystemLog::query()->create(['action' => 'Delete User', 'details' => '{}']);
        SystemLog::query()->create(['action' => 'Login Lockout', 'details' => '{}']);

        $s = $this->payload()['security'];

        $this->assertSame(2, $s['failed_24h']);
        $this->assertSame(3, $s['failed_7d']);
        $this->assertSame(1, $s['lockouts_24h']);
        $this->assertSame(1, $s['privileged_7d']);
    }

    public function test_dormant_accounts_have_no_login_for_90_days(): void
    {
        $dormant = User::factory()->create(['role' => User::ROLE_FARMER]);
        $dormant->forceFill(['created_at' => now()->subDays(200), 'last_active_at' => now()->subDays(120)])->save();
        User::factory()->create(['role' => User::ROLE_FARMER]);                 // never logged in, but only just created
        $this->it()->forceFill(['last_active_at' => now()])->save();

        $this->assertSame(1, $this->payload()['security']['dormant']);
    }

    public function test_failed_logins_are_recorded_for_the_security_panel(): void
    {
        User::factory()->create(['email' => 'real@example.test']);

        $this->post(route('login'), ['email' => 'real@example.test', 'password' => 'wrong-password']);

        $log = SystemLog::query()->where('action', 'Failed Login')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('real@example.test', (string) $log->details);
    }

    public function test_resources_show_not_available_instead_of_fake_values(): void
    {
        config(['app.version' => null, 'app.deployed_at' => null]);

        $r = $this->payload()['resources'];

        $this->assertNull($r['version']);
        $this->assertNull($r['deployed_at']);
        $this->page()->assertSee('Not available');
    }

    public function test_ai_usage_counts_todays_queries(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        foreach ([200, 400] as $ms) {
            AIChat::query()->create(['user_id' => $farmer->id, 'question' => 'q', 'answer' => 'a', 'response_time_ms' => $ms]);
        }
        AIChat::query()->create(['user_id' => $farmer->id, 'question' => 'q', 'answer' => 'a', 'source_type' => 'System', 'response_time_ms' => 0]);

        $ai = $this->payload()['ai'];

        $this->assertSame(3, $ai['queries']);
        $this->assertSame(1, $ai['failed']);
        $this->assertSame(200, $ai['avg_ms']);
    }

    // ------------------------------------------------------------ recent activity + chart + refresh

    public function test_recent_activity_flags_failed_logins_and_privileged_actions_and_is_filterable(): void
    {
        $it = $this->it();
        SystemLog::query()->create(['user_id' => $it->id, 'action' => 'User Login', 'details' => '{"ip":"1.1.1.1","role":"IT Expert"}']);
        SystemLog::query()->create(['action' => 'Failed Login', 'details' => '{"ip":"9.9.9.9"}']);
        SystemLog::query()->create(['user_id' => $it->id, 'action' => 'Delete User', 'details' => '{"ip":"1.1.1.1","role":"IT Expert"}']);

        $rows = $this->payload($it)['recent']['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame(['privileged', 'failed', null], array_column($rows, 'flag'));
        $this->assertSame('9.9.9.9', $rows[1]['ip']);

        $json = $this->actingAs($it)->getJson(route('admin.dashboard.recent', ['action' => 'failed']))->assertOk()->json();
        $this->assertStringContainsString('9.9.9.9', $json['html']);
        $this->assertStringNotContainsString('1.1.1.1', $json['html']);

        $empty = $this->actingAs($it)->getJson(route('admin.dashboard.recent', ['role' => User::ROLE_FARMER]))->json();
        $this->assertStringContainsString('No activity matches', $empty['html']);
    }

    public function test_recent_activity_shows_at_most_ten_rows(): void
    {
        SystemLog::factory()->count(14)->create();

        $this->assertCount(10, $this->payload()['recent']['rows']);
    }

    public function test_activity_endpoint_has_the_five_series_and_supports_the_three_ranges(): void
    {
        $it = $this->it();
        SystemLog::query()->create(['user_id' => $it->id, 'action' => 'User Login', 'details' => '{}']);
        SystemLog::query()->create(['action' => 'Failed Login', 'details' => '{}']);

        foreach ([7, 30, 90] as $days) {
            $json = $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => $days]))->assertOk()->json();
            $this->assertSame($days, $json['days']);
            $this->assertCount($days, $json['dates']);
            $this->assertSame(['Logins', 'Admin actions', 'Data processing', 'System jobs', 'Errors'], array_column($json['datasets'], 'label'));
        }
        $json = $this->actingAs($it)->getJson(route('admin.dashboard.activity', ['range' => 30]))->json();
        $this->assertSame(1, array_sum(collect($json['datasets'])->firstWhere('label', 'Logins')['data']));
        $this->assertSame(1, array_sum(collect($json['datasets'])->firstWhere('label', 'Errors')['data']));
    }

    public function test_activity_is_empty_when_nothing_happened(): void
    {
        $this->assertSame('empty', $this->actingAs($this->it())->getJson(route('admin.dashboard.activity'))->json('state'));
    }

    public function test_refresh_endpoint_rebuilds_every_live_section(): void
    {
        $this->predictionApiOnline();
        $json = $this->actingAs($this->it())->getJson(route('admin.dashboard.live'))->assertOk()->json();

        foreach (['kpis', 'health', 'brief', 'pipeline', 'jobs', 'security', 'roles', 'resources', 'integrity', 'recent', 'ai'] as $slot) {
            $this->assertNotSame('', trim($json['slots'][$slot]), $slot);
        }
        $this->assertSame('Healthy', $json['status']);
    }

    public function test_integrity_links_open_filtered_lists(): void
    {
        $it = $this->it();
        $withProfile = User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'Has Profile']);
        FarmerProfile::factory()->create(['user_id' => $withProfile->id]);
        User::factory()->create(['role' => User::ROLE_FARMER, 'name' => 'No Profile Farmer']);

        $this->actingAs($it)->get(route('users.index', ['integrity' => 'farmers_without_profile']))
            ->assertOk()->assertSee('No Profile Farmer')->assertDontSee('Has Profile');
    }

    public function test_sidebar_groups_it_navigation_and_lists_each_page_once(): void
    {
        $html = $this->page()->getContent();
        $sidebar = substr($html, strpos($html, 'sidebar-group'), strpos($html, 'IT Personnel Dashboard') - strpos($html, 'sidebar-group'));

        // Four groups, so all 14 links fit a laptop screen without scrolling the menu.
        foreach (['>Main Menu<', '>Management<', '>Data<', '>System<'] as $section) {
            $this->assertStringContainsString($section, $html);
        }
        $this->assertLessThan(strpos($html, '>Management<'), strpos($html, '>Main Menu<'));
        $this->assertLessThan(strpos($html, '>System<'), strpos($html, '>Data<'));
        // The menu is rendered twice (desktop sidebar + mobile offcanvas), so a page listed once per menu appears exactly twice.
        foreach ([route('users.index'), route('system-logs.index'), route('system-health.index'), route('reports.index'), route('farmer-profiles.index')] as $url) {
            $this->assertSame(2, substr_count($sidebar, 'href="'.$url.'"'), $url);
        }
    }

    public function test_other_roles_keep_their_own_sidebar_sections(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()->getContent();

        // MAO (2026-10): one list of eleven pages, without IT's sections.
        $this->assertStringContainsString('Field Reports', $html);
        $this->assertStringNotContainsString('More Tools', $html);
        $this->assertStringNotContainsString('>Management<', $html);
        $this->assertStringNotContainsString('System Health', $html);
    }
}
