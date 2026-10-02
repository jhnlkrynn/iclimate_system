<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use App\Support\SystemLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemLogsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function admin(string $name = 'IT Personnel'): User
    {
        return User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'name' => $name]);
    }

    private function log(?User $user, string $action, ?array $details = [], ?string $at = null): SystemLog
    {
        $log = SystemLog::create(['user_id' => $user?->id, 'action' => $action, 'details' => $details === null ? null : json_encode($details)]);
        if ($at) {
            $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }

        return $log;
    }

    private function page(User $as, array $query = [])
    {
        Cache::flush();

        return $this->actingAs($as)->get(route('system-logs.index', $query))->assertOk();
    }

    public function test_empty_logs_show_zero_cards_and_friendly_message(): void
    {
        $res = $this->page($this->admin());

        $res->assertViewHas('summary', fn ($s) => $s['total'] === 0 && $s['today'] === 0 && $s['logins_today'] === 0 && $s['recent_users'] === 0);
        $res->assertSee('No activity has been recorded yet.');
        $res->assertSee('No activity recorded yet');
    }

    public function test_single_log_renders_readable_row_and_keeps_technical_data_for_modal(): void
    {
        $admin = $this->admin();
        $this->log($admin, 'User Login', ['user' => 'it@gmail.com', 'role' => 'IT Expert', 'method' => 'POST', 'path' => 'login', 'ip' => '127.0.0.1']);

        $html = $this->page($admin)->getContent();
        $table = explode('</table>', explode('<table', $html)[1])[0];

        $this->assertStringContainsString('Logged In', $table);
        $this->assertStringContainsString('IT Personnel', $table);
        $this->assertStringNotContainsString('127.0.0.1', $table);
        $this->assertStringNotContainsString('&quot;method&quot;', $table);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Technical Details', $html);
        $this->assertStringContainsString('127.0.0.1', $html);
        $this->assertStringContainsString('data-sl-view="0"', $html);
    }

    public function test_status_is_recorded_unless_log_explicitly_says_failed(): void
    {
        $admin = $this->admin();
        $ok = $this->log($admin, 'User Login');
        $failed = $this->log($admin, 'Login Failed');
        $ctx = $this->log($admin, 'Updated Thing', ['context' => ['result' => 'denied']]);
        $rejected = $this->log($admin, 'Rejected Advisory', ['context' => ['record_type' => 'Advisory']]);

        $this->assertSame('Recorded', SystemLogPresenter::present($ok->load('user'))['status']);
        $this->assertSame('Failed', SystemLogPresenter::present($failed)['status']);
        $this->assertSame('Failed', SystemLogPresenter::present($ctx)['status']);
        $this->assertSame('Recorded', SystemLogPresenter::present($rejected)['status']);
        $this->assertStringNotContainsString('Success', $this->page($admin)->getContent());
    }

    public function test_role_uses_stored_role_else_current_role_with_note(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO, 'name' => 'Maria']);
        $stored = SystemLogPresenter::present($this->log($user, 'Updated Thing', ['role' => 'Farmer'])->load('user'));
        $fallback = SystemLogPresenter::present($this->log($user, 'User Logout', ['ip' => '1.1.1.1'])->load('user'));

        $this->assertSame('Farmer', $stored['role']);
        $this->assertStringContainsString('recorded in the log', $stored['technical']['Role source']);
        $this->assertSame('MAO Personnel', $fallback['role']);
        $this->assertSame("Role shown is the user's current role; historical role was not stored in this log.", $fallback['technical']['Role source']);
    }

    public function test_login_today_counts_both_spellings_once_each_and_ignores_old_or_other(): void
    {
        $admin = $this->admin();
        $this->log($admin, 'User Login');
        $this->log($admin, 'login');
        $this->log($admin, 'User Logout');
        $this->log($admin, 'User Login', [], now()->subDays(3)->toDateTimeString());

        $this->page($admin)->assertViewHas('summary', fn ($s) => $s['total'] === 4 && $s['today'] === 3 && $s['logins_today'] === 2);
    }

    public function test_recent_users_counts_distinct_users_in_last_7_days(): void
    {
        $a = $this->admin('Ana');
        $b = $this->admin('Ben');
        $c = $this->admin('Old Carl');
        $this->log($a, 'User Login');
        $this->log($a, 'User Logout');
        $this->log($b, 'User Login', [], now()->subDays(6)->toDateTimeString());
        $this->log($c, 'User Login', [], now()->subDays(9)->toDateTimeString());
        $this->log(null, 'Queued Python report analytics');

        $this->page($a)->assertViewHas('summary', fn ($s) => $s['recent_users'] === 2 && $s['recent_names'] === ['Ana', 'Ben']);
    }

    public function test_summary_is_not_cached_and_matches_the_table(): void
    {
        $admin = $this->admin();
        $this->log($admin, 'User Login');
        $this->actingAs($admin)->get(route('system-logs.index'))->assertViewHas('summary', fn ($s) => $s['total'] === 1);

        $this->log($admin, 'User Logout');
        $this->actingAs($admin)->get(route('system-logs.index'))->assertViewHas('summary', fn ($s) => $s['total'] === 2);
    }

    public function test_unknown_actions_are_readable_and_original_is_preserved(): void
    {
        $admin = $this->admin();
        $p = SystemLogPresenter::present($this->log($admin, 'generate_report')->load('user'));

        $this->assertSame('Generate Report', $p['action']);
        $this->assertSame('generate_report', $p['technical']['Original action']);
    }

    public function test_action_filter_groups_login_spellings_and_search_matches_displayed_label(): void
    {
        $admin = $this->admin();
        $this->log($admin, 'User Login');
        $this->log($admin, 'login');
        $this->log($admin, 'User Logout');

        $this->page($admin)->assertViewHas('actions', fn ($a) => $a === ['Logged In', 'Logged Out']);
        $this->page($admin, ['action' => 'Logged In'])->assertViewHas('rows', fn ($r) => $r->count() === 2);
        $this->page($admin, ['search' => 'logged out'])->assertViewHas('rows', fn ($r) => $r->count() === 1 && $r->first()['action'] === 'Logged Out');
    }

    public function test_search_user_role_and_date_filters(): void
    {
        $it = $this->admin('Ivan');
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'name' => 'Marisol']);
        $this->log($it, 'User Login', ['role' => 'IT Expert']);
        $this->log($mao, 'User Login', ['role' => 'MAO Personnel']);
        $this->log($mao, 'Updated Advisory', [], now()->subDays(2)->toDateTimeString());

        $this->page($it, ['search' => 'Marisol'])->assertViewHas('rows', fn ($r) => $r->count() === 2);
        $this->page($it, ['user' => (string) $it->id])->assertViewHas('rows', fn ($r) => $r->count() === 1);
        $this->page($it, ['role' => 'MAO Personnel'])->assertViewHas('rows', fn ($r) => $r->count() === 2);
        $this->page($it, ['role' => 'IT Expert'])->assertViewHas('rows', fn ($r) => $r->count() === 1);
        $this->page($it, ['date' => now()->toDateString()])->assertViewHas('rows', fn ($r) => $r->count() === 2);
        $this->page($it, ['date' => now()->subDays(2)->toDateString()])->assertViewHas('rows', fn ($r) => $r->count() === 1);
        $this->page($it, ['date' => 'not-a-date'])->assertViewHas('rows', fn ($r) => $r->count() === 3);
        $this->page($it, ['search' => 'zzz-nothing'])->assertSee('No activity matches your filters.');
    }

    public function test_pagination_is_15_per_page(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 20; $i++) {
            $this->log($admin, 'Updated Thing');
        }

        $this->page($admin)->assertViewHas('rows', fn ($r) => $r->count() === 15)->assertSee('Showing 1–15 of 20 activities');
        $this->page($admin, ['page' => 2])->assertViewHas('rows', fn ($r) => $r->count() === 5);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($farmer)->get(route('system-logs.index'))->assertForbidden();
    }
}
