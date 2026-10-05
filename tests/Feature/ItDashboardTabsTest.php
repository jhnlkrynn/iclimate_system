<?php

namespace Tests\Feature;

use App\Models\BackupVerification;
use App\Models\ClimateRecord;
use App\Models\ScheduledTaskRun;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\WeatherFetchLog;
use App\Services\Analytics\ItDashboardInsights;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tabs, the System Health wording, task history, weather fetch logging and the retry audit trail. */
class ItDashboardTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);
        config(['backup.directory' => storage_path('framework/testing/tabs-'.uniqid())]);
    }

    private function it(): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
    }

    /** The forecasting service answers its health check (setUp makes every outside call fail). */
    private function predictionApiOnline(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'models_loaded' => ['rice', 'weather']]), '*' => Http::response([], 500)]);
    }

    // ------------------------------------------------------------ tabs

    public function test_each_tab_is_selected_from_the_url_and_defaults_to_overview(): void
    {
        $it = $this->it();
        foreach (['overview' => 'Overview', 'heatmap' => 'Heat Map', 'security' => 'Security', 'users' => 'Users & Usage', 'resources' => 'Resources & Data', 'activity' => 'Activity Log'] as $key => $label) {
            $html = $this->actingAs($it)->get(route('admin.dashboard', ['tab' => $key]))->assertOk()->assertViewHas('tab', $key)->getContent();

            $this->assertMatchesRegularExpression('/data-tab="'.$key.'"[^>]*aria-selected="true"/', $html, $key);
            $this->assertMatchesRegularExpression('/data-panel="'.$key.'"[^>]*aria-labelledby="itd-tab-'.$key.'"\s*>/', $html, "$key panel is visible");
            $this->assertStringContainsString(e($label), $html);
        }

        $this->actingAs($it)->get(route('admin.dashboard', ['tab' => 'nonsense']))->assertViewHas('tab', 'overview');
        $this->actingAs($it)->get(route('admin.dashboard'))->assertViewHas('tab', 'overview');
    }

    public function test_sections_live_in_the_tab_the_brief_gives_them(): void
    {
        $html = $this->actingAs($this->it())->get(route('admin.dashboard'))->getContent();
        $panel = function (string $name) use ($html): string {
            $start = strpos($html, 'data-panel="'.$name.'"');
            $next = preg_match('/data-panel="/', $html, $m, PREG_OFFSET_CAPTURE, $start + 10) ? $m[0][1] : strlen($html);

            return substr($html, $start, $next - $start);
        };

        foreach (['data-it-health', 'data-it-activity', 'data-it-brief'] as $marker) {
            $this->assertStringContainsString($marker, $panel('overview'), $marker);
        }
        foreach (['Failed logins by IP', 'Admin actions by user', 'Accounts that never logged in', 'Dormant accounts'] as $text) {
            $this->assertStringContainsString($text, $panel('security'), $text);
        }
        foreach (['data-it-roles', 'data-it-usage', 'data-it-ai', 'Login activity heatmap', 'New registrations per week'] as $text) {
            $this->assertStringContainsString($text, $panel('users'), $text);
        }
        foreach (['Largest tables', 'Uploads added', 'data-it-integrity', 'data-it-pipeline', 'data-it-scheduler', 'data-it-app-errors', 'data-it-failed-jobs'] as $text) {
            $this->assertStringContainsString($text, $panel('resources'), $text);
        }
        $this->assertStringContainsString('data-it-recent', $panel('activity'));
    }

    public function test_every_section_has_a_subtitle_and_a_last_updated_label(): void
    {
        $html = $this->actingAs($this->it())->get(route('admin.dashboard'))->getContent();

        foreach (['data-it-health', 'data-it-brief', 'data-it-failed-jobs', 'data-it-pipeline', 'data-it-security', 'data-it-roles', 'data-it-usage', 'data-it-ai', 'data-it-resources', 'data-it-integrity', 'data-it-ops', 'data-it-recent'] as $marker) {
            $section = substr($html, strpos($html, $marker), 6000);
            $this->assertStringContainsString('itd-sub', $section, "$marker subtitle");
            $this->assertStringContainsString('Last updated', $section, "$marker last updated");
        }
    }

    // ------------------------------------------------------------ System Health wording

    public function test_the_overview_has_no_attention_banner_and_a_working_system_reads_healthy(): void
    {
        $this->predictionApiOnline();
        $response = $this->actingAs($this->it())->get(route('admin.dashboard'));
        $it = $response->viewData('it');

        $this->assertSame(['Healthy', 0], [$it['overall']['label'], $it['overall']['issues']]);
        $this->assertArrayNotHasKey('attention', $it);
        $this->assertArrayNotHasKey('alerts', $it);
        $response->assertSee('System Health')->assertDontSee('Needs your attention')->assertDontSee('Attention Required');
    }

    public function test_services_that_need_a_look_say_so_in_plain_words_on_their_own_rows(): void
    {
        $it = $this->it();
        $this->predictionApiOnline();
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'x', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        BackupVerification::query()->forceCreate(['backup_filename' => 'iclimate_2026-09-25_0315.backup', 'verified_at' => now()->subHour(), 'status' => BackupVerification::FAILED, 'failure_reason' => 'Unreadable']);
        \App\Models\ExternalWeatherData::query()->forceCreate(['source' => 'Open-Meteo', 'location_name' => 'Lian', 'latitude' => 14.0, 'longitude' => 120.6, 'forecast_date' => now()->toDateString(), 'fetched_at' => now()->subHours(5)]);

        $response = $this->actingAs($it)->get(route('admin.dashboard'));
        $data = $response->viewData('it');
        $rows = collect($data['services'])->keyBy('key');

        foreach (['jobs', 'backup', 'weather'] as $key) {
            $this->assertSame('Needs attention', $rows[$key]['label'], $key);
        }
        $this->assertStringContainsString('Unreadable', $rows['backup']['detail']);
        $this->assertSame(['Operational', 3], [$data['overall']['label'], $data['overall']['issues']]);      // the application and database are up
        $this->assertStringEndsWith('3 to review', $data['overall']['text']);
        $response->assertSee('data-service="jobs" data-level="warn"', false)->assertSee('Needs attention')->assertSee('Full diagnostics');
        foreach (['Critical', 'Warning'] as $harsh) {
            $this->assertStringNotContainsString($harsh, view('dashboards.it.health', ['it' => $data])->render(), $harsh);
        }
    }

    public function test_an_overdue_pipeline_is_marked_on_its_own_row(): void
    {
        $record = ClimateRecord::factory()->create();
        $record->forceFill(['updated_at' => now()->subDays(200)])->save();

        $it = $this->actingAs($this->it())->get(route('admin.dashboard'))->viewData('it');

        $this->assertSame('crit', collect($it['pipeline'])->firstWhere('key', 'climate')['level']);     // 200 days against a 45 day target
    }

    // ------------------------------------------------------------ scheduled task history

    public function test_scheduled_runs_are_stored_in_the_database_and_survive_a_cache_clear(): void
    {
        $task = Schedule::events() ?: (function () {
            \Illuminate\Support\Facades\Artisan::all();

            return Schedule::events();
        })();
        $task = $task[0];

        event(new ScheduledTaskStarting($task));
        $this->assertSame(ScheduledTaskRun::RUNNING, ScheduledTaskRun::query()->latest('id')->first()->status);
        event(new ScheduledTaskFinished($task, 1.234));
        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFailed($task, new \RuntimeException('Boom')));

        $runs = ScheduledTaskRun::query()->orderBy('id')->get();
        $this->assertCount(2, $runs);
        $this->assertSame([ScheduledTaskRun::SUCCEEDED, ScheduledTaskRun::FAILED], $runs->pluck('status')->all());
        $this->assertSame('Boom', $runs[1]->output_summary);
        $this->assertNotNull($runs[0]->started_at);
        $this->assertNotNull($runs[0]->finished_at);

        Cache::flush();                                                    // the history is not in the cache
        $row = app(ItDashboardInsights::class)->scheduledTasks()['rows'][0];
        $this->assertSame('failed', $row['status']);
        $this->assertNotNull($row['last']);
        $this->assertSame(['runs' => 2, 'failed' => 1], app(ItDashboardInsights::class)->taskRunStats());
    }

    // ------------------------------------------------------------ weather fetch logging

    public function test_a_failed_weather_fetch_is_logged_with_duration_and_a_scrubbed_error(): void
    {
        $result = app(OpenMeteoService::class)->fetchForecast(true);

        $this->assertFalse($result['ok']);
        $log = WeatherFetchLog::query()->first();
        $this->assertSame(WeatherFetchLog::FAILED, $log->status);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->duration_ms);
        $this->assertStringNotContainsString('http', (string) $log->error_message);
    }

    public function test_weather_fetch_stats_give_success_rate_average_time_and_failure_streak(): void
    {
        $at = now();
        foreach ([[WeatherFetchLog::SUCCESS, 200, 5], [WeatherFetchLog::SUCCESS, 400, 4], [WeatherFetchLog::FAILED, 900, 3], [WeatherFetchLog::FAILED, 1000, 2]] as [$status, $ms, $hoursAgo]) {
            WeatherFetchLog::record($at->copy()->subHours($hoursAgo), $ms, $status === WeatherFetchLog::SUCCESS, $status === WeatherFetchLog::FAILED ? 'Open-Meteo returned HTTP 500 https://api.example.test/x?lat=1' : null);
        }
        WeatherFetchLog::record($at->copy()->subDays(9), 5000, false, 'too old to count');

        $s = app(ItDashboardInsights::class)->weatherFetchStats();

        $this->assertSame(4, $s['total']);
        $this->assertSame(50.0, $s['rate']);
        $this->assertSame(625, $s['avg_ms']);
        $this->assertSame(2, $s['streak']);
        $this->assertStringContainsString('[url]', $s['last_error']);

        $html = $this->actingAs($this->it())->get(route('admin.dashboard'))->assertSee('Weather API reliability')->assertSee('2 in a row')->getContent();
        $this->assertStringContainsString('625', $html);
    }

    public function test_weather_fetch_stats_have_an_empty_state(): void
    {
        $this->assertSame(0, app(ItDashboardInsights::class)->weatherFetchStats()['total']);
        $this->actingAs($this->it())->get(route('admin.dashboard'))->assertSee('No weather fetch attempts have been logged yet');
    }

    // ------------------------------------------------------------ retry button

    public function test_retry_asks_for_confirmation_is_csrf_protected_and_is_written_to_the_system_log(): void
    {
        $it = $this->it();
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncThing']), 'exception' => 'x', 'failed_at' => now()]);

        $html = $this->actingAs($it)->get(route('admin.dashboard'))->getContent();
        // Asked in the shared confirmation pop-up (resources/js/info-modal.js).
        $this->assertStringContainsString('data-ic-confirm="Retry 1 failed job of class App\\Jobs\\SyncThing?', $html);
        $this->assertStringContainsString('name="_token"', $html);

        $this->actingAs($it)->post(route('admin.dashboard.retry-failed'), ['class' => 'App\\Jobs\\SyncThing'])->assertRedirect(route('admin.dashboard'));

        $log = SystemLog::query()->where('action', 'Retry Failed Jobs')->first();
        $this->assertNotNull($log);
        $this->assertSame($it->id, $log->user_id);
        $this->assertStringContainsString('SyncThing', $log->details);
        $this->assertStringContainsString($it->name, $log->details);
    }

    public function test_retry_is_post_only_and_closed_to_other_roles(): void
    {
        // (Laravel skips the CSRF check while testing, so the token is asserted on the rendered form above.)
        $this->actingAs($this->it())->get(route('admin.dashboard.retry-failed'))->assertStatus(405);      // never reachable by a plain link

        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->post(route('admin.dashboard.retry-failed'), ['class' => 'x'])->assertForbidden();
        $this->assertSame(0, SystemLog::query()->where('action', 'Retry Failed Jobs')->count());
    }

    public function test_task_labels_drop_quotes_log_redirects_and_background_wrappers(): void
    {
        $this->assertSame('iclimate:automation-run --force-weather', ItDashboardInsights::taskLabel("'C:\\php\\php.exe' 'artisan' iclimate:automation-run --force-weather >> \"C:\\x\\NUL\" 2>&1"));
        $this->assertSame('iclimate:backup-database', ItDashboardInsights::taskLabel("'php' 'artisan' iclimate:backup-database > 'NUL' 2>&1; 'php' 'artisan' schedule:finish \"x\""));
        $this->assertSame('iclimate:warm-dashboards', ItDashboardInsights::taskLabel("'php' 'artisan' iclimate:warm-dashboards"));
    }

    // ------------------------------------------------------------ environment

    public function test_the_app_logs_to_daily_files_and_stores_sessions_in_the_database(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=database$/m', $env);
        $this->assertMatchesRegularExpression('/^LOG_CHANNEL=daily$/m', $env);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('sessions'));
    }
}
