<?php

namespace Tests\Feature;

use App\Database\StartupSearchPathPostgresConnector;
use App\Models\FarmerOrganization;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Prediction\PredictionReadMode;
use App\Services\Security\CaptchaService;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Support\PageCache;
use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pages must stay fast, and must stay correct while being fast:
 * no outside calls or model training while a page loads, no per-row queries,
 * and caches that never show one person's data to another.
 */
class PagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        User::ROLE_FARMER => ['/farmer/dashboard', '/calendar', '/planting-advisories', '/farmer/my-farm', '/messages', '/notifications', '/weather-predictions'],
        User::ROLE_MAO => ['/mao/dashboard', '/reports', '/farmer-profiles', '/planting-advisories', '/weather-predictions', '/field-reports', '/calendar'],
        User::ROLE_IT_EXPERT => ['/admin/dashboard', '/users', '/system-logs', '/system-health', '/system-health/model-registry', '/model-evaluation', '/ai-admin/topics', '/reports'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.groq.enabled' => true, 'services.groq.key' => 'gsk_test_key']);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['role' => $role] + $attributes);

        if ($role === User::ROLE_FARMER) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $attributes['barangay'] ?? 'Bagong Pook']);
        }

        return $user;
    }

    /** @return array{int, int} [queries, milliseconds] spent on the database while $work runs */
    private function queries(callable $work): array
    {
        $count = 0;
        $listener = function ($query) use (&$count): void {
            $count++;
        };
        DB::listen($listener);
        $work();

        return [$count, 0];
    }

    private function countQueries(callable $work): int
    {
        return $this->queries($work)[0];
    }

    private function mockForestThatMustNotTrain(): MockInterface
    {
        return $this->mock(MonthlyWeatherRandomForest::class, function (MockInterface $mock): void {
            $mock->shouldReceive('datasetVersion')->andReturn('test-dataset');
            $mock->shouldNotReceive('predict');
        });
    }

    /** In production every request starts without a signed-in user object; a test process keeps one, so reset it. */
    private function newRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function captchaSession(): array
    {
        return [CaptchaService::SESSION_KEY => [
            'context' => 'login', 'question' => 'What is 3 + 4?', 'answer_hash' => Hash::make('7'), 'created_at' => now()->timestamp,
        ]];
    }

    // ------------------------------------------------------------------ login

    public function test_the_login_page_and_login_call_no_outside_service(): void
    {
        Http::fake();
        $this->mockForestThatMustNotTrain();
        $user = $this->user(User::ROLE_FARMER);

        $this->get('/login')->assertOk();
        $this->withSession($this->captchaSession())->post('/login', [
            'email' => $user->email, 'password' => 'password', 'captcha_answer' => '7',
        ])->assertRedirect();

        // No weather provider, no Groq, no Python service, no model.
        Http::assertNothingSent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_shows_stored_weather_without_calling_a_provider(): void
    {
        Http::fake();
        $service = app(FarmerDashboardWeatherService::class);
        $key = new ReflectionMethod($service, 'cacheKey');
        Cache::put($key->invoke($service).'.last_successful', [
            'success' => true, 'current' => ['temperature' => 27.4, 'humidity' => 80, 'wind_speed' => 9, 'condition' => 'Cloudy'],
            'today' => ['rainfall' => 1.2], 'fetched_at' => now()->toIso8601String(),
        ]);

        $this->get('/login')->assertOk()->assertSee('27.4');

        Http::assertNothingSent();
    }

    public function test_login_needs_only_a_handful_of_queries(): void
    {
        Http::fake();
        $user = $this->user(User::ROLE_FARMER);

        $queries = $this->countQueries(fn () => $this->withSession($this->captchaSession())->post('/login', [
            'email' => $user->email, 'password' => 'password', 'captcha_answer' => '7',
        ]));

        // Find the user, record the login, and little else. Each is a network round trip in production.
        $this->assertLessThanOrEqual(8, $queries, 'login must not do hidden work');
    }

    // -------------------------------------------- pages: no outside calls, no ML

    public function test_navigating_never_calls_groq_or_trains_a_model(): void
    {
        Queue::fake();
        Http::fake();
        $this->mockForestThatMustNotTrain();

        foreach (self::PAGES as $role => $pages) {
            $user = $this->user($role);

            foreach ($pages as $page) {
                $this->actingAs($user)->get($page)->assertOk();
            }
        }

        Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'groq'));
    }

    public function test_weather_provider_failures_do_not_block_or_break_navigation(): void
    {
        $attempts = 0;
        Http::fake(['*' => function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        }]);
        Queue::fake();
        $this->mockForestThatMustNotTrain();

        foreach ([User::ROLE_FARMER => '/farmer/dashboard', User::ROLE_MAO => '/mao/dashboard', User::ROLE_IT_EXPERT => '/admin/dashboard'] as $role => $page) {
            $started = microtime(true);
            $this->actingAs($this->user($role))->get($page)->assertOk();

            $this->assertLessThan(15, microtime(true) - $started, "{$page} must not wait on a dead provider");
        }

        $this->assertLessThan(40, $attempts, 'a failing provider is tried without retries, not repeatedly');
    }

    public function test_a_page_request_never_retries_a_failing_weather_provider(): void
    {
        $attempts = 0;
        Http::fake(['api.open-meteo.com/*' => function () use (&$attempts) {
            $attempts++;

            return Http::response(['reason' => 'overloaded'], 503);
        }]);

        Route::middleware('web')->get('/_probe/weather', fn () => response()->json(app(FarmerDashboardWeatherService::class)->current()));

        $this->get('/_probe/weather')->assertOk();

        $this->assertSame(0, $attempts, 'a page never calls the provider; the scheduled refresh does');
    }

    public function test_pages_and_live_widgets_use_stored_weather_and_only_an_explicit_refresh_calls_out(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $service = app(FarmerDashboardWeatherService::class);
        $key = (new ReflectionMethod($service, 'cacheKey'))->invoke($service);
        // A reading fetched 5 minutes ago; the normal 1-minute cache has long expired.
        Cache::put($key.'.last_successful', [
            'success' => true, 'current' => ['temperature' => 26.0], 'fetched_at' => now()->subMinutes(5)->toIso8601String(),
            'timestamps' => ['server_fetched_at' => now()->subMinutes(5)->toIso8601String()],
        ]);

        Route::middleware('web')->get('/_probe/weather-page', fn () => response()->json(['t' => app(FarmerDashboardWeatherService::class)->current()['current']['temperature'] ?? null]));

        // An HTML page reuses the stored reading and calls nobody...
        $this->get('/_probe/weather-page')->assertOk()->assertJsonPath('t', 26);
        Http::assertNothingSent();

        // ...so does a live widget polling in the background...
        $this->getJson('/_probe/weather-page')->assertOk()->assertJsonPath('t', 26);
        Http::assertNothingSent();

        // ...and only an explicit refresh (a Refresh button, or the scheduled job) asks the provider.
        app(FarmerDashboardWeatherService::class)->current(force: true);
        Http::assertSentCount(1);
    }

    public function test_a_page_never_computes_the_risk_map_it_reads_the_last_one_and_queues_a_refresh(): void
    {
        Queue::fake();
        Http::fake();
        $this->mockForestThatMustNotTrain();
        $this->storeCurrentWeather();

        // Two farmers' dashboards while nothing has ever been computed.
        $this->actingAs($this->user(User::ROLE_FARMER))->get('/farmer/dashboard')->assertOk();
        $this->actingAs($this->user(User::ROLE_FARMER, ['barangay' => 'Matabungkay']))->get('/farmer/dashboard')->assertOk();

        Http::assertNothingSent();
        Queue::assertPushed(\App\Jobs\RefreshBarangayRiskMap::class, 1);
    }

    /** A recent stored reading, so a dashboard has nothing to fetch (only the risk map could call a provider). */
    private function storeCurrentWeather(): void
    {
        $service = app(FarmerDashboardWeatherService::class);
        $key = (new ReflectionMethod($service, 'cacheKey'))->invoke($service);

        Cache::put($key.'.last_successful', [
            'success' => true, 'current' => ['temperature' => 27.0, 'humidity' => 80, 'wind_speed' => 9, 'condition' => 'Cloudy'],
            'today' => ['rainfall' => 1.0], 'forecast' => [], 'fetched_at' => now()->toIso8601String(),
            'timestamps' => ['server_fetched_at' => now()->toIso8601String()],
        ]);
    }

    public function test_read_only_prediction_mode_is_on_during_a_request_and_off_after(): void
    {
        Route::middleware('web')->get('/_probe/readonly', fn () => response()->json(['on' => app(PredictionReadMode::class)->active()]));

        $this->get('/_probe/readonly')->assertOk()->assertJsonPath('on', true);
        $this->assertFalse(app(PredictionReadMode::class)->active());
    }

    // ------------------------------------------------------------ query counts

    public function test_cached_dashboards_need_almost_no_queries_on_the_second_visit(): void
    {
        Queue::fake();
        Http::fake();

        foreach ([User::ROLE_FARMER => '/farmer/dashboard', User::ROLE_MAO => '/mao/dashboard', User::ROLE_IT_EXPERT => '/admin/dashboard', User::ROLE_IT_EXPERT.'-h' => '/system-health'] as $role => $page) {
            $user = $this->user(str_replace('-h', '', $role));

            $first = $this->countQueries(fn () => $this->actingAs($user)->get($page)->assertOk());
            $second = $this->countQueries(fn () => $this->actingAs($user)->get($page)->assertOk());

            $this->assertGreaterThan($second, $first, "{$page}: the first visit builds the page");
            $this->assertLessThanOrEqual(3, $second, "{$page}: a repeat visit must come from the prepared copy");
        }
    }

    public function test_the_scheduled_warm_up_makes_the_first_visit_a_cheap_one(): void
    {
        Http::fake();
        $mao = $this->user(User::ROLE_MAO);
        $it = $this->user(User::ROLE_IT_EXPERT);

        // Both scheduled warm-ups: the dashboards (iclimate:warm-dashboards) and System Health (iclimate:warm-dashboard-cache).
        $this->artisan('iclimate:warm-dashboards')->assertExitCode(0);
        $this->artisan('iclimate:warm-dashboard-cache')->assertExitCode(0);

        foreach ([[$mao, '/mao/dashboard'], [$it, '/admin/dashboard'], [$it, '/system-health']] as [$user, $page]) {
            $queries = $this->countQueries(fn () => $this->actingAs($user)->get($page)->assertOk());

            $this->assertLessThanOrEqual(3, $queries, "{$page}: the first visitor reads what the warm-up prepared");
        }
    }

    public function test_the_warm_up_is_scheduled_every_two_minutes(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression('/\*\/2\s+\*\s+\*\s+\*\s+\*\s+php artisan iclimate:warm-dashboard-cache/', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_shared_dashboards_are_built_once_for_everyone(): void
    {
        Http::fake();
        $first = $this->user(User::ROLE_MAO);
        $other = $this->user(User::ROLE_MAO); // created first: adding a user is itself a data change that rebuilds the page
        $this->actingAs($first)->get('/mao/dashboard')->assertOk();

        $queries = $this->countQueries(fn () => $this->actingAs($other)->get('/mao/dashboard')->assertOk());

        $this->assertLessThanOrEqual(3, $queries, 'a second officer reuses the copy the first one caused to be built');
    }

    public function test_a_list_page_does_not_query_once_per_row(): void
    {
        Http::fake();
        $mao = $this->user(User::ROLE_MAO);
        $organization = FarmerOrganization::query()->create(['name' => 'Test Farmers Group', 'barangay' => 'Bagong Pook', 'farm_type' => 'Irrigated']);

        // A full page (10 rows) against a page with twice as many candidate rows behind it: the page
        // shows 10 either way, so a per-row query would show up as a difference between the two.
        FarmerProfile::factory()->count(10)->create(['farmer_organization_id' => $organization->id]);
        // Warm-up: the controller instance is reused inside one test process, and its constructor's dropdown
        // lookups only run for the first request (in production every request boots fresh).
        $this->actingAs($mao)->get('/farmer-profiles')->assertOk();
        Cache::flush();
        $full = $this->countQueries(fn () => $this->actingAs($mao)->get('/farmer-profiles')->assertOk());

        FarmerProfile::factory()->count(10)->create(['farmer_organization_id' => $organization->id]);
        Cache::flush();
        $moreBehind = $this->countQueries(fn () => $this->actingAs($mao)->get('/farmer-profiles')->assertOk());

        $this->assertSame($full, $moreBehind);
        $this->assertLessThanOrEqual(12, $full, 'the whole page, its relations included, in a fixed handful of queries');
    }

    /** A page whose rows each need their own related record must load them together, not one at a time. */
    public function test_related_records_are_loaded_together_not_one_per_row(): void
    {
        Http::fake();
        $mao = $this->user(User::ROLE_MAO);
        $organization = FarmerOrganization::query()->create(['name' => 'Group A', 'barangay' => 'Bagong Pook', 'farm_type' => 'Irrigated']);
        FarmerProfile::factory()->count(10)->create(['farmer_organization_id' => $organization->id]);
        Cache::flush();

        $sql = [];
        DB::listen(function ($query) use (&$sql): void {
            $sql[] = $query->sql;
        });
        $this->actingAs($mao)->get('/farmer-profiles')->assertOk();

        $organizationLookups = array_filter($sql, fn (string $q): bool => str_contains($q, 'from "farmer_organizations" where "farmer_organizations"."id"'));
        $this->assertLessThanOrEqual(1, count($organizationLookups), 'one lookup for all rows, not one per row');
    }

    public function test_lists_are_paginated_and_only_the_requested_page_is_loaded(): void
    {
        Http::fake();
        $mao = $this->user(User::ROLE_MAO);
        FarmerProfile::factory()->count(53)->create(); // 53 profiles: pages of 25, 25 and 3

        $first = $this->actingAs($mao)->get('/farmer-profiles');
        $second = $this->actingAs($mao)->get('/farmer-profiles?page=3');

        $first->assertOk();
        $second->assertOk();
        $this->assertCount(25, $first->viewData('records')->items());
        $this->assertSame(25, $first->viewData('records')->perPage());
        $this->assertCount(3, $second->viewData('records')->items(), 'page 3 holds the last 3 of 53 profiles');
    }

    // ---------------------------------------------------------------- cache safety

    public function test_one_persons_cached_page_is_never_shown_to_another(): void
    {
        Http::fake();
        // Farmer Home greets each farmer by name and shows their own barangay: that is the per-person content under test.
        $alice = $this->user(User::ROLE_FARMER);
        $bob = $this->user(User::ROLE_FARMER);
        $alice->forceFill(['name' => 'Alicenonly Santos', 'barangay' => 'Prenza'])->save();
        $bob->forceFill(['name' => 'Bobonly Reyes', 'barangay' => 'Cumba'])->save();

        $this->actingAs($alice)->get('/farmer/dashboard')->assertOk()->assertSee('Alicenonly')->assertDontSee('Bobonly')->assertDontSee('Cumba, Lian');
        $this->actingAs($bob)->get('/farmer/dashboard')->assertOk()->assertSee('Bobonly')->assertDontSee('Alicenonly')->assertDontSee('Prenza, Lian');
        // and again, now that both copies exist
        $this->actingAs($alice)->get('/farmer/dashboard')->assertOk()->assertSee('Alicenonly')->assertDontSee('Bobonly')->assertDontSee('Cumba, Lian');
    }

    public function test_advisories_and_calendars_are_cached_per_barangay_and_person(): void
    {
        Http::fake();
        $here = $this->user(User::ROLE_FARMER, ['barangay' => 'Bagong Pook']);
        $there = $this->user(User::ROLE_FARMER, ['barangay' => 'Matabungkay']);
        PlantingAdvisory::factory()->create(['title' => 'ADVISORY-FOR-BAGONG-POOK', 'status' => 'published', 'target_scope' => 'barangay', 'target_barangay' => 'Bagong Pook', 'valid_from' => now()->subDay(), 'valid_until' => now()->addWeek()]);

        // The Calendar shows planning only (2026-10): a farmer's own dates there, never another farmer's.
        \App\Models\FarmerPlantingSchedule::query()->create(['user_id' => $here->id, 'planting_date' => now()->addDays(3)->toDateString(),
            'scheduled_harvest_date' => now()->addDays(110)->toDateString(), 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'PLAN-OF-BAGONG-POOK']);

        $this->actingAs($here)->get('/planting-advisories')->assertSee('ADVISORY-FOR-BAGONG-POOK');
        $this->actingAs($there)->get('/planting-advisories')->assertDontSee('ADVISORY-FOR-BAGONG-POOK');
        $this->actingAs($here)->get('/calendar')->assertSee('PLAN-OF-BAGONG-POOK')->assertDontSee('ADVISORY-FOR-BAGONG-POOK');
        $this->actingAs($there)->get('/calendar')->assertDontSee('PLAN-OF-BAGONG-POOK');
    }

    public function test_a_data_change_shows_on_the_very_next_visit(): void
    {
        Http::fake();
        $mao = $this->user(User::ROLE_MAO);
        $before = $this->actingAs($mao)->get('/mao/dashboard')->assertOk()->viewData('mao')['farmers'];

        // The MAO dashboard is prepared; new data must not wait for the copy to age out.
        \App\Models\FarmerProfile::factory()->count(3)->create();

        $after = $this->actingAs($mao)->get('/mao/dashboard')->assertOk()->viewData('mao')['farmers'];
        $this->assertSame(3, $after - $before);                             // "Registered farmers" (a plain count since 2026-10)
    }

    public function test_page_cache_scopes_never_mix(): void
    {
        $built = 0;
        $build = function () use (&$built) {
            return ++$built;
        };

        $sharedA = PageCache::shared('x', 60, $build);
        $sharedB = PageCache::shared('x', 60, $build);
        $aliceFirst = PageCache::forUser(1, 'x', 60, $build);
        $bobFirst = PageCache::forUser(2, 'x', 60, $build);
        $aliceAgain = PageCache::forUser(1, 'x', 60, $build);

        $this->assertSame($sharedA, $sharedB, 'shared is one entry');
        $this->assertNotSame($aliceFirst, $bobFirst, 'each person has their own entry');
        $this->assertSame($aliceFirst, $aliceAgain);
        $this->assertNotSame($sharedA, $aliceFirst, 'a private entry is not the shared one');
    }

    public function test_page_cache_invalidation_reaches_exactly_what_it_should(): void
    {
        $n = 0;
        $build = function () use (&$n) {
            return ++$n;
        };
        $shared = PageCache::shared('s', 60, $build);
        $alice = PageCache::forUser(1, 'u', 60, $build);
        $bob = PageCache::forUser(2, 'u', 60, $build);

        PageCache::flushUser(1);
        $this->assertNotSame($alice, PageCache::forUser(1, 'u', 60, $build), 'Alice is rebuilt');
        $this->assertSame($bob, PageCache::forUser(2, 'u', 60, $build), 'Bob is untouched');
        $this->assertSame($shared, PageCache::shared('s', 60, $build), 'shared is untouched by a private flush');

        PageCache::flushShared();
        $this->assertNotSame($shared, PageCache::shared('s', 60, $build), 'shared is rebuilt');
        $this->assertNotSame($bob, PageCache::forUser(2, 'u', 60, $build), 'private copies also depend on shared data');
    }

    public function test_a_refresh_replaces_one_entry_without_flushing_everything(): void
    {
        $n = 0;
        $build = function () use (&$n) {
            return ++$n;
        };
        $keep = PageCache::shared('keep', 60, $build);
        PageCache::shared('health', 60, $build);

        $fresh = PageCache::refreshShared('health', 60, $build);

        $this->assertSame($fresh, PageCache::shared('health', 60, $build), 'the refreshed value is what is served next');
        $this->assertSame($keep, PageCache::shared('keep', 60, $build), 'other entries are not invalidated');
    }

    public function test_a_page_build_that_writes_data_does_not_invalidate_itself(): void
    {
        $builds = 0;
        $build = function () use (&$builds) {
            $builds++;
            PageCache::flushShared(); // what a model save during rendering (a stored weather snapshot) does

            return $builds;
        };

        PageCache::shared('self', 60, $build);
        PageCache::shared('self', 60, $build);

        $this->assertSame(1, $builds, 'the page is built once, not rebuilt because of its own writes');
    }

    public function test_query_string_input_cannot_flood_the_cache_with_keys(): void
    {
        $request = \Illuminate\Http\Request::create('/x?page=2&junk1=a&junk2=b&type='.str_repeat('z', 500).'&status=ok');

        $vary = PageCache::queryVary($request, ['page', 'type', 'status']);

        $this->assertSame(['page' => '2', 'status' => 'ok'], $vary, 'unknown and oversized parameters never become part of a key');
    }

    // ------------------------------------------------------ signed-in user cache

    public function test_a_role_change_applies_on_the_very_next_request(): void
    {
        Http::fake();
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $user->id]);

        $this->withSession($this->captchaSession())->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha_answer' => '7']);
        $this->newRequest()->get('/admin/dashboard')->assertForbidden();
        $this->newRequest()->get('/farmer/dashboard')->assertOk(); // the cached user is now warm
        $this->assertNotNull(Cache::get('auth:user:'.$user->id));

        $user->update(['role' => User::ROLE_IT_EXPERT]);
        $this->assertNull(Cache::get('auth:user:'.$user->id), 'the change dropped the cached copy');

        $this->newRequest()->get('/admin/dashboard')->assertOk();
        $this->newRequest()->get('/farmer/dashboard')->assertForbidden();
    }

    public function test_a_deactivated_or_deleted_user_is_locked_out_at_once(): void
    {
        Http::fake();
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->withSession($this->captchaSession())->post('/login', ['email' => $user->email, 'password' => 'password', 'captcha_answer' => '7']);
        $this->newRequest()->get('/mao/dashboard')->assertOk();

        $user->delete();

        $this->newRequest()->get('/mao/dashboard')->assertRedirect(); // sent to log in: the cached copy did not survive the delete
    }

    public function test_the_cached_user_is_private_and_not_readable_in_the_cache(): void
    {
        $user = User::factory()->create(['email' => 'private.person@example.test']);
        auth()->guard()->getProvider()->retrieveById($user->id);

        $stored = Cache::get('auth:user:'.$user->id);

        $this->assertIsString($stored);
        $this->assertStringNotContainsString('private.person@example.test', $stored);
        $this->assertStringNotContainsString($user->password, $stored);
    }

    public function test_the_signed_in_user_costs_one_query_once_not_on_every_request(): void
    {
        $user = User::factory()->create();
        $provider = auth()->guard()->getProvider();

        $first = $this->countQueries(fn () => $provider->retrieveById($user->id));
        $second = $this->countQueries(fn () => $provider->retrieveById($user->id));

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    // ------------------------------------------------- RBAC with warm caches

    public function test_warm_caches_never_open_a_page_to_the_wrong_role(): void
    {
        Http::fake();
        $farmer = $this->user(User::ROLE_FARMER);
        $mao = $this->user(User::ROLE_MAO);
        $it = $this->user(User::ROLE_IT_EXPERT);

        // warm everything that is cached
        $this->actingAs($mao)->get('/mao/dashboard')->assertOk();
        $this->actingAs($it)->get('/admin/dashboard')->assertOk();
        $this->actingAs($it)->get('/system-health')->assertOk();
        $this->actingAs($it)->get('/ai-admin/topics')->assertOk();

        foreach (['/mao/dashboard', '/admin/dashboard', '/system-health', '/system-health/model-registry', '/ai-admin/topics', '/users', '/system-logs'] as $page) {
            $this->actingAs($farmer)->get($page)->assertForbidden();
        }

        foreach (['/admin/dashboard', '/system-health', '/users'] as $page) {
            $this->actingAs($mao)->get($page)->assertForbidden();
        }
    }

    // ------------------------------------------------------ system health page

    public function test_system_health_reuses_a_recent_check_and_refresh_runs_a_new_one(): void
    {
        Http::fake();
        $it = $this->user(User::ROLE_IT_EXPERT);

        $first = $this->countQueries(fn () => $this->actingAs($it)->get('/system-health')->assertOk());
        $repeat = $this->countQueries(fn () => $this->actingAs($it)->get('/system-health')->assertOk());
        $refresh = $this->countQueries(fn () => $this->actingAs($it)->get('/system-health?refresh=1')->assertOk());
        $afterRefresh = $this->countQueries(fn () => $this->actingAs($it)->get('/system-health')->assertOk());

        $this->assertLessThanOrEqual(2, $repeat);
        $this->assertGreaterThan($repeat + 4, $refresh, 'Refresh really runs the checks again');
        $this->assertLessThanOrEqual(2, $afterRefresh, 'and the next visitor reuses that new result');
    }

    // ------------------------------------------------------------ infrastructure

    public function test_schema_checks_share_one_catalog_query_and_a_migration_reads_it_again(): void
    {
        $first = $this->countQueries(fn () => SchemaCache::hasTable('users'));
        $tables = $this->countQueries(function (): void {
            SchemaCache::hasTable('users');
            SchemaCache::hasTable('planting_advisories');
            SchemaCache::hasTable('no_such_table');
            SchemaCache::hasTable('no_such_table');
        });
        $firstColumn = $this->countQueries(fn () => SchemaCache::hasColumn('users', 'email'));
        $columns = $this->countQueries(function (): void {
            SchemaCache::hasColumn('users', 'email');
            SchemaCache::hasColumn('users', 'no_such_column');
            SchemaCache::hasColumn('no_such_table', 'id');
        });

        $this->assertLessThanOrEqual(2, $first, 'one catalog read (SQLite adds a dbstat check; PostgreSQL is one query)');
        $this->assertSame(0, $tables, 'that list answers every other table, a missing one included');
        $this->assertLessThanOrEqual(2, $firstColumn, 'one column read per table (SQLite adds one more)');
        $this->assertSame(0, $columns, 'that list answers its other columns; a missing table needs no query');
        $this->assertTrue(SchemaCache::hasTable('USERS'));
        $this->assertFalse(SchemaCache::hasTable('no_such_table'));
        $this->assertTrue(SchemaCache::hasColumn('users', 'email'));
        $this->assertFalse(SchemaCache::hasColumn('users', 'no_such_column'));

        // A "no" is cached safely because every migration run starts a new version of the lists.
        \Illuminate\Support\Facades\Schema::create('no_such_table', fn ($table) => $table->id());
        $this->assertFalse(SchemaCache::hasTable('no_such_table'), 'until the next migration run');
        event(new \Illuminate\Database\Events\MigrationsEnded('up'));
        $this->assertTrue(SchemaCache::hasTable('no_such_table'), 'a migration run reads the catalog again');
        $this->assertSame(0, $this->countQueries(fn () => SchemaCache::hasTable('no_such_table')));
    }

    public function test_persistent_connections_are_opt_in_and_the_schema_is_sent_at_connect_time(): void
    {
        $config = config('database.connections.pgsql');

        // Off unless DB_PERSISTENT=true (one authoritative pgsql options definition; see config/database.php).
        $this->assertFalse($config['options'][\PDO::ATTR_PERSISTENT] ?? false, 'persistent connections are opt-in');

        $this->assertTrue($config['startup_search_path']);
        $this->assertInstanceOf(StartupSearchPathPostgresConnector::class, app('db.connector.pgsql'));
    }

    public function test_postgres_sends_each_query_in_one_round_trip_unless_switched_off(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not installed.');
        }

        $disablePrepares = class_exists(\Pdo\Pgsql::class) ? \Pdo\Pgsql::ATTR_DISABLE_PREPARES : \PDO::PGSQL_ATTR_DISABLE_PREPARES;
        $options = fn (): array => (require config_path('database.php'))['connections']['pgsql']['options'];

        // Default: values go as real parameters in one round trip (not emulated prepares, which inline them).
        $default = config('database.connections.pgsql.options');
        $this->assertTrue($default[$disablePrepares] ?? false, 'prepares are disabled by default');
        $this->assertArrayNotHasKey(\PDO::ATTR_EMULATE_PREPARES, $default, 'values are never pasted into the SQL text');
        $this->assertArrayNotHasKey(\PDO::ATTR_PERSISTENT, $default, 'persistent connections stay opt-in');

        $_SERVER['DB_PGSQL_DISABLE_PREPARES'] = 'false';
        try {
            $off = $options();
        } finally {
            unset($_SERVER['DB_PGSQL_DISABLE_PREPARES']);
        }
        $this->assertSame([\PDO::ATTR_TIMEOUT => 3], $off, 'switched off: Laravel\'s own prepare + execute is used');

        $_SERVER['DB_PERSISTENT'] = 'true';
        try {
            $both = $options();
        } finally {
            unset($_SERVER['DB_PERSISTENT']);
        }
        $this->assertSame([\PDO::ATTR_TIMEOUT => 3, $disablePrepares => true, \PDO::ATTR_PERSISTENT => true], $both, 'one options list holds every setting');
    }

    public function test_an_unreachable_database_is_given_up_on_well_inside_the_request_time_limit(): void
    {
        $connections = fn (): array => (require config_path('database.php'))['connections'];

        // The wait is a PDO option on purpose: pdo_pgsql ignores a connect_timeout written in the DSN and waits its
        // default 30 s, which is PHP's whole request time limit (the request then died with a fatal error).
        $drivers = array_keys(array_filter(['pgsql' => extension_loaded('pdo_pgsql'), 'mysql' => extension_loaded('pdo_mysql'), 'mariadb' => extension_loaded('pdo_mysql')]));
        if ($drivers === []) {
            $this->markTestSkipped('Neither pdo_pgsql nor pdo_mysql is installed.');
        }

        foreach ($drivers as $name) {
            $this->assertSame(3, $connections()[$name]['options'][\PDO::ATTR_TIMEOUT] ?? null, $name.' gives up after 3 seconds by default');
        }

        // A zero or empty setting must not bring the endless wait back.
        $_SERVER['DB_CONNECT_TIMEOUT'] = '0';
        try {
            foreach ($drivers as $name) {
                $this->assertSame(1, $connections()[$name]['options'][\PDO::ATTR_TIMEOUT] ?? null, $name.' always has a wait limit');
            }
        } finally {
            unset($_SERVER['DB_CONNECT_TIMEOUT']);
        }
    }

    public function test_one_failed_connect_answers_for_the_rest_of_the_request(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('pdo_pgsql is not installed.');
        }

        // Nothing listens on port 1: the connect is refused at once, so the test needs no network.
        $config = ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'postgres', 'username' => 'u', 'password' => 'p',
            'options' => [\PDO::ATTR_TIMEOUT => 1]];
        $connector = new StartupSearchPathPostgresConnector;
        $fail = function () use ($connector, $config): \PDOException {
            try {
                $connector->connect($config);
            } catch (\PDOException $exception) {
                return $exception;
            }
            $this->fail('The connect was expected to fail.');
        };

        StartupSearchPathPostgresConnector::forgetUnreachable();
        try {
            $first = $fail();
            $second = $fail();

            $this->assertNull($first->getPrevious(), 'the first call really tried to connect');
            $this->assertSame($first, $second->getPrevious(), 'the second call was answered without connecting again');
            $this->assertSame($first->getMessage(), $second->getMessage());

            // Another server is still tried.
            $this->assertNull((function () use ($connector, $config) {
                try {
                    $connector->connect(['port' => 2] + $config);
                } catch (\PDOException $exception) {
                    return $exception->getPrevious();
                }
            })());
        } finally {
            StartupSearchPathPostgresConnector::forgetUnreachable();
        }
    }

    public function test_the_connector_sends_the_schema_as_a_startup_option_only_when_it_is_safe(): void
    {
        $dsn = new ReflectionMethod(StartupSearchPathPostgresConnector::class, 'getDsn');
        $connector = new StartupSearchPathPostgresConnector;
        $base = ['host' => 'db.example', 'port' => 5432, 'database' => 'postgres', 'sslmode' => 'require'];

        $on = $dsn->invoke($connector, $base + ['search_path' => 'iclimate_laravel', 'startup_search_path' => true]);
        $off = $dsn->invoke($connector, $base + ['search_path' => 'iclimate_laravel', 'startup_search_path' => false]);
        $odd = $dsn->invoke($connector, $base + ['search_path' => 'weird schema; drop', 'startup_search_path' => true]);
        $multi = $dsn->invoke($connector, $base + ['search_path' => 'iclimate_laravel,public', 'startup_search_path' => true]);

        $this->assertStringContainsString("options='-c search_path=iclimate_laravel'", $on);
        $this->assertStringNotContainsString('options=', $off, 'switched off: the normal SET statement is used');
        $this->assertStringNotContainsString('options=', $odd, 'a schema name that is not a plain identifier is never put in the DSN');
        $this->assertStringContainsString('search_path=iclimate_laravel,public', $multi);
    }

    public function test_environment_settings_are_read_through_config_so_config_cache_is_safe(): void
    {
        foreach (glob(app_path('**/*.php')) ?: [] as $file) {
            // (glob without GLOB_BRACE is shallow; the recursive scan below is the real check)
        }

        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // env() outside config files returns null once `php artisan config:cache` is on.
            if (preg_match('/(?<![>:\w])env\(\s*[\'"]/', file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'env() must only be used in config/ files');
    }
}
