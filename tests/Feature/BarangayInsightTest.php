<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\AI\BarangayInsightService;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Services\Risk\BarangayRiskMapService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesBarangayWeather;
use Tests\TestCase;

/**
 * The Heat Map's Barangay AI Insight: every barangay opens one, its values are exactly the risk map's verified entry,
 * the AI only explains them (short, plain, nothing invented), the explanation is kept until the data changes, and a
 * failed AI never breaks the pop-up.
 */
class BarangayInsightTest extends TestCase
{
    use FakesBarangayWeather;
    use RefreshDatabase;

    /** @var array<int, array<string, mixed>> the context of every Groq insight request */
    private array $groqContexts = [];

    private bool $groqUp = true;

    /** Replaces the fake AI reply (for checking what is rejected). */
    private ?array $groqReply = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 20:18', 'Asia/Manila'));
        Cache::flush();
        Config::set('services.groq.enabled', true);
        Config::set('services.groq.key', 'test-key');
        Config::set('services.groq.base_url', 'https://api.groq.com/openai/v1');
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/*' => fn ($request) => $this->openMeteoAnswer($request, fn (float $lat, float $lon) => $this->weatherFor($lat, $lon)),
            'api.groq.com/*' => fn (Request $request) => $this->groqAnswer($request),
        ]);
        $this->prepareMonthlyRainfall();
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();
    }

    public function test_every_lian_barangay_opens_an_insight_with_exactly_the_maps_values(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'San Diego']);
        $map = collect($this->getJson('/api/lian/risk-map')->assertOk()->json('barangays'))->keyBy('barangay');

        $this->assertCount(19, LianBarangays::all());
        foreach (LianBarangays::all() as $barangay) {
            $entry = $map[$barangay];
            $response = $this->actingAs($farmer)->getJson(route('heat-map.insight', ['barangay' => $barangay, 'lang' => 'en']))->assertOk();
            $data = $response->json('data');

            $this->assertSame('ok', $response->json('status'));
            $this->assertSame($barangay, $response->json('barangay'));
            $this->assertSame($entry['risk_level'], $data['risk_level'], "{$barangay}: risk");
            $this->assertEquals($entry['weather']['temperature_c'], $data['temperature_c'], "{$barangay}: temperature");
            $this->assertEquals($entry['weather']['humidity_percent'], $data['humidity_percent'], "{$barangay}: humidity");
            $this->assertEquals($entry['weather']['rainfall_24h_mm'], $data['rainfall_24h_mm'], "{$barangay}: rainfall");
            $this->assertSame($entry['weather']['condition'], $data['condition'], "{$barangay}: condition");
            $this->assertSame($entry['weather']['fetched_at'], $data['weather_updated_at'], "{$barangay}: weather time");
            $this->assertSame($entry['risk_calculated_at'], $data['risk_calculated_at'], "{$barangay}: risk time");
            $this->assertSame('current', $response->json('freshness'));
            $this->assertSame('ok', $response->json('insight.status'));
            $this->assertSame($barangay === 'San Diego', $response->json('is_own_barangay'));

            // The AI was given those same verified values, and nothing else from the database.
            $context = end($this->groqContexts);
            $this->assertSame($barangay, $context['barangay']);
            $this->assertSame($entry['risk_level'], $context['risk_level']);
            $this->assertEquals($entry['weather']['temperature_c'], $context['temperature_c']);
            $this->assertEquals($entry['weather']['humidity_percent'], $context['humidity_percent']);
            $this->assertEquals($entry['weather']['rainfall_24h_mm'], $context['rainfall_next_24h_mm']);
            $this->assertEmpty(array_diff(array_keys($context), ['barangay', 'risk_level', 'risk_status', 'temperature_c', 'humidity_percent',
                'rainfall_next_24h_mm', 'rain_outlook', 'condition', 'weather_status', 'weather_updated', 'risk_calculated', 'tone']));
        }

        $this->assertCount(19, $this->groqContexts);
        // Different barangays really have different readings here, so nothing above matched by accident.
        $this->assertGreaterThan(1, $map->pluck('weather.temperature_c')->unique()->count());
    }

    public function test_the_insight_is_kept_until_the_weather_or_risk_changes(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $url = fn (string $lang) => route('heat-map.insight', ['barangay' => 'Bagong Pook', 'lang' => $lang]);

        $first = $this->actingAs($mao)->getJson($url('en'))->assertOk();
        $again = $this->actingAs($mao)->getJson($url('en'))->assertOk();
        $this->assertCount(1, $this->groqContexts, 'Same data: the explanation is reused.');
        $this->assertFalse($first->json('insight.cached'));
        $this->assertTrue($again->json('insight.cached'));
        $this->assertSame($first->json('insight.text'), $again->json('insight.text'));
        $this->assertSame($first->json('signature'), $again->json('signature'));

        $this->actingAs($mao)->getJson($url('fil'))->assertOk();
        $this->assertCount(2, $this->groqContexts, 'Each language has its own explanation.');

        // New weather and a new rating: a new explanation, once.
        $this->travel(11)->minutes();
        $this->artisan('iclimate:refresh-barangay-weather', ['--force' => true])->assertSuccessful();
        $newer = $this->actingAs($mao)->getJson($url('en'))->assertOk();
        $this->actingAs($mao)->getJson($url('en'))->assertOk();

        $this->assertCount(3, $this->groqContexts);
        $this->assertNotSame($first->json('signature'), $newer->json('signature'));
    }

    public function test_farmer_and_mao_get_the_same_values_and_the_same_insight(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $url = route('heat-map.insight', ['barangay' => 'Balibago', 'lang' => 'en']);

        $forFarmer = $this->actingAs($farmer)->getJson($url)->assertOk();
        $forMao = $this->actingAs($mao)->getJson($url)->assertOk();

        $this->assertSame($forFarmer->json('data'), $forMao->json('data'));
        $this->assertSame($forFarmer->json('insight.text'), $forMao->json('insight.text'));
        $this->assertTrue($forFarmer->json('is_own_barangay'));
        $this->assertFalse($forMao->json('is_own_barangay'));
        $this->assertCount(1, $this->groqContexts);
    }

    public function test_a_failed_ai_keeps_the_verified_values_and_says_the_insight_is_unavailable(): void
    {
        $this->groqUp = false;
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $entry = collect($this->getJson('/api/lian/risk-map')->json('barangays'))->firstWhere('barangay', 'Binubusan');

        $response = $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'Binubusan', 'lang' => 'en']))->assertOk();

        $this->assertSame('ok', $response->json('status'));
        $this->assertSame('unavailable', $response->json('insight.status'));
        $this->assertNull($response->json('insight.text'));
        $this->assertSame('iclimate', $response->json('insight.action_source'));
        $this->assertSame($entry['risk_level'], $response->json('data.risk_level'));
        $this->assertEquals($entry['weather']['temperature_c'], $response->json('data.temperature_c'));
    }

    public function test_an_unexpected_failure_never_answers_500(): void
    {
        $this->mock(BarangayRiskMapService::class, fn ($mock) => $mock->shouldReceive('detailFor')->andThrow(new \RuntimeException('database down')));
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'Bungahan']))
            ->assertOk()
            ->assertJson(['status' => 'unavailable', 'message' => 'AI insight is temporarily unavailable.']);
    }

    public function test_a_long_or_invented_ai_reply_is_rejected_not_shown(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->groqReply = [
            'insight' => 'Current meteorological conditions indicate that the barangay is experiencing elevated relative humidity and moderate agricultural risk. A typhoon may bring flooding. Yield could drop.',
            'action' => 'Monitor everything.',
        ];

        $response = $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'San Diego']))->assertOk();

        $this->assertSame('unavailable', $response->json('insight.status'));
        $this->assertStringNotContainsStringIgnoringCase('typhoon', (string) $response->getContent());
    }

    public function test_the_farmer_language_check(): void
    {
        $context = ['risk_level' => 'Moderate', 'temperature_c' => 25.8, 'humidity_percent' => 91, 'rainfall_next_24h_mm' => 8.8,
            'weather_status' => 'current', 'weather_updated' => '8:18 PM', 'risk_calculated' => '8:18 PM'];
        $check = fn (string $insight, string $action, array $overrides = []) => BarangayInsightService::check(['insight' => $insight, 'action' => $action], $overrides + $context);

        // Short, plain, true to the data.
        $this->assertNotNull($check('Humidity is high at 91%, so the field may stay wet longer.', 'Check soil moisture before watering.'));
        $this->assertNotNull($check('Moderate risk today, with about 8.8 mm of rain in the next 24 hours.', 'Check your field before watering.'));
        $this->assertNotNull($check('Mataas ang humidity na 91%, kaya puwedeng manatiling basa ang bukid.', 'Suriin muna ang lupa bago magdilig.'));
        $this->assertNotNull($check('“Conditions need some attention.”', '"Check soil moisture before watering."'), 'Wrapping quotes are removed.');

        // Too long but usable: shortened to the first sentence or clause, not rejected.
        $this->assertSame(['insight' => 'Conditions need attention.', 'action' => 'Check the soil.'], $check('Conditions need attention. Humidity is high.', 'Check the soil.'));
        $this->assertSame(['insight' => 'Conditions need attention.', 'action' => 'Check the soil.'], $check('Conditions need attention. Humidity is high. Rain is low.', 'Check the soil. Then water. Then rest.'));
        $this->assertSame('Mainit na 25.8°C, maalinsangan.', $check('Mainit na 25.8°C, maalinsangan; kaunting ulan.', 'Suriin ang lupa.')['insight'], 'Semicolon-joined clauses keep the first.');
        $this->assertSame('Check the soil, then wait.', $check('Conditions need attention.', 'Check the soil, then wait')['action'] ?? null, 'A missing full stop is added.');

        // Unusable: one sentence too long to cut, a list, a list of values.
        $this->assertNull($check(str_repeat('Rain is light and the air is very damp around the field today ', 4).'.', 'Check the soil.'));
        $this->assertNull($check('Conditions need attention.', "Do this:\n- check the soil\n- check drainage"));
        $this->assertNull($check('It is 25.8°C with 91% humidity and 8.8 mm of rain.', 'Check the soil.'), 'A list of values, not plain words.');

        // Unsafe in a part that would be cut off still rejects the whole reply.
        $this->assertNull($check('Conditions need attention. A typhoon may come.', 'Check the soil.'), 'Invented topic after the first sentence.');
        $this->assertNull($check('Conditions need attention. The model is unsure.', 'Check the soil.'), 'Technical word after the first sentence.');
        $this->assertNull($check('Conditions need attention. Humidity is 75%.', 'Check the soil.'), 'Unsupplied number after the first sentence.');

        // Technical words or invented topics.
        $this->assertNull($check('The model shows moderate risk.', 'Check the soil.'));
        $this->assertNull($check('Precipitation is low.', 'Check the soil.'));
        $this->assertNull($check('A typhoon may come.', 'Check the soil.'));
        $this->assertNull($check('Conditions need attention.', 'Watch for pests in the field.'));
        $this->assertNull($check('Good time for harvest in November.', 'Check the soil.'));
        $this->assertNull($check('Baka bumaha sa bukid.', 'Suriin ang lupa.'));

        // Numbers and risk exactly as supplied.
        $this->assertNull($check('It is 27°C today.', 'Check the soil.'), 'Wrong temperature.');
        $this->assertNull($check('Expect 15 mm of rain.', 'Check the soil.'), 'Invented rainfall.');
        $this->assertNull($check('This is a low risk day.', 'Check the soil.'), 'Wrong risk.');
        $this->assertNull($check('Mababang panganib ngayon.', 'Suriin ang lupa.'), 'Wrong risk (Filipino).');
        $this->assertNull($check('Risk is moderate.', 'Check the soil.', ['risk_level' => null]), 'No verified risk: none may be named.');

        // Delayed readings are never called live.
        $this->assertNull($check('Live readings show damp air.', 'Check the soil.', ['weather_status' => 'delayed']));
    }

    public function test_iclimates_own_actions_pass_the_same_check(): void
    {
        foreach (['en', 'fil'] as $language) {
            foreach (['Low', 'Moderate', 'High', 'Severe', null] as $level) {
                $action = (new \ReflectionClassConstant(BarangayInsightService::class, 'ACTION'))->getValue()[$language][$level ?? ''];
                $this->assertNotNull(BarangayInsightService::check(['insight' => 'Conditions need some attention.', 'action' => $action], ['risk_level' => $level, 'weather_status' => 'current']), "{$language} {$level}");
            }
        }
    }

    public function test_generated_insights_stay_short(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        foreach (['Bagong Pook', 'Balibago', 'Binubusan', 'Bungahan', 'San Diego'] as $barangay) {
            foreach (['en', 'fil'] as $language) {
                $insight = $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => $barangay, 'lang' => $language]))->json('insight');
                $this->assertSame('ok', $insight['status']);
                $this->assertLessThanOrEqual(BarangayInsightService::MAX_INSIGHT_SENTENCES, preg_match_all('/[.!?](\s|$)/', $insight['text']));
                $this->assertLessThanOrEqual(BarangayInsightService::MAX_ACTION_SENTENCES, preg_match_all('/[.!?](\s|$)/', $insight['action']));
                $this->assertLessThanOrEqual(60, str_word_count($insight['text'].' '.$insight['action']));
            }
        }
    }

    public function test_missing_risk_is_never_shown_as_low(): void
    {
        HeatmapArea::query()->delete();
        $this->app->instance(AmbulongHistoricalClimateDataset::class, new AmbulongHistoricalClimateDataset(database_path('data/does-not-exist.csv')));
        MonthlyWeatherPrediction::query()->delete();
        $weather = Cache::get(LianBarangayWeatherService::STORE_KEY);
        Cache::flush();
        Cache::forever(LianBarangayWeatherService::STORE_KEY, $weather);
        app(BarangayRiskMapService::class)->refreshForCurrentWeather(force: true);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $response = $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'Cumba']))->assertOk();

        $this->assertNull($response->json('data.risk_level'));
        $this->assertSame('unavailable', $response->json('data.risk_status'));
        $this->assertContains('Risk is being updated.', $response->json('notices'));
        $this->assertSame('latest_available', $response->json('freshness'));
        $this->assertNull(end($this->groqContexts)['risk_level']);
    }

    public function test_delayed_weather_is_the_latest_available_insight(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->travel(45)->minutes();

        $response = $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'Prenza']))->assertOk();

        $this->assertSame('delayed', $response->json('data.weather_status'));
        $this->assertSame('latest_available', $response->json('freshness'));
        $this->assertContains('Based on the last available weather update.', $response->json('notices'));
        $this->assertSame('delayed', end($this->groqContexts)['weather_status']);
    }

    public function test_language_defaults_to_the_farmers_choice(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Kapito']);
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.language'), ['language' => 'fil'])->assertOk();

        $this->actingAs($farmer)->getJson(route('heat-map.insight', ['barangay' => 'Kapito']))->assertOk()->assertJson(['language' => 'fil']);
        $this->actingAs($farmer)->getJson(route('heat-map.insight', ['barangay' => 'Kapito', 'lang' => 'en']))->assertOk()->assertJson(['language' => 'en']);
    }

    public function test_guests_and_unknown_barangays(): void
    {
        $this->getJson(route('heat-map.insight', ['barangay' => 'San Diego']))->assertUnauthorized();

        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->getJson(route('heat-map.insight', ['barangay' => 'Calatagan']))->assertNotFound()->assertJson(['status' => 'not_found']);
    }

    public function test_the_heat_map_page_uses_its_panel_instead_of_the_insight_pop_up(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'San Diego']);

        // Heat Map page (2026-10): a clicked barangay opens in the panel beside the map, not in the AI Insight pop-up.
        $this->actingAs($farmer)->get(route('heat-map.index'))
            ->assertOk()
            ->assertSee('data-heat-map-panel', false)
            ->assertDontSee('data-insight-url', false);

        // The pop-up stays where it was not replaced: the IT dashboard's map.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_IT_EXPERT]))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-insight-url="'.url('/heat-map/insight').'"', false);
    }

    private function groqAnswer(Request $request)
    {
        if (! $this->groqUp) {
            return Http::response(['error' => 'down'], 500);
        }

        $prompt = (string) data_get($request->data(), 'messages.1.content');
        $context = json_decode(substr($prompt, strpos($prompt, "\n") + 1), true);
        $this->groqContexts[] = $context;
        $filipino = str_contains((string) data_get($request->data(), 'messages.0.content'), 'Filipino');
        $humidity = $context['humidity_percent'];

        $reply = $this->groqReply ?? ($filipino
            ? ['insight' => "Mataas ang humidity na {$humidity}%, kaya kailangang bantayan ang bukid.", 'action' => 'Suriin muna ang lupa bago magdilig.']
            : ['insight' => "Humidity is {$humidity}%, so conditions need some attention.", 'action' => 'Check soil moisture before watering.']);

        return Http::response(['choices' => [['message' => ['content' => json_encode($reply)]]], 'usage' => []]);
    }

    /** Each barangay its own readings (from its position in the official list), so values cannot match by accident. */
    private function weatherFor(float $latitude, float $longitude): array
    {
        $index = (int) array_search(
            collect(LianBarangays::coordinates())->search(fn (array $point) => abs($point[0] - $latitude) < 1e-6 && abs($point[1] - $longitude) < 1e-6),
            LianBarangays::all(),
            true,
        );
        $start = now('Asia/Manila')->startOfHour();
        $times = collect(range(0, 47))->map(fn (int $hour) => $start->copy()->addHours($hour)->format('Y-m-d\TH:i'))->all();
        $rain = round(0.1 + $index * 0.05, 2);

        return [
            'current' => ['time' => now('Asia/Manila')->format('Y-m-d\TH:i'), 'temperature_2m' => round(24.5 + $index * 0.3, 1), 'relative_humidity_2m' => 70 + $index,
                'precipitation' => 0.1, 'rain' => 0.1, 'weather_code' => $index % 2 ? 3 : 61, 'is_day' => 0, 'wind_speed_10m' => 6.0 + $index],
            'hourly' => ['time' => $times, 'precipitation_probability' => array_fill(0, 48, 40 + $index), 'precipitation' => array_fill(0, 48, $rain),
                'rain' => array_fill(0, 48, $rain), 'temperature_2m' => array_fill(0, 48, 27), 'relative_humidity_2m' => array_fill(0, 48, 80),
                'wind_speed_10m' => array_fill(0, 48, 8.0)],
            'daily' => ['time' => [now('Asia/Manila')->toDateString()], 'precipitation_sum' => [$rain * 24], 'rain_sum' => [$rain * 24],
                'temperature_2m_max' => [31], 'temperature_2m_min' => [24], 'precipitation_probability_max' => [60]],
        ];
    }

    private function prepareMonthlyRainfall(): void
    {
        $month = now()->startOfMonth();
        MonthlyWeatherPrediction::query()->updateOrCreate(
            ['prediction_year' => $month->year, 'prediction_month' => $month->month, 'weather_model' => MonthlyWeatherRandomForest::MODEL_VERSION],
            ['rainfall' => 200, 'tmax' => 30, 'tmin' => 24, 'temperature_average' => 27, 'humidity' => 75, 'wind_speed' => 10,
                'wind_direction' => 180, 'season' => 'Wet', 'generated_at' => now(), 'expires_at' => now()->addDay(),
                'status' => MonthlyWeatherPrediction::STATUS_READY,
                'metadata' => ['dataset_version' => app(MonthlyWeatherRandomForest::class)->datasetVersion()]],
        );
    }
}
