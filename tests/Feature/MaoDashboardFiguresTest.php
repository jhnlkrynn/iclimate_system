<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\Notification;
use App\Models\PlantingAdvisory;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Mao\MaoMissingDataNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MAO Dashboard – Naicel (2026-10-08): the dashboard is in English unless the MAO picks Filipino with its selector,
 * and it carries four more figures (farmers with a planting plan, harvests due, rice production, advisories in
 * effect) and one more chart (registered farmers by barangay), all counted from saved records.
 */
class MaoDashboardFiguresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));   // Wet season 2026 (May–October)
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function mao(array $attributes = []): User
    {
        return User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE] + $attributes);
    }

    private function farmer(string $barangay): User
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay, 'status' => User::STATUS_ACTIVE]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);

        return $user;
    }

    private function plan(User $farmer, string $planted, string $harvest, float $area, array $more = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($more + ['user_id' => $farmer->id, 'planting_date' => $planted, 'scheduled_harvest_date' => $harvest,
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => $farmer->barangay, 'farm_area_hectares' => $area]);
    }

    private function html(User $as): string
    {
        Cache::flush();

        return $this->actingAs($as)->get(route('mao.dashboard'))->assertOk()->getContent();
    }

    /** The visible words of one KPI tile. */
    private function tile(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('#<a class="mhd-tile"[^>]*data-kpi="'.$key.'">(.*?)</a>#s', $html, $m), "tile {$key}");

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $m[1]))));
    }

    // ------------------------------------------------------------ the four new figures

    public function test_farmers_with_a_planting_plan_counts_each_farmer_once_for_this_season_only(): void
    {
        [$a, $b, $c] = [$this->farmer('Prenza'), $this->farmer('Cumba'), $this->farmer('Kapito')];
        $this->farmer('Kapito');                                               // a fourth registered farmer, no plan
        $this->plan($a, '2026-06-10', '2026-09-28', 1.5);
        $this->plan($a, '2026-07-01', '2026-10-20', 0.5);                      // the same farmer's second plan: still one farmer
        $this->plan($b, '2026-10-01', '2027-01-20', 2.0);
        $this->plan($c, '2026-03-01', '2026-06-20', 3.0);                      // last Dry season: not this season

        $tile = $this->tile($this->html($this->mao()), 'plans');

        $this->assertStringStartsWith('Farmers with a planting plan 2 of 4', $tile);
        $this->assertStringContainsString('50% of registered farmers · Wet season 2026', $tile);
    }

    public function test_harvests_due_counts_unharvested_plans_whose_harvest_date_is_in_the_next_30_days(): void
    {
        $farmer = $this->farmer('Prenza');
        $this->plan($farmer, '2026-06-20', '2026-10-10', 1.5);                 // in 4 days
        $this->plan($farmer, '2026-07-10', '2026-11-05', 2.25);                // in 30 days (the last day counted)
        $this->plan($farmer, '2026-07-20', '2026-11-06', 9.0);                 // in 31 days
        $this->plan($farmer, '2026-06-01', '2026-10-01', 9.0);                 // already past
        $this->plan($farmer, '2026-06-15', '2026-10-12', 9.0, ['actual_yield' => 4.1, 'harvested_on' => '2026-10-04']);   // already harvested

        $tile = $this->tile($this->html($this->mao()), 'harvests');

        $this->assertStringStartsWith('Harvests due in 30 days 2 plans', $tile);
        $this->assertStringContainsString('3.8 ha to be harvested', $tile);
    }

    public function test_rice_production_is_the_latest_year_on_record_with_tons_over_hectares(): void
    {
        $record = fn (int $year, string $barangay, float $area, float $tons) => RiceProduction::query()->create(['barangay' => $barangay, 'season' => 'Wet', 'irrigation_type' => 'Irrigated',
            'yield_per_hectare' => round($tons / $area, 2), 'area_hectares' => $area, 'total_production' => $tons, 'year' => $year, 'production_month' => $year.'-09-01', 'remarks' => 'Barangay harvest report']);
        $record(2025, 'Prenza', 50, 150);                                      // an older year is not added in
        $record(2026, 'Prenza', 10, 42);
        $record(2026, 'Cumba', 30, 108);

        $html = $this->html($this->mao());
        $tile = $this->tile($html, 'production');

        // 42 + 108 = 150 tons over 10 + 30 = 40 ha = 3.75 t/ha (not the plain average of 4.20 and 3.60).
        $this->assertStringStartsWith('Rice production (2026) 150.0 tons', $tile);
        $this->assertStringContainsString('40.0 ha · 3.75 t/ha · 2 of 19 barangays reported', $tile);
        $this->assertStringContainsString('<a class="mhd-tile" href="'.route('rice-productions.index').'" data-kpi="production">', $html);
    }

    public function test_advisories_in_effect_counts_published_advisories_that_have_not_expired(): void
    {
        $mao = $this->mao();
        $advisory = fn (string $status, $until) => PlantingAdvisory::query()->create(['title' => 'A', 'content' => 'M', 'message' => 'M', 'type' => 'Planting', 'advisory_type' => 'planting',
            'severity' => 'low', 'target_scope' => 'municipality', 'status' => $status, 'valid_from' => now()->subDays(3), 'valid_until' => $until, 'posted_by' => $mao->id]);
        $advisory('published', now()->addWeek());
        $advisory('published', now()->addDay());
        $advisory('published', now()->subDay());                               // expired
        $advisory('pending_review', now()->addWeek());                         // not published yet

        $this->assertStringStartsWith('Advisories in effect 2 Published and not expired', $this->tile($this->html($mao), 'advisories'));

        PlantingAdvisory::query()->delete();
        $this->assertStringStartsWith('Advisories in effect 0 No advisory is in effect today', $this->tile($this->html($mao), 'advisories'));
    }

    public function test_the_registered_farmers_chart_has_one_bar_per_barangay_most_first(): void
    {
        foreach (['Prenza', 'Cumba', 'Prenza', 'Kapito', 'Prenza', 'Cumba'] as $barangay) {
            $this->farmer($barangay);
        }
        $html = $this->html($this->mao());

        $this->assertSame(1, preg_match('#<canvas id="maoFarmersChart" data-chart="([^"]+)"#', $html, $m));
        $chart = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(['Prenza', 'Cumba', 'Kapito'], $chart['labels']);
        $this->assertSame([3, 2, 1], $chart['values']);
        $this->assertStringContainsString('6 registered farmers with a barangay on file.', $html);
        $this->assertStringStartsWith('Registered farmers 6', $this->tile($html, 'farmers'));   // the same count as the first tile
    }

    // ------------------------------------------------------------ English by default, Filipino when picked

    public function test_the_dashboard_is_in_english_until_the_mao_picks_filipino(): void
    {
        $mao = $this->mao();
        $english = $this->html($mao);

        foreach (['MAO Dashboard', 'Needs Action', 'Registered farmers', 'Advisories to approve', 'Rice Yield by Year 2020–2025'] as $words) {
            $this->assertStringContainsString($words, $english, $words);
        }
        $this->assertStringNotContainsString('Kailangan ng Aksyon', $english);
        $this->assertMatchesRegularExpression('#<option value="en" lang="en"\s+selected\s*>English</option>#', $english);

        $this->actingAs($mao)->post(route('mao.dashboard.language'), ['language' => 'fil'])->assertRedirect(route('mao.dashboard'));
        $this->assertSame('fil', $mao->fresh()->preferred_language);

        $filipino = $this->html($mao->fresh());
        foreach (['Dashboard ng MAO', 'Kailangan ng Aksyon', 'Mga rehistradong magsasaka', 'Mga advisory na aaprubahan', 'Ani ng Palay Bawat Taon 2020–2025', 'Tag-ulan 2026'] as $words) {
            $this->assertStringContainsString($words, $filipino, $words);
        }
        $this->assertStringNotContainsString('Needs Action', $filipino);
        $this->assertMatchesRegularExpression('#<option value="fil" lang="fil"\s+selected\s*>Filipino \(Tagalog\)</option>#', $filipino);
        // The menu follows: "Mga Magsasaka" instead of "Farmers".
        $this->assertStringContainsString('<span class="sidebar-label">Mga Magsasaka</span>', $filipino);

        // The figures are the same in both languages, and another MAO account still reads English.
        $this->assertSame($this->numbers($english), $this->numbers($filipino));
        $this->assertStringContainsString('Needs Action', $this->html($this->mao()));

        // Back to English.
        $this->actingAs($mao)->post(route('mao.dashboard.language'), ['language' => 'en'])->assertRedirect(route('mao.dashboard'));
        $this->assertStringContainsString('Needs Action', $this->html($mao->fresh()));
    }

    /** @return array<int, string> every KPI tile's key and value, in page order */
    private function numbers(string $html): array
    {
        preg_match_all('#data-kpi="([a-z]+)">.*?<span data-kpi-value>([^<]*)</span>#s', $html, $m);

        return array_map(fn (string $key, string $value): string => $key.'='.$value, $m[1], $m[2]);
    }

    public function test_picking_a_language_rewrites_the_bell_notifications_and_only_mao_may_pick(): void
    {
        $mao = $this->mao();
        app(MaoMissingDataNotifier::class)->syncAll();
        $title = fn (): string => (string) Notification::query()->where('user_id', $mao->id)->where('category', 'mao-setup:climate_month')->value('title');
        $this->assertSame('No climate records this month – add one', $title());

        $this->actingAs($mao)->post(route('mao.dashboard.language'), ['language' => 'fil'])->assertRedirect();
        $this->assertSame('Walang climate records ngayong buwan – magdagdag', $title());

        // An unknown language is refused; farmers and IT cannot use the MAO selector.
        $this->actingAs($mao)->post(route('mao.dashboard.language'), ['language' => 'xx'])->assertSessionHasErrors('language');
        $this->actingAs(User::factory()->farmer()->create())->post(route('mao.dashboard.language'), ['language' => 'fil'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IT_EXPERT]))->post(route('mao.dashboard.language'), ['language' => 'fil'])->assertForbidden();
    }

    public function test_pages_shared_with_farmers_stay_in_english_for_a_mao_who_reads_filipino(): void
    {
        // The farmer-language step of those pages (Heat Map, Weather Analysis) applies a farmer's choice only.
        $locale = function (User $user): string {
            app()->setLocale('en');
            $request = \Illuminate\Http\Request::create('/heat-map');
            $request->setUserResolver(fn () => $user);
            (new \App\Http\Middleware\SetFarmerLanguage)->handle($request, fn () => response('ok'));

            return app()->getLocale();
        };

        $this->assertSame('en', $locale($this->mao(['preferred_language' => 'fil'])));
        $this->assertSame('fil', $locale(User::factory()->farmer()->create(['preferred_language' => 'fil'])));
        app()->setLocale('en');
    }

    public function test_english_and_filipino_have_the_same_mao_words(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot(require lang_path($locale.'/mao.php')));
        $en = $keys('en');
        $fil = $keys('fil');
        sort($en);
        sort($fil);

        $this->assertSame($en, $fil);
    }
}
