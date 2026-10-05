<?php

namespace Tests\Feature;

use App\Models\AIChat;
use App\Models\AnalyticsRun;
use App\Models\ClimateRecord;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Analytics\ItDashboardInsights;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The extra KPIs and analytics on the IT dashboard: each query is checked against known data. */
class ItDashboardInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
        config(['backup.directory' => storage_path('framework/testing/insights-'.uniqid())]);
    }

    private function insights(): ItDashboardInsights
    {
        return app(ItDashboardInsights::class);
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    private function log(string $action, array $details = [], ?User $user = null, ?CarbonImmutable $at = null): SystemLog
    {
        $log = SystemLog::query()->create(['user_id' => $user?->id, 'action' => $action, 'details' => json_encode($details)]);
        $at && $log->forceFill(['created_at' => $at])->save();

        return $log;
    }

    private function failedJob(string $class, ?string $uuid = null): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid ?? (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['displayName' => $class, 'job' => 'x', 'data' => []]), 'exception' => 'x', 'failed_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ the page

    public function test_dashboard_shows_the_new_sections_and_loads_the_three_fonts(): void
    {
        $html = $this->actingAs($this->it())->get('/admin/dashboard')->assertOk()->assertViewIs('dashboards.it-expert')->getContent();

        foreach (['Usage Patterns', 'Login activity heatmap', 'New registrations per week', 'Accounts that never logged in', 'Background Jobs to Retry',
            'Scheduled tasks', 'Application errors', 'Failed logins by IP', 'Admin actions by user', 'Largest tables', 'Uploads added',
            'Queries per day', 'Last run duration', 'Weather API reliability'] as $title) {
            $this->assertStringContainsString($title, $html, $title);
        }
        // Same two families as the farmer dashboard (Georgia titles, Inter text); the page no longer defines its own.
        $this->assertStringContainsString('family=Inter:wght@', $html);
        $this->assertStringContainsString('class="is-it-dashboard"', $html);
        foreach (['--itd-display', '--itd-sans', '--itd-mono-font'] as $font) {
            $this->assertStringNotContainsString($font, $html, $font);
        }
        // Each section is on the page once.
        foreach (['data-it-kpis', 'data-it-brief', 'data-it-failed-jobs', 'data-it-health', 'data-it-pipeline', 'data-it-security', 'data-it-roles', 'data-it-activity', 'data-it-resources', 'data-it-integrity', 'data-it-recent', 'data-it-ai'] as $marker) {
            $this->assertSame(1, preg_match_all('/'.preg_quote($marker, '/').'(?![-\w])/', $html), $marker);
        }
        $this->assertSame(['total-users', 'active-users', 'status', 'database', 'api'], array_column($this->actingAs($this->it())->get('/admin/dashboard')->viewData('it')['kpis'], 'key'));
    }

    public function test_active_users_card_says_who_is_online_only_when_sessions_can_be_counted(): void
    {
        $note = fn () => collect($this->actingAs($this->it())->get('/admin/dashboard')->viewData('it')['kpis'])->firstWhere('key', 'active-users')['note'];

        config(['session.driver' => 'file']);
        $this->assertStringNotContainsString('online now', $note());      // never a made-up count

        config(['session.driver' => 'database']);
        Cache::flush();
        $this->assertMatchesRegularExpression('/· \d+ online now$/', $note());
    }

    public function test_refresh_endpoint_includes_the_usage_section(): void
    {
        $json = $this->actingAs($this->it())->getJson(route('admin.dashboard.live'))->assertOk()->json();

        $this->assertStringContainsString('Usage Patterns', $json['slots']['usage']);
    }

    // ------------------------------------------------------------ jobs and scheduler

    public function test_failed_jobs_are_grouped_by_class_with_top_five_and_can_be_retried(): void
    {
        foreach (['App\\Jobs\\A' => 3, 'App\\Jobs\\B' => 2, 'App\\Jobs\\C' => 1, 'App\\Jobs\\D' => 1, 'App\\Jobs\\E' => 1, 'App\\Jobs\\F' => 1] as $class => $n) {
            for ($i = 0; $i < $n; $i++) {
                $this->failedJob($class);
            }
        }

        $r = $this->insights()->failedJobsByClass();

        $this->assertSame(9, $r['total']);
        $this->assertCount(5, $r['rows']);
        $this->assertSame(['App\\Jobs\\A', 3], [$r['rows'][0]['class'], $r['rows'][0]['count']]);

        $this->actingAs($this->it())->post(route('admin.dashboard.retry-failed'), ['class' => 'App\\Jobs\\A'])->assertRedirect(route('admin.dashboard'));
        $this->assertSame(6, DB::table('failed_jobs')->count());         // the three class-A failures left the failed list
    }

    public function test_failed_jobs_empty_state(): void
    {
        $this->assertSame('empty', $this->insights()->failedJobsByClass()['state']);
    }

    public function test_only_it_can_retry_failed_jobs(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_FARMER]))->post(route('admin.dashboard.retry-failed'), ['class' => 'x'])->assertForbidden();
    }

    public function test_scheduled_tasks_show_next_run_and_the_recorded_last_run(): void
    {
        $first = $this->insights()->scheduledTasks();
        $this->assertSame('ok', $first['state']);
        $this->assertNull($first['rows'][0]['last']);                       // nothing has run yet: not a made-up time

        $task = Schedule::events()[0];
        event(new ScheduledTaskFinished($task, 0.5));                      // what the scheduler fires after each run

        $row = $this->insights()->scheduledTasks()['rows'][0];
        $this->assertNotNull($row['last']);
        $this->assertTrue($row['ok']);
        $this->assertTrue($row['next']->isFuture());
    }

    public function test_application_errors_are_counted_by_level_with_the_top_repeated_messages(): void
    {
        $dir = storage_path('framework/testing/logs-'.uniqid());
        File::ensureDirectoryExists($dir.'/logs');
        $now = now();
        $line = fn ($when, $level, $msg) => '['.$when->format('Y-m-d H:i:s').'] testing.'.$level.': '.$msg;
        file_put_contents($dir.'/logs/laravel.log', implode("\n", [
            $line($now->copy()->subHour(), 'ERROR', 'Weather API timed out {"x":1}'),
            $line($now->copy()->subHours(2), 'ERROR', 'Weather API timed out {"x":2}'),
            $line($now->copy()->subHours(3), 'CRITICAL', 'Disk full'),
            $line($now->copy()->subHours(4), 'WARNING', 'Slow query'),
            $line($now->copy()->subDays(3), 'ERROR', 'Old failure'),
            $line($now->copy()->subDays(20), 'ERROR', 'Too old'),
            $line($now->copy()->subHour(), 'INFO', 'Not an error'),
        ])."\n");
        $original = $this->app->storagePath();
        $this->app->useStoragePath($dir);

        try {
            $r = $this->insights()->applicationErrors();
        } finally {
            $this->app->useStoragePath($original);
            File::deleteDirectory($dir);
        }

        $this->assertSame(['error' => 2, 'warning' => 1, 'critical' => 1], $r['h24']);
        $this->assertSame(['error' => 3, 'warning' => 1, 'critical' => 1], $r['d7']);
        $this->assertSame('Weather API timed out', $r['top'][0]['message']);
        $this->assertSame(2, $r['top'][0]['count']);
    }

    // ------------------------------------------------------------ pipelines

    public function test_records_per_day_counts_each_source_over_seven_days(): void
    {
        ClimateRecord::factory()->count(2)->create();

        $r = $this->insights()->recordsPerDay();

        $this->assertCount(7, $r['climate']['dates']);
        $this->assertSame(2, $r['climate']['total']);
        $this->assertSame(2, end($r['climate']['counts']));
        $this->assertSame(0, $r['weather']['total']);
    }

    public function test_last_run_duration_comes_from_the_latest_completed_run(): void
    {
        $at = now()->subHour();
        AnalyticsRun::query()->forceCreate(['type' => 'dataset_processing', 'status' => AnalyticsRun::STATUS_COMPLETED, 'input' => ['schema' => 'x'],
            'created_at' => $at, 'updated_at' => $at, 'completed_at' => $at->copy()->addSeconds(90)]);
        AnalyticsRun::query()->forceCreate(['type' => 'dataset_processing', 'status' => AnalyticsRun::STATUS_FAILED, 'input' => ['schema' => 'x'],
            'created_at' => now(), 'updated_at' => now(), 'completed_at' => now()]);

        $rows = $this->insights()->lastRunDurations();

        $this->assertCount(1, $rows);
        $this->assertSame(90, $rows[0]['seconds']);
    }

    // ------------------------------------------------------------ database and storage

    public function test_largest_tables_are_ranked_by_row_count_and_limited_to_five(): void
    {
        User::factory()->count(4)->create();

        $tables = $this->insights()->largestTables();

        $this->assertLessThanOrEqual(5, count($tables));
        $counts = array_column($tables, 'rows');
        $rows = collect($tables)->firstWhere('table', 'users');
        $this->assertSame($counts, collect($counts)->sortDesc()->values()->all());      // biggest first
        $this->assertTrue($rows === null || $rows['rows'] === 4);                       // a listed count is the real row count
    }

    public function test_uploads_report_an_empty_state_without_files(): void
    {
        $this->assertSame('empty', $this->insights()->uploads()['state']);
        $this->assertCount(30, $this->insights()->uploads()['dates']);
    }

    // ------------------------------------------------------------ security

    public function test_login_security_computes_success_rate_off_hours_ips_and_admin_actions(): void
    {
        $it = $this->it();
        $night = CarbonImmutable::now('UTC')->subDay()->setTime(15, 0);       // 11 PM in Manila
        $day = CarbonImmutable::now('UTC')->subDay()->setTime(2, 0);          // 10 AM in Manila
        $this->log('User Login', [], $it, $night);
        $this->log('User Login', [], $it, $day);
        $this->log('User Login', [], $it, $day);
        $this->log('Failed Login', ['ip' => '9.9.9.9']);
        foreach (range(1, 2) as $_) {
            $this->log('Failed Login', ['ip' => '8.8.8.8']);
        }
        $this->log('Update Setting', [], $it);
        $this->log('Delete User', [], $it);
        $this->log('User Logout', [], $it);

        $r = $this->insights()->loginSecurity();

        $this->assertSame(3, $r['success']);
        $this->assertSame(3, $r['failed']);
        $this->assertSame(50.0, $r['rate']);
        $this->assertSame(1, $r['off_hours']);
        $this->assertSame(['8.8.8.8', 2], [$r['ips'][0]['ip'], $r['ips'][0]['count']]);
        $this->assertSame([$it->name, 2], [$r['admins'][0]['name'], $r['admins'][0]['count']]);   // logins and logouts are not admin actions
    }

    public function test_login_success_rate_is_not_available_without_attempts(): void
    {
        $it = $this->actingAs($this->it())->get('/admin/dashboard')->viewData('it');

        $this->assertNull($it['extra']['logins']['rate']);
    }

    public function test_active_sessions_count_recent_activity_on_the_database_driver(): void
    {
        config(['session.driver' => 'database']);
        foreach ([['a', now()->subMinutes(2)], ['b', now()->subMinutes(10)], ['c', now()->subHours(3)]] as [$id, $when]) {
            DB::table('sessions')->insert(['id' => $id, 'payload' => '', 'last_activity' => $when->getTimestamp()]);
        }

        $this->assertSame(['ok', 2], [$this->insights()->activeSessions()['state'], $this->insights()->activeSessions()['count']]);

        config(['session.driver' => 'file']);
        $this->assertSame('unavailable', $this->insights()->activeSessions()['state']);
    }

    // ------------------------------------------------------------ users and adoption

    public function test_login_heatmap_buckets_by_manila_day_and_hour(): void
    {
        $it = $this->it();
        $monday = CarbonImmutable::now('UTC')->startOfWeek()->subWeek()->setTime(1, 0);       // Monday 9 AM in Manila
        $this->log('User Login', [], $it, $monday);
        $this->log('User Login', [], $it, $monday->addMinutes(5));
        $this->log('User Login', [], $it, $monday->addDay());

        $h = $this->insights()->loginHeatmap();

        $this->assertSame(3, $h['total']);
        $this->assertSame(2, $h['grid'][0][9]);
        $this->assertSame(1, $h['grid'][1][9]);
        $this->assertSame(['day' => 'Monday', 'hour' => 9, 'count' => 2], $h['busiest']);
    }

    public function test_login_heatmap_empty_state(): void
    {
        $this->assertSame('empty', $this->insights()->loginHeatmap()['state']);
    }

    public function test_registrations_are_counted_per_week_for_eight_weeks(): void
    {
        User::factory()->count(2)->create();
        $old = User::factory()->create();
        $old->forceFill(['created_at' => now()->subWeeks(3)])->save();
        User::factory()->create()->forceFill(['created_at' => now()->subWeeks(20)])->save();      // outside the window

        $r = $this->insights()->registrations();

        $this->assertCount(8, $r['weeks']);
        $this->assertSame(3, $r['total']);
        $this->assertSame(2, end($r['counts']));
    }

    public function test_never_logged_in_excludes_new_accounts_and_accounts_that_logged_in(): void
    {
        $never = User::factory()->create();
        $never->forceFill(['created_at' => now()->subDays(5), 'last_active_at' => null])->save();
        $used = User::factory()->create();
        $used->forceFill(['created_at' => now()->subDays(5), 'last_active_at' => null])->save();
        $this->log('User Login', [], $used);
        User::factory()->create();                                         // created just now: not overdue yet

        $this->assertSame(1, $this->insights()->neverLoggedIn());

        User::factory()->create(['name' => 'Zed Never'])->forceFill(['created_at' => now()->subDays(9), 'last_active_at' => null])->save();
        $this->actingAs($this->it())->get(route('users.index', ['integrity' => 'never_logged_in']))->assertOk()->assertSee('Zed Never');
    }

    // ------------------------------------------------------------ Climaite AI

    public function test_ai_queries_per_day_for_the_last_seven_days(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        AIChat::query()->create(['user_id' => $farmer->id, 'question' => 'q', 'answer' => 'a']);
        AIChat::query()->create(['user_id' => $farmer->id, 'question' => 'q', 'answer' => 'a']);

        $r = $this->insights()->aiTrend();

        $this->assertSame('ok', $r['state']);
        $this->assertCount(7, $r['counts']);
        $this->assertSame(2, $r['total']);
    }

    public function test_ai_trend_empty_state(): void
    {
        $this->assertSame('empty', $this->insights()->aiTrend()['state']);
    }
}
