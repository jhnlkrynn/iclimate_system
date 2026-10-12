<?php

namespace Tests\Feature;

use App\Jobs\PrepareFarmerYieldEstimate;
use App\Models\ExternalWeatherData;
use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\MlModel;
use App\Models\Notification;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Models\YieldPredictionFeedback;
use App\Services\Farmer\FarmerHomeCharts;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Services\PlantingSchedulePredictionService;
use App\Services\Prediction\RiceYieldPredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The farmer side added in 2026-10 (Naicel): the greeting line, the Home charts, missing information as bell
 * notifications, the Activity Calendar on John Luke's farms (App\Models\Farm), the quiet Payo page, the forecast
 * survey and the actual-harvest question.
 */
class FarmerNaicelFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-10-07 02:00:00', 'UTC'));       // 10:00 AM, Wednesday Oct 7 in Lian
        Http::fake(['*' => Http::response([], 500)]);                          // no page may need a provider
        // The yield model is slow and external; nothing here depends on it.
        $this->mock(PlantingSchedulePredictionService::class, fn ($mock) => $mock->shouldReceive('refresh')->andReturnUsing(fn ($schedule) => $schedule));
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(null));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function farmer(?float $area = 1.5, ?string $barangay = 'Binubusan', array $attributes = []): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'status' => User::STATUS_ACTIVE, 'barangay' => $barangay, 'name' => 'Naicel Dela Cruz'] + $attributes);
        if ($barangay !== null) {
            FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => $area, 'farm_type' => 'Rainfed']);
        }

        return $user;
    }

    /** The farmer's farms in use (John Luke's farms table): the primary farm first. */
    private function farms(User $user)
    {
        return Farm::query()->ownedBy($user)->active()->orderByDesc('is_primary')->orderBy('id')->get();
    }

    private function addFarm(User $user, string $name, string $barangay, float $hectares, string $type = 'Irrigated'): Farm
    {
        return app(\App\Services\Farms\FarmService::class)->create($user->fresh(), [
            'farm_name' => $name, 'barangay' => $barangay, 'farm_type' => $type,
            'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_value' => $hectares, 'farm_size_unit' => Farm::UNIT_HECTARES,
        ]);
    }

    private function planting(User $user, array $attributes = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($attributes + [
            'user_id' => $user->id, 'farm_id' => $this->farms($user)->first()?->id, 'planting_date' => '2026-06-10', 'scheduled_harvest_date' => '2026-10-03',
            'season' => 'Wet', 'farm_type' => 'Rainfed', 'barangay' => 'Binubusan', 'farm_area_hectares' => 1.5,
        ]);
    }

    private function text(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', strip_tags($html));
    }

    // ------------------------------------------------------------ 1. greeting

    public function test_home_greets_with_one_short_line_by_manila_time_in_both_languages(): void
    {
        $farmer = $this->farmer();

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<p class="ic-topbar-greeting">Good morning, Naicel!</p>#', $html);   // in the top bar now

        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        Carbon::setTestNow(Carbon::parse('2026-10-07 07:00:00', 'UTC'));       // 3:00 PM in Lian
        $this->assertStringContainsString('Magandang hapon, Naicel!', $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->getContent());

        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'UTC'));       // 8:00 PM in Lian
        Cache::flush();
        $this->assertStringContainsString('Magandang gabi, Naicel!', $this->actingAs($farmer->fresh())->get(route('farmer.dashboard'))->getContent());
    }

    // ------------------------------------------------------------ 2. charts

    public function test_home_charts_say_one_short_line_when_there_is_nothing_to_draw(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Your harvests will show here after you record one.', $html);
        $this->assertStringContainsString('The 14-day forecast is not ready yet.', $html);
        $this->assertStringNotContainsString('<canvas', $html);
    }

    public function test_harvest_chart_has_my_harvest_per_season_and_the_barangay_average(): void
    {
        $farmer = $this->farmer();
        $this->planting($farmer, ['planting_date' => '2025-06-10', 'scheduled_harvest_date' => '2025-10-03', 'actual_yield' => 3.8, 'harvested_on' => '2025-10-05']);
        $this->planting($farmer, ['planting_date' => '2025-11-20', 'scheduled_harvest_date' => '2026-03-15', 'season' => 'Dry', 'actual_yield' => 4.4, 'harvested_on' => '2026-03-16']);
        RiceProduction::factory()->create(['barangay' => 'Binubusan', 'season' => 'Wet', 'year' => 2025, 'yield_per_hectare' => 4.1, 'remarks' => 'MAO record']);

        $chart = app(FarmerHomeCharts::class)->harvest($farmer, null, 'Binubusan');

        $this->assertSame('ok', $chart['state']);
        $this->assertSame(['Wet 2025', 'Dry 2025–26'], $chart['labels']);
        $this->assertSame([3.8, 4.4], $chart['mine']);
        $this->assertSame(4.1, $chart['average'][0]);
        $this->assertNull($chart['average'][1], 'no record and fewer than three farmers: no line point, never a guess');

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="fhHarvestChart"', $html);
        $this->assertStringContainsString('My harvest (t\/ha)', $html);
    }

    public function test_a_barangay_average_from_farmers_records_needs_three_farmers(): void
    {
        $farmer = $this->farmer();
        $this->planting($farmer, ['planting_date' => '2025-06-10', 'actual_yield' => 4.0]);
        $second = $this->farmer(attributes: ['email' => 'second@example.test']);
        $this->planting($second, ['planting_date' => '2025-06-15', 'actual_yield' => 5.0]);

        $this->assertNull(app(FarmerHomeCharts::class)->harvest($farmer, null, 'Binubusan')['average'][0]);

        $third = $this->farmer(attributes: ['email' => 'third@example.test']);
        $this->planting($third, ['planting_date' => '2025-07-01', 'actual_yield' => 3.0]);

        $this->assertSame(4.0, app(FarmerHomeCharts::class)->harvest($farmer, null, 'Binubusan')['average'][0]);
    }

    public function test_weather_chart_shows_fourteen_days_of_temperature_and_chance_of_rain(): void
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $days = array_map(fn (int $i): array => ['kind' => 'forecast', 'date' => $today->addDays($i), 'high' => 30.4 + $i, 'chance' => 10 * ($i % 5)], range(0, 13));

        $chart = app(FarmerHomeCharts::class)->weatherFrom(['state' => 'ok', 'location' => 'Binubusan, Lian', 'updated_at' => null, 'days' => $days, 'estimated' => 0]);

        $this->assertSame('ok', $chart['state']);
        $this->assertCount(14, $chart['labels']);
        $this->assertSame('Today', $chart['labels'][0]);
        $this->assertSame(30.0, $chart['temperature'][0]);
        $this->assertSame(40.0, $chart['rain'][4]);
    }

    // ------------------------------------------------------------ 3. missing information goes to the bell

    public function test_missing_information_becomes_one_notification_each_and_is_never_duplicated(): void
    {
        $farmer = $this->farmer(area: null);
        $notifier = app(FarmerMissingInfoNotifier::class);

        $notifier->sync($farmer);
        $notifier->sync($farmer->fresh());

        $this->assertSame(['setup:farm_area', 'setup:planting_date'], Notification::query()->where('user_id', $farmer->id)->orderBy('category')->pluck('category')->all());
        $this->assertSame('Add your planting date', Notification::query()->where('category', 'setup:planting_date')->value('title'));

        // In the farmer's language; switching the language rewrites it instead of adding another.
        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        $notifier->sync($farmer->fresh());
        $this->assertSame('Idagdag ang iyong petsa ng pagtatanim', Notification::query()->where('category', 'setup:planting_date')->sole()->title);
    }

    public function test_a_notification_is_removed_once_the_information_is_added(): void
    {
        $farmer = $this->farmer(area: null);
        app(FarmerMissingInfoNotifier::class)->sync($farmer);
        $farm = $this->farms($farmer)->first();

        $this->actingAs($farmer)->put(route('farmer.farms.update', $farm->id), ['farm_name' => 'Farm 1', 'barangay' => 'Binubusan', 'farm_type' => 'Rainfed',
            'farm_size_method' => 'exact', 'farm_size_value' => '2', 'farm_size_unit' => 'hectares'])->assertRedirect();
        // John Luke's farm form opens My Farm next, which checks again at once.
        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk();
        $this->assertDatabaseMissing('notifications', ['user_id' => $farmer->id, 'category' => 'setup:farm_area']);

        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-10-01', 'kind' => 'planted', 'farm_id' => $farm->id])->assertRedirect();
        $this->assertSame(0, Notification::query()->where('user_id', $farmer->id)->count());
    }

    public function test_a_harvest_without_a_record_is_asked_for_and_the_bell_links_to_the_right_page(): void
    {
        $farmer = $this->farmer();
        $this->planting($farmer);                                              // expected harvest Oct 3: already past
        $noBarangay = $this->farmer(barangay: null, attributes: ['email' => 'nb@example.test']);

        app(FarmerMissingInfoNotifier::class)->sync($farmer);
        app(FarmerMissingInfoNotifier::class)->sync($noBarangay);

        $items = collect($this->actingAs($farmer)->getJson(route('notifications.feed'))->assertOk()->json('items'));
        $this->assertSame(['Add your harvest record'], $items->pluck('title')->all());
        $this->assertSame(route('farmer.my-farm').'#activity', $items->first()['link_url']);
        $this->assertSame('Open', $items->first()['link_label']);

        $items = collect($this->actingAs($noBarangay)->getJson(route('notifications.feed'))->json('items'))->keyBy('title');
        $this->assertSame(route('profile.edit').'#barangay', $items['Add your barangay']['link_url']);
        $this->assertSame(route('farmer.farms.create'), $items['Add your farm']['link_url']);
    }

    public function test_home_shows_no_warning_card_for_missing_information(): void
    {
        $farmer = $this->farmer(area: null);

        $html = $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Add your farm size', $this->text($html));
        $this->assertStringNotContainsString('data-fh-harvest', $html);
        $this->assertDatabaseHas('notifications', ['user_id' => $farmer->id, 'category' => 'setup:farm_area']);
    }

    // ------------------------------------------------------------ 5. with John Luke's farms (App\Models\Farm)

    public function test_the_calendar_and_the_harvest_chart_use_the_farmers_own_farms(): void
    {
        $farmer = $this->farmer();
        $first = $this->farms($farmer)->sole();                                    // "Farm 1", made from the profile
        $this->assertSame(['Farm 1', 'Binubusan', true], [$first->farm_name, $first->barangay, $first->is_primary]);
        $second = $this->addFarm($farmer, 'Bukid sa ilog', 'Prenza', 0.75);

        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-06-12', 'kind' => 'planted', 'farm_id' => $second->id])->assertRedirect();
        $plan = FarmerPlantingSchedule::query()->sole();
        $this->assertSame([$second->id, 'Prenza', 'Irrigated', '0.75'], [$plan->farm_id, $plan->barangay, $plan->farm_type, $plan->farm_area_hectares]);

        // Each farm's harvests on its own chart.
        $this->planting($farmer, ['farm_id' => $first->id, 'planting_date' => '2025-06-10', 'actual_yield' => 3.8]);
        $this->planting($farmer, ['farm_id' => $second->id, 'planting_date' => '2025-06-15', 'actual_yield' => 5.1]);
        $this->assertSame([3.8], app(FarmerHomeCharts::class)->harvest($farmer, $first, null)['mine']);
        $this->assertSame([5.1], app(FarmerHomeCharts::class)->harvest($farmer, $second, null)['mine']);

        // The calendar names the farm when there are two or more.
        $html = $this->actingAs($farmer)->get(route('farmer.my-farm', ['month' => '2026-06']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#fmf-cal-event is-planted">Planted<small>Bukid sa ilog</small>#', $html);
    }

    public function test_an_archived_or_another_farmers_farm_cannot_get_an_activity(): void
    {
        $farmer = $this->farmer();
        $second = $this->addFarm($farmer, 'Bukid sa ilog', 'Prenza', 0.75);
        app(\App\Services\Farms\FarmService::class)->archive($second);

        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-06-12', 'kind' => 'planted', 'farm_id' => $second->id])
            ->assertSessionHasErrorsIn('activity', ['farm_id']);
        $this->assertSame(0, FarmerPlantingSchedule::query()->count());
    }

    // ------------------------------------------------------------ 4. Activity Calendar

    public function test_the_calendar_records_planted_and_harvested_on_the_existing_planting_records(): void
    {
        $farmer = $this->farmer();
        $farm = $this->farms($farmer)->first();

        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-06-12', 'kind' => 'planted', 'farm_id' => $farm->id])
            ->assertRedirect(route('farmer.my-farm', ['month' => '2026-06']).'#activity');
        $plan = FarmerPlantingSchedule::query()->sole();
        $this->assertSame([$farmer->id, $farm->id, '2026-06-12', 'Wet', 'Rainfed', 'Binubusan', '1.50'],
            [$plan->user_id, $plan->farm_id, $plan->planting_date->toDateString(), $plan->season, $plan->farm_type, $plan->barangay, $plan->farm_area_hectares]);

        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-10-05', 'kind' => 'harvested', 'farm_id' => $farm->id])
            ->assertRedirect(route('farmer.my-farm', ['month' => '2026-10']).'#yield-survey');
        $plan->refresh();
        $this->assertSame(['2026-10-05', 'Harvested'], [$plan->harvested_on->toDateString(), $plan->progress_status]);
        $this->assertSame(1, FarmerPlantingSchedule::query()->count(), 'the harvest is set on the same record, not a new one');

        $html = $this->actingAs($farmer)->get(route('farmer.my-farm', ['month' => '2026-10']))->assertOk()->getContent();
        $this->assertStringContainsString('data-fmf-grid', $html);
        $this->assertStringContainsString('data-fmf-list', $html);                                 // the phone's list by month
        $this->assertMatchesRegularExpression('#data-fmf-activity-open="2026-10-05"[^>]*>\s*<span class="fmf-cal-num">5</span>\s*<span class="fmf-cal-event is-harvested">Harvested#', $html);
    }

    public function test_the_calendar_refuses_what_it_cannot_record(): void
    {
        $farmer = $this->farmer();
        $farm = $this->farms($farmer)->first();
        $other = $this->farmer(attributes: ['email' => 'other@example.test']);
        $post = fn (array $data) => $this->actingAs($farmer)->post(route('farmer.activities.store'), $data + ['farm_id' => $farm->id]);

        $post(['date' => '2026-10-05', 'kind' => 'harvested'])->assertSessionHasErrorsIn('activity', ['date' => 'Record the planting on this farm first.']);
        $post(['date' => '2026-10-20', 'kind' => 'harvested'])->assertSessionHasErrorsIn('activity', ['date' => 'A harvest date cannot be in the future.']);
        $post(['date' => '2026-06-12', 'kind' => 'watered'])->assertSessionHasErrorsIn('activity', ['kind']);
        $this->actingAs($farmer)->post(route('farmer.activities.store'), ['date' => '2026-06-12', 'kind' => 'planted', 'farm_id' => $this->farms($other)->first()->id])
            ->assertSessionHasErrorsIn('activity', ['farm_id']);
        $this->assertSame(0, FarmerPlantingSchedule::query()->count());

        $post(['date' => '2026-06-12', 'kind' => 'planted'])->assertSessionHasNoErrors();
        $post(['date' => '2026-06-12', 'kind' => 'planted'])->assertSessionHasErrorsIn('activity', ['date' => 'This planting is already recorded.']);
    }

    public function test_the_calendar_shows_only_the_farmers_own_activities(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(attributes: ['email' => 'other@example.test']);
        $this->planting($other, ['planting_date' => '2026-10-02']);

        $html = $this->actingAs($farmer)->get(route('farmer.my-farm', ['month' => '2026-10']))->assertOk()->getContent();

        $this->assertStringNotContainsString('fmf-cal-event', $html);
        $this->assertStringContainsString('No activities this month.', $html);
    }

    // ------------------------------------------------------------ 8. the actual-harvest question

    public function test_after_a_harvest_the_farmer_is_asked_for_the_actual_yield_next_to_the_predicted_one(): void
    {
        Queue::fake();
        $farmer = $this->farmer();
        $plan = $this->planting($farmer, ['harvested_on' => '2026-10-05', 'progress_status' => 'Harvested', 'predicted_yield' => 4.25, 'prediction_source' => 'iClimate Rice Harvest Estimate', 'prediction_generated_at' => '2026-09-01 00:00:00']);
        $models = MlModel::query()->count();

        $html = $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()->getContent();
        $this->assertStringContainsString('What was your actual harvest?', $html);
        $this->assertMatchesRegularExpression('#data-fmf-predicted>.*?<strong>4\.25 <small>t/ha</small></strong>#s', $html);

        $this->actingAs($farmer)->post(route('farmer.yield-survey.store', $plan->id), ['actual_yield' => '3.9'])->assertRedirect(route('farmer.my-farm').'#harvests');

        $answer = YieldPredictionFeedback::query()->sole();
        $this->assertSame([$farmer->id, $plan->farm_id, $plan->id, 'Wet', 2026, '4.25', '3.90'],
            [$answer->user_id, $answer->farm_id, $answer->farmer_planting_schedule_id, $answer->season, $answer->season_year, $answer->predicted_yield, $answer->actual_yield]);
        $this->assertSame('3.90', $plan->fresh()->actual_yield);
        $this->assertStringNotContainsString('What was your actual harvest?', $this->actingAs($farmer)->get(route('farmer.my-farm'))->getContent());

        // Stored only: nothing retrains. The one job a saved record may queue is the farmer's own estimate.
        $this->assertSame($models, MlModel::query()->count());
        foreach (Queue::pushedJobs() as $class => $jobs) {
            $this->assertSame(PrepareFarmerYieldEstimate::class, $class);
        }
    }

    public function test_the_actual_yield_must_be_a_real_yield_of_the_farmers_own_harvest(): void
    {
        $farmer = $this->farmer();
        $plan = $this->planting($farmer, ['harvested_on' => '2026-10-05', 'progress_status' => 'Harvested']);
        $other = $this->farmer(attributes: ['email' => 'other@example.test']);

        $this->actingAs($farmer)->post(route('farmer.yield-survey.store', $plan->id), ['actual_yield' => '40'])->assertSessionHasErrorsIn('yield_survey', ['actual_yield']);
        $this->actingAs($other)->post(route('farmer.yield-survey.store', $plan->id), ['actual_yield' => '4'])->assertNotFound();
        $this->assertSame(0, YieldPredictionFeedback::query()->count());
    }

    // ------------------------------------------------------------ 6. Payo with nothing to show

    public function test_payo_with_no_advisories_shows_only_the_page_title_to_a_farmer(): void
    {
        $html = $this->actingAs($this->farmer())->get(route('planting-advisories.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Advisories</h1>', $html);
        foreach (['data-adv-empty', 'ic-no-data', 'No data yet', 'No planting advice right now', 'No announcements yet.', 'fad-announcements'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
    }

    // ------------------------------------------------------------ 7. the forecast survey

    private function storeYesterdaysForecast(float $rain = 3.0): void
    {
        ExternalWeatherData::query()->create([
            'source' => 'Open-Meteo', 'location_name' => 'Lian, Batangas', 'latitude' => 14.033, 'longitude' => 120.65, 'forecast_date' => '2026-10-06',
            'weather_code' => 61, 'temperature_max' => 31, 'temperature_min' => 24, 'humidity' => 80, 'rainfall_mm' => $rain, 'precipitation_probability' => 70,
            'wind_speed' => 9, 'fetched_at' => now()->subDay(),
        ]);
    }

    public function test_the_forecast_page_asks_once_about_yesterday_and_saves_the_answer(): void
    {
        $farmer = $this->farmer(attributes: ['preferred_language' => 'fil']);
        $this->storeYesterdaysForecast();

        $html = $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Tama ba ang taya ng panahon kahapon?', $html);
        $this->assertStringContainsString('Mahinang ulan', $html);                                  // what was forecast, in words
        foreach (['Tama', 'Medyo', 'Mali', 'Maaraw', 'Maulap', 'Malakas na ulan', 'Malakas na hangin', 'Laktawan'] as $word) {
            $this->assertStringContainsString($word, $html);
        }
        $this->assertStringNotContainsString(' mm', $this->text($html));

        $this->actingAs($farmer)->postJson(route('farmer.forecast-survey.store'), ['forecast_date' => '2026-10-06', 'accuracy' => 'different', 'observed_condition' => 'Heavy Rain'])
            ->assertOk()->assertJson(['ok' => true, 'message' => 'Salamat sa iyong sagot.']);

        $row = WeatherForecastFeedback::query()->sole();
        $this->assertSame([$farmer->id, 'daily', '2026-10-06', 'Binubusan', 'Light Rain', 'different', 'Heavy Rain'],
            [$row->user_id, $row->forecast_type, $row->forecast_date->toDateString(), $row->barangay, $row->forecast_condition, $row->accuracy, $row->observed_condition]);

        // One answer per day: it is not asked again, and a second answer does not replace the first.
        $this->assertStringNotContainsString('data-forecast-survey', $this->actingAs($farmer)->get(route('weather-predictions.index'))->getContent());
        $this->actingAs($farmer)->postJson(route('farmer.forecast-survey.store'), ['forecast_date' => '2026-10-06', 'accuracy' => 'accurate'])->assertOk();
        $this->assertSame('different', WeatherForecastFeedback::query()->sole()->accuracy);
    }

    public function test_the_forecast_survey_is_only_for_yesterday_and_never_blocks_the_page(): void
    {
        $farmer = $this->farmer();

        // No saved forecast for yesterday: nothing is asked and the page opens as usual.
        $this->assertStringNotContainsString('data-forecast-survey', $this->actingAs($farmer)->get(route('weather-predictions.index'))->assertOk()->getContent());

        $this->storeYesterdaysForecast();
        foreach (['2026-10-07', '2026-10-01'] as $day) {
            $this->actingAs($farmer)->postJson(route('farmer.forecast-survey.store'), ['forecast_date' => $day, 'accuracy' => 'accurate'])->assertStatus(422);
        }
        $this->actingAs($farmer)->postJson(route('farmer.forecast-survey.store'), ['forecast_date' => '2026-10-06', 'accuracy' => 'perfect'])->assertStatus(422);
        $this->assertSame(0, WeatherForecastFeedback::query()->count());

        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
        $this->assertStringNotContainsString('data-forecast-survey', $this->actingAs($mao)->get(route('weather-predictions.index'))->assertOk()->getContent());
    }
}
