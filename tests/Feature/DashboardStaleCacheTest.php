<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Analytics\ItDashboardService;
use App\Support\StaleCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression for the /admin/dashboard 500: with ~0.4 s per Supabase round trip the IT overview (82 queries cold)
 * took longer than PHP's 30 s limit whenever its 60 s cache had lapsed. Expired dashboard data is now answered from
 * the last good copy and rebuilt after the response; only a first-ever (or too old) build happens while the page
 * waits, with an explicit time allowance.
 */
class DashboardStaleCacheTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private static array $events = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response([], 500)]);
        self::$events = [];
    }

    /** A page that reads one StaleCache value, recording whether the build ran before or after the response. */
    private function page(string $key, ?string $version = null, int $maxStale = StaleCache::MAX_STALE_SECONDS, bool $fail = false): string
    {
        Route::get('/_stale-probe', function () use ($key, $maxStale, $fail) {
            $value = StaleCache::remember($key, 60, function () use ($fail) {
                self::$events[] = 'build';
                if ($fail) {
                    throw new RuntimeException('provider down');
                }

                return 'built at '.now()->toTimeString();
            }, null, $maxStale);
            self::$events[] = 'response';

            return $value;
        })->middleware('web');

        return (string) $this->get('/_stale-probe')->assertOk()->getContent();
    }

    public function test_a_first_visit_builds_while_the_page_waits(): void
    {
        $this->page('probe');

        $this->assertSame(['build', 'response'], self::$events);
    }

    public function test_an_expired_value_is_answered_from_the_last_copy_and_rebuilt_after_the_response(): void
    {
        $first = $this->page('probe');
        self::$events = [];
        $this->travel(5)->minutes();                                  // the 60 s copy has lapsed

        $second = $this->page('probe');

        $this->assertSame($first, $second);                           // the last good copy
        $this->assertSame(['response', 'build'], self::$events);      // rebuilt only after answering
        $this->assertNotSame($first, Cache::get('probe'));            // and the fresh copy is now in place
    }

    public function test_a_last_copy_older_than_the_limit_is_rebuilt_while_the_page_waits(): void
    {
        $this->page('probe', maxStale: 600);
        self::$events = [];
        $this->travel(11)->minutes();

        $this->page('probe', maxStale: 600);

        $this->assertSame(['build', 'response'], self::$events);
    }

    public function test_a_rebuild_failing_after_the_response_keeps_the_last_copy_and_is_logged(): void
    {
        $first = $this->page('probe');
        $this->travel(5)->minutes();
        Log::spy();

        $this->assertSame($first, $this->page('probe', fail: true));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, 'could not be rebuilt') && $context['message'] === 'provider down')->once();
        $this->assertSame($first, Cache::get('probe:latest')['value']);
    }

    public function test_a_first_build_that_fails_is_not_hidden(): void
    {
        Route::get('/_stale-fail', fn () => StaleCache::remember('broken', 60, fn () => throw new RuntimeException('bug')))->middleware('web');

        $this->get('/_stale-fail')->assertStatus(500);
    }

    public function test_allow_time_restarts_the_clock_and_never_shortens_a_longer_limit(): void
    {
        $before = ini_get('max_execution_time');
        try {
            set_time_limit(30);
            StaleCache::allowTime(120);
            $this->assertSame('120', ini_get('max_execution_time'));

            set_time_limit(600);
            StaleCache::allowTime(120);
            $this->assertSame('600', ini_get('max_execution_time'));

            set_time_limit(0);
            StaleCache::allowTime(120);
            $this->assertSame('0', ini_get('max_execution_time'));    // unlimited stays unlimited
        } finally {
            set_time_limit((int) $before);
        }
    }

    public function test_the_it_dashboard_answers_from_its_last_copy_once_its_cache_lapsed(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $queries = 0;
        $answered = null;
        \Illuminate\Support\Facades\DB::listen(function () use (&$queries) { $queries++; });

        $this->actingAs($it)->get(route('admin.dashboard'))->assertOk();
        $coldBuild = $queries;
        $this->travel(5)->minutes();                                   // the 60 s page cache has lapsed

        $queries = 0;
        // Registered before the request, so it runs before StaleCache's after-response rebuild: the boundary.
        $this->app->terminating(function () use (&$queries, &$answered) { $answered ??= $queries; });
        $this->actingAs($it)->get(route('admin.dashboard'))->assertOk()->assertSee('System');

        $this->assertGreaterThan(30, $coldBuild, 'the cold build is the expensive one');
        $this->assertLessThan(10, $answered, 'answered from the last copy, not rebuilt while the page waits');
        $this->assertGreaterThan(30, $queries, 'the overview was rebuilt after the response');
    }

    public function test_after_a_data_change_a_versioned_value_is_rebuilt_not_shown_stale(): void
    {
        $key = fn () => 'versioned:v'.\App\Support\DashboardData::version();
        Route::get('/_stale-versioned', function () use ($key) {
            return StaleCache::remember($key(), 60, function () {
                self::$events[] = 'build';

                return 'value for v'.\App\Support\DashboardData::version();
            });
        })->middleware('web');

        $this->get('/_stale-versioned')->assertOk();
        \App\Support\DashboardData::bump();
        self::$events = [];

        $this->get('/_stale-versioned')->assertOk()->assertSee('value for v'.\App\Support\DashboardData::version());
        $this->assertSame(['build'], self::$events);
    }
}
