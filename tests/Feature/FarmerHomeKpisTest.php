<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Services\Farmer\FarmerHomeKpis;
use App\Support\ProjectYieldDataset;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Farmer – Naicel (2026-10-08): Farmer Home's key figures (farm size, days to harvest, expected harvest in tons, rainy
 * days ahead) and the yield comparison. Each figure comes from the farmer's saved records or from a part the page
 * already shows; one that cannot be worked out is left out, never shown as zero.
 */
class FarmerHomeKpisTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $this->travelTo($this->now = CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));
        // Lian's usual yield: 4.00 t/ha in every month on record.
        $rows = array_map(fn ($m) => ['year' => 2024, 'month' => $m, 'yield' => 4.0, 'split' => null], range(1, 12));
        $this->app->instance(ProjectYieldDataset::class, new class($rows) extends ProjectYieldDataset
        {
            public function __construct(private array $custom) {}

            public function available(): bool
            {
                return true;
            }

            public function rows(): Collection
            {
                return collect($this->custom);
            }
        });
    }

    private function farmer(): User
    {
        return User::factory()->farmer()->create(['barangay' => 'Prenza', 'status' => User::STATUS_ACTIVE]);
    }

    private function farm(User $farmer, array $attributes = []): Farm
    {
        return Farm::query()->create($attributes + ['user_id' => $farmer->id, 'farm_name' => 'North Farm', 'barangay' => 'Prenza',
            'farm_size_hectares' => 1.5, 'farm_size_method' => Farm::SIZE_EXACT, 'farm_type' => 'Irrigated', 'is_primary' => true]);
    }

    private function plan(User $farmer, string $planted, ?string $harvest, array $more = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($more + ['user_id' => $farmer->id, 'planting_date' => $planted, 'scheduled_harvest_date' => $harvest,
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => 1.5]);
    }

    /** @param  array<int, ?string>  $rain  one rain word key per forecast day */
    private function week(array $rain): array
    {
        return ['state' => 'ok', 'days' => array_map(fn (?string $key): array => ['rain_key' => $key], $rain)];
    }

    /** @return array<string, array<string, mixed>> the tiles by key */
    private function tiles(User $farmer, ?Farm $farm, array $harvest = ['state' => 'nodata'], array $week = ['state' => 'nodata', 'days' => []]): array
    {
        return collect(app(FarmerHomeKpis::class)->for($farmer, $farm, null, $harvest, $week, $this->now)['tiles'])->keyBy('key')->all();
    }

    // ------------------------------------------------------------ the four figures

    public function test_farm_size_is_the_known_size_or_the_estimate_the_farmer_picked_never_a_made_up_number(): void
    {
        $farmer = $this->farmer();

        $exact = $this->tiles($farmer, $this->farm($farmer))['farm'];
        $this->assertSame(['1.5', 'ha', 'Irrigated'], [$exact['value'], $exact['unit'], $exact['note']]);

        $estimated = $this->tiles($farmer, $this->farm($farmer, ['farm_size_hectares' => null, 'farm_size_method' => Farm::SIZE_ESTIMATED, 'farm_size_estimate_range' => '1_2', 'farm_type' => 'Rainfed', 'is_primary' => false]))['farm'];
        $this->assertStringContainsString('1', $estimated['value']);
        $this->assertNull($estimated['unit']);
        $this->assertStringStartsWith('Estimated', $estimated['note']);

        // No size at all: no tile (the bell asks for it), and no expected-harvest total either.
        $unknown = $this->farm($farmer, ['farm_size_hectares' => null, 'farm_size_method' => Farm::SIZE_UNKNOWN, 'is_primary' => false]);
        $this->assertSame([], array_keys($this->tiles($farmer, $unknown, ['state' => 'ok', 'yield' => 4.2])));
    }

    public function test_days_to_harvest_comes_from_the_current_planting_plan_with_how_far_the_crop_is(): void
    {
        $farmer = $this->farmer();
        $this->plan($farmer, '2026-03-01', '2026-06-20', ['actual_yield' => 4.1, 'harvested_on' => '2026-06-22']);   // harvested: not the current crop
        $this->plan($farmer, '2026-07-28', '2026-11-05');                      // planted 70 days ago, harvest in 30 days (100 in all)

        $crop = $this->tiles($farmer, null)['crop'];

        $this->assertSame(['Days to harvest', '30', 'days', 'Harvest around Nov 5', 70], [$crop['label'], $crop['value'], $crop['unit'], $crop['note'], $crop['progress']]);
    }

    public function test_a_plan_not_planted_yet_counts_down_to_planting_and_an_old_unrecorded_one_is_dropped(): void
    {
        $farmer = $this->farmer();
        $upcoming = $this->plan($farmer, '2026-10-20', '2027-02-05');
        $crop = $this->tiles($farmer, null)['crop'];
        $this->assertSame(['Days to planting', '14', 'days', 'Planting on Oct 20'], [$crop['label'], $crop['value'], $crop['unit'], $crop['note']]);
        $this->assertArrayNotHasKey('progress', $crop);

        // Harvest date passed 6 days ago and nothing recorded: the tile says so. More than 60 days: no tile.
        $upcoming->update(['planting_date' => '2026-06-12', 'scheduled_harvest_date' => '2026-09-30']);
        $crop = $this->tiles($farmer, null)['crop'];
        $this->assertSame(['Harvest date passed', '6', 'days ago'], [$crop['label'], $crop['value'], $crop['unit']]);

        $upcoming->update(['planting_date' => '2026-02-01', 'scheduled_harvest_date' => '2026-05-20']);
        $this->assertArrayNotHasKey('crop', $this->tiles($farmer, null));
    }

    public function test_expected_harvest_is_the_expected_yield_times_the_known_farm_size(): void
    {
        $farmer = $this->farmer();
        $farm = $this->farm($farmer);                                          // 1.5 ha

        $expected = $this->tiles($farmer, $farm, ['state' => 'ok', 'yield' => 4.2])['expected'];
        $this->assertSame(['6.30', 'tons', '4.20 t/ha × 1.5 ha'], [$expected['value'], $expected['unit'], $expected['note']]);

        // No estimate yet, or only an estimated size: no total.
        $this->assertArrayNotHasKey('expected', $this->tiles($farmer, $farm, ['state' => 'loading']));
        $rough = $this->farm($farmer, ['farm_size_hectares' => null, 'farm_size_method' => Farm::SIZE_ESTIMATED, 'farm_size_estimate_range' => '1_2', 'is_primary' => false]);
        $this->assertArrayNotHasKey('expected', $this->tiles($farmer, $rough, ['state' => 'ok', 'yield' => 4.2]));
    }

    public function test_rainy_days_are_counted_from_the_same_seven_day_forecast(): void
    {
        $farmer = $this->farmer();

        $rain = $this->tiles($farmer, null, week: $this->week(['light', 'none', 'heavy', 'moderate', 'none', 'none', 'heavy']))['rain'];
        $this->assertSame(['Rainy days, next 7 days', '4', 'of 7', 'Heavy rain on 2 days'], [$rain['label'], $rain['value'], $rain['unit'], $rain['note']]);

        $dry = $this->tiles($farmer, null, week: $this->week(['none', 'none', 'none']))['rain'];
        $this->assertSame(['0', 'of 3', 'No heavy rain expected'], [$dry['value'], $dry['unit'], $dry['note']]);

        // No forecast: no tile, never "0 rainy days".
        $this->assertArrayNotHasKey('rain', $this->tiles($farmer, null));
    }

    // ------------------------------------------------------------ the yield comparison

    public function test_the_comparison_needs_two_figures_and_uses_the_farmers_last_recorded_harvest(): void
    {
        $farmer = $this->farmer();
        $compare = fn (array $harvest) => app(FarmerHomeKpis::class)->for($farmer, null, null, $harvest, ['state' => 'nodata', 'days' => []], $this->now)['compare'];

        // Only Lian's usual yield is known: one figure is not a comparison.
        $this->assertSame('nodata', $compare(['state' => 'nodata'])['state']);

        $this->plan($farmer, '2025-06-01', '2025-09-20', ['actual_yield' => 3.2, 'harvested_on' => '2025-09-22']);
        $this->plan($farmer, '2026-03-01', '2026-06-20', ['actual_yield' => 4.6, 'harvested_on' => '2026-06-22']);   // the last one
        $all = $compare(['state' => 'ok', 'yield' => 4.25, 'usual' => 4.0]);

        $this->assertSame(['estimate', 'usual', 'last'], $all['keys']);
        $this->assertSame([4.25, 4.0, 4.6], $all['values']);
        $this->assertSame(['Expected this season', 'Usual in Lian', 'My last harvest'], $all['labels']);
        // Without an estimate the usual yield is still this month's, beside the last harvest.
        $this->assertSame(['usual', 'last'], $compare(['state' => 'loading'])['keys']);
    }

    // ------------------------------------------------------------ on the page

    public function test_home_shows_the_figures_in_the_farmers_language_and_refreshes_them_with_the_other_parts(): void
    {
        $farmer = $this->farmer();
        $this->farm($farmer);
        $this->plan($farmer, '2026-07-28', '2026-11-05');

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<section class="fh-kpis"[^>]*data-fh-kpis>(.*?)</section>#s', $html, $m));
        preg_match_all('#data-fh-kpi="([a-z]+)"#', $m[1], $keys);
        // No estimate and no forecast here, so "expected" and "rain" are left out; the row is filled with figures about the
        // farmer's place that need nothing from the farmer (2026-10-09).
        $this->assertSame(['farm', 'crop', 'advisories', 'usual'], $keys[1]);
        $text = (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1])));
        $this->assertStringContainsString('Farm size 1.5 ha Irrigated', $text);
        $this->assertStringContainsString('Days to harvest 30 days', $text);
        $this->assertStringContainsString('href="'.route('farmer.my-farm').'"', $m[1]);

        $slots = $this->actingAs($farmer)->getJson(route('farmer.dashboard.live'))->assertOk()->json('slots');
        $this->assertStringContainsString('data-fh-kpi="crop"', $slots['kpis']);

        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        Cache::flush();
        $fil = $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Laki ng bukid', $fil);
        $this->assertStringContainsString('Araw bago mag-ani', $fil);
        $this->assertStringContainsString('May patubig', $fil);
    }

    public function test_a_farmer_with_nothing_saved_still_gets_real_figures_about_their_place_and_no_empty_tiles(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('farmer.dashboard'))->assertOk()->getContent();

        // Nothing of their own is saved (no farm size, plan, estimate) and no forecast is stored: only the two figures
        // that exist are shown. Zero advisories is a real count; the usual yield is 4.00 t/ha in this test's records.
        preg_match_all('#data-fh-kpi="([a-z]+)"#', $html, $keys);
        $this->assertSame(['advisories', 'usual'], $keys[1]);
        $this->assertSame(1, preg_match('#<section class="fh-kpis"[^>]*data-fh-kpis>(.*?)</section>#s', $html, $m));
        $text = (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[1])));
        $this->assertStringContainsString('Advisories in effect 0 No advisory right now', $text);
        $this->assertStringContainsString('Usual yield in Lian 4.00 t/ha', $text);
        $this->assertStringNotContainsString('data-fh-chart="compare"', $html);
    }

    public function test_the_row_is_filled_to_five_with_place_figures_after_the_farmers_own(): void
    {
        $farmer = $this->farmer();
        $place = [
            'risk' => ['state' => 'ok', 'label' => 'Low', 'barangay' => 'Prenza'],
            'barangay' => 'Prenza',
            'forecast' => ['state' => 'ok', 'labels' => ['Today', 'Sat 10', 'Sun 11'], 'temperature' => [30, 33, 31]],
        ];
        $tiles = fn (?\App\Models\Farm $farm, array $harvest, array $week): array => collect(app(FarmerHomeKpis::class)->for($farmer, $farm, null, $harvest, $week, $this->now, $place)['tiles'])->keyBy('key')->all();

        // Nothing of the farmer's own: the four place figures.
        $sparse = $tiles(null, ['state' => 'nodata'], ['state' => 'nodata', 'days' => []]);
        $this->assertSame(['risk', 'advisories', 'hot', 'usual'], array_keys($sparse));
        $this->assertSame(['Low', 'Prenza'], [$sparse['risk']['value'], $sparse['risk']['note']]);
        $this->assertSame(['33', '°C', 'Sat 10'], [$sparse['hot']['value'], $sparse['hot']['unit'], $sparse['hot']['note']]);
        $this->assertSame(['4.00', 't/ha'], [$sparse['usual']['value'], $sparse['usual']['unit']]);

        // With all four of the farmer's own figures, one place figure completes the five.
        $this->plan($farmer, '2026-07-28', '2026-11-05');
        $full = $tiles($this->farm($farmer), ['state' => 'ok', 'yield' => 4.2], $this->week(['light', 'none', 'heavy']));
        $this->assertSame(['farm', 'crop', 'expected', 'rain', 'risk'], array_keys($full));
        $this->assertCount(FarmerHomeKpis::MAX_TILES, $full);
    }

    public function test_english_and_filipino_have_the_same_words_for_the_figures(): void
    {
        $keys = fn (string $locale): array => array_keys(Arr::dot((require lang_path($locale.'/farmer.php'))['kpis']));

        $this->assertSame($keys('en'), $keys('fil'));
    }
}
