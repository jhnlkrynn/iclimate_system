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

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        foreach (['admin.dashboard', 'admin.dashboard.live', 'admin.dashboard.activity', 'admin.dashboard.recent'] as $route) {
            $this->actingAs($mao)->get(route($route))->assertForbidden();
            $this->actingAs($farmer)->get(route($route))->assertForbidden();
        }
    }

    public function test_page_follows_the_layout_in_order_and_has_no_removed_sections(): void
    {
        $html = $this->page()->getContent();
        $order = ['IT Dashboard', 'data-it-summary', 'data-it-kpis', 'data-it-alerts', 'data-it-health', 'data-it-pipeline', 'data-it-activity',
            'data-it-security', 'data-it-roles', 'data-it-ai', 'data-it-usage', 'data-it-resources', 'data-it-integrity', 'data-it-ops', 'data-it-recent'];

        $last = -1;
        foreach ($order as $marker) {
            $pos = strpos($html, $marker);
            $this->assertNotFalse($pos, "Missing section: $marker");
            $this->assertGreaterThan($last, $pos, "Section out of order: $marker");
            $last = $pos;
        }
        foreach (['Monitor system health, data pipelines, security, and user activity', 'Page refreshed at', 'Weather data synced at', 'Manage Users', 'View System Logs'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Quick Actions', 'Farmer Database', 'User Distribution', 'Average Farm Size', 'itUsersByRoleChart'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    public function test_there_are_exactly_six_kpi_cards_and_unmeasurable_ones_say_not_available(): void
    {
        $data = $this->payload();

        $this->assertSame(['status', 'freshness', 'backup', 'users', 'login-rate', 'app-errors'], array_column($data['kpis'], 'key'));
        // A metric with no data yet shows a dash and says why; no card says "Not available".
        foreach (['freshness', 'login-rate'] as $key) {
            $card = collect($data['kpis'])->firstWhere('key', $key);
            $this->assertSame('—', $card['value'], $key);
            $this->assertNotEmpty($card['compare'], $key);
        }
        foreach (array_merge($data['kpis'], $data['kpis_extra']) as $card) {
            $this->assertNotSame('Not available', $card['value'], $card['key']);
            $this->assertNotEmpty($card['tip'], $card['key']);
            $this->assertNotEmpty($card['href'], $card['key']);
        }
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

    public function test_users_by_role_is_a_table_with_total_active_and_percent(): void
    {
        User::factory()->create(['role' => User::ROLE_MAO]);

        $html = $this->page()->getContent();

        $this->assertStringContainsString('data-it-roles', $html);
        foreach (['Farmer', 'MAO Personnel', 'IT Expert', 'Total accounts', 'Active (30 days)', 'Active %'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_farmer_account_and_profile_gap_is_a_data_integrity_warning(): void
    {
        $with = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $with->id]);
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER]);

        $data = $this->payload();

        $this->assertSame(3, $data['integrity']['farmer_accounts']);
        $this->assertSame(1, $data['integrity']['farmer_profiles']);
        $this->assertSame(2, $data['integrity']['without_profile']);
        $this->assertNotNull($this->alert($data, 'Farmer Accounts and Profiles Differ'));
        $this->actingAs($this->it())->get(route('admin.dashboard'))->assertSee('data-integrity-gap', false)->assertSee('3 farmer accounts but 1 farmer profile records');
    }

    public function test_no_gap_warning_when_accounts_and_profiles_match(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id]);

        $data = $this->payload();

        $this->assertSame(0, $data['integrity']['account_gap']);
        $this->assertNull($this->alert($data, 'Farmer Accounts and Profiles Differ'));
    }

    // ------------------------------------------------------------ health, backup and alerts share one source

    public function test_a_fresh_system_is_healthy_and_all_clear(): void
    {
        $data = $this->payload();

        $this->assertSame('ok', $data['overall']['level']);
        $this->assertSame([], $data['alerts']);
        $this->page()->assertSee('All clear');
        $this->assertSame(['application', 'database', 'jobs', 'scheduler', 'weather', 'storage', 'backup'], array_column($data['services'], 'key'));
    }

    public function test_database_backup_row_and_verification_alert_never_disagree(): void
    {
        $it = $this->it();
        $this->backupFile(hoursOld: 2);
        $data = $this->payload($it);
        $this->assertSame('ok', $this->service($data, 'backup')['level']);
        $this->assertNull($this->alert($data, 'Backup Verification Failed'));

        BackupVerification::query()->forceCreate([
            'backup_filename' => 'iclimate_2026-09-25_0315.backup', 'verified_at' => now()->subHour(),
            'status' => BackupVerification::FAILED, 'failure_reason' => 'Archive could not be read',
        ]);
        $data = $this->payload($it);

        $this->assertNotNull($this->alert($data, 'Backup Verification Failed'));
        $this->assertSame('warn', $this->service($data, 'backup')['level']);                 // the health row agrees with the alert
        $this->assertStringContainsString('Archive could not be read', $this->service($data, 'backup')['detail']);
        $this->assertSame('warn', $data['overall']['level']);
    }

    public function test_overdue_backup_is_only_a_warning_when_provable(): void
    {
        $it = $this->it();
        $this->backupFile(hoursOld: 120);

        $data = $this->payload($it);                                        // overdue but nothing proves the scheduler runs
        $this->assertSame('unk', $this->service($data, 'backup')['level']);
        $this->assertNull($this->alert($data, 'Database Backup Overdue'));

        $this->schedulerIsRunning();
        $data = $this->payload($it);
        $this->assertSame('warn', $this->service($data, 'backup')['level']);
        $this->assertSame('warning', $this->alert($data, 'Database Backup Overdue')['severity']);
    }

    public function test_failed_jobs_and_stale_weather_become_alerts_from_the_health_rows(): void
    {
        $it = $this->it();
        $this->failedJob();
        $this->weather(minutesAgo: 60 * 8);

        $data = $this->payload($it);

        $this->assertSame('warn', $this->service($data, 'jobs')['level']);
        $this->assertNotNull($this->alert($data, 'Background Jobs Need Review'));
        $this->assertSame('crit', $this->service($data, 'weather')['level']);
        $this->assertSame('critical', $this->alert($data, 'Weather Data Out of Date')['severity']);
        $this->assertSame('critical', $data['alerts'][0]['severity']);          // critical first
        $this->assertSame('crit', $data['overall']['level']);
    }

    public function test_weather_row_shows_last_successful_sync_and_header_labels_both_timestamps(): void
    {
        $this->weather(minutesAgo: 37);

        $data = $this->payload();
        $row = $this->service($data, 'weather');

        $this->assertSame('ok', $row['level']);
        $this->assertStringContainsString('Last successful sync', $row['detail']);
        $this->assertNotNull($data['weather_synced_at']);
        $this->page()->assertSee('Page refreshed at')->assertSee('Weather data synced at');
        $fresh = collect($data['kpis'])->firstWhere('key', 'freshness');
        $this->assertSame(['37', 'min old'], [$fresh['value'], $fresh['unit']]);
        $this->assertStringContainsString('Target: < 2h', $fresh['compare']);
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
        $json = $this->actingAs($this->it())->getJson(route('admin.dashboard.live'))->assertOk()->json();

        foreach (['kpis', 'alerts', 'health', 'pipeline', 'security', 'roles', 'resources', 'integrity', 'recent', 'ai'] as $slot) {
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
