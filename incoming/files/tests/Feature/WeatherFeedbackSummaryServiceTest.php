<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Services\Weather\WeatherFeedbackSummaryService as Summary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherFeedbackSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function farmerIn(string $barangay): User
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay]);

        return $user;
    }

    /** @param  array<string, int>  $answers  accuracy => how many farmers */
    private function report(string $barangay, array $answers, ?CarbonImmutable $month = null, array $extra = []): void
    {
        $month ??= CarbonImmutable::now('Asia/Manila')->subMonthNoOverflow()->startOfMonth();
        foreach ($answers as $accuracy => $count) {
            for ($i = 0; $i < $count; $i++) {
                WeatherForecastFeedback::factory()->create([
                    'user_id' => $this->farmerIn($barangay)->id,
                    'barangay' => $barangay,
                    'forecast_date' => $month->toDateString(),
                    'accuracy' => $accuracy,
                    'observed_condition' => $accuracy === 'accurate' ? null : 'Sunny',
                ] + $extra);
            }
        }
    }

    public function test_percentages_and_message_when_enough_reports(): void
    {
        $this->report('Malaruhatan', ['accurate' => 4, 'partly' => 1, 'different' => 1]);

        $summary = app(Summary::class)->summarize();

        $this->assertSame(6, $summary['total']);
        $this->assertTrue($summary['enough_data']);
        $this->assertSame(['accurate' => 67, 'partly' => 17, 'different' => 17], $summary['percentages']);
        $this->assertSame('Most farmers reported that the forecast matched actual conditions.', $summary['message']);
    }

    public function test_below_minimum_reports_shows_not_enough_reports_and_no_percentages(): void
    {
        $this->report('Malaruhatan', ['accurate' => Summary::MIN_REPORTS - 1]);

        $summary = app(Summary::class)->summarize();

        $this->assertFalse($summary['enough_data']);
        $this->assertNull($summary['percentages']);
        $this->assertSame('Not enough farmer reports yet', $summary['message']);
    }

    public function test_single_report_group_is_low_confidence_and_never_treated_as_truth(): void
    {
        $this->report('Malaruhatan', ['different' => 1]);

        $group = app(Summary::class)->summarize()['groups'][0];

        $this->assertTrue($group['low_confidence']);
        $this->assertNull($group['dominant']);
        $this->assertFalse($group['conflicting']);
    }

    public function test_conflicting_group_is_flagged_and_dominant_group_is_not(): void
    {
        $this->report('Malaruhatan', ['accurate' => 2, 'partly' => 1, 'different' => 1]);
        $this->report('Prenza', ['accurate' => 3, 'different' => 1]);

        $groups = collect(app(Summary::class)->summarize()['groups'])->keyBy('barangay');

        $this->assertTrue($groups['Malaruhatan']['conflicting']);
        $this->assertFalse($groups['Prenza']['conflicting']);
        $this->assertSame('accurate', $groups['Prenza']['dominant']);
    }

    public function test_barangay_filter_and_grouping(): void
    {
        $this->report('Malaruhatan', ['accurate' => 3]);
        $this->report('Prenza', ['different' => 2]);

        $summary = app(Summary::class)->summarize(['barangay' => 'Prenza']);

        $this->assertSame(2, $summary['total']);
        $this->assertCount(1, $summary['groups']);
        $this->assertSame('Prenza', $summary['groups'][0]['barangay']);
    }

    public function test_quality_control_excludes_future_wrong_barangay_and_non_farmers(): void
    {
        $this->report('Malaruhatan', ['accurate' => 1]); // valid

        // Period not over yet.
        $this->report('Malaruhatan', ['accurate' => 1], CarbonImmutable::now('Asia/Manila')->startOfMonth());

        // Farmer moved away from the barangay the report was filed under.
        $mover = $this->farmerIn('Prenza');
        WeatherForecastFeedback::factory()->create(['user_id' => $mover->id, 'barangay' => 'Malaruhatan']);

        // Non-farmer author.
        $mao = User::factory()->maoPersonnel()->create(['barangay' => 'Malaruhatan']);
        WeatherForecastFeedback::factory()->create(['user_id' => $mao->id, 'barangay' => 'Malaruhatan']);

        // Non-Lian barangay.
        $stray = $this->farmerIn('Atlantis');
        WeatherForecastFeedback::factory()->create(['user_id' => $stray->id, 'barangay' => 'Atlantis']);

        $this->assertSame(1, app(Summary::class)->summarize()['total']);
    }

    public function test_duplicate_rows_for_the_same_farmer_and_forecast_are_counted_once(): void
    {
        $farmer = $this->farmerIn('Malaruhatan');
        $date = CarbonImmutable::now('Asia/Manila')->subMonthNoOverflow()->startOfMonth()->toDateString();
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id, 'forecast_date' => $date, 'submitted_at' => now()->subDay()]);
        // The unique key normally prevents a second row; simulate legacy/bad data with another forecast type.
        WeatherForecastFeedback::factory()->create(['user_id' => $farmer->id, 'forecast_date' => $date, 'forecast_type' => 'daily']);

        $observations = app(Summary::class)->validatedObservations();

        // 'daily' rows for a past date are valid separate forecasts; the monthly one counts once.
        $this->assertCount(2, $observations);
        $this->assertCount(1, $observations->where('forecast_type', 'monthly'));
    }

    public function test_accurate_observation_equals_forecast_and_snapshot_stays_separate(): void
    {
        $this->report('Malaruhatan', ['accurate' => 1, 'different' => 1]);

        $rows = app(Summary::class)->validatedObservations()->keyBy('accuracy');

        $this->assertSame('Moderate Rain', $rows['accurate']['observed_condition']);
        $this->assertSame('Sunny', $rows['different']['observed_condition']);
        $this->assertSame('Moderate Rain', $rows['different']['forecast']['condition']);
    }

    public function test_mao_dashboard_renders_summary_and_farmer_cannot_see_it(): void
    {
        Cache::clear();
        Config::set('services.weather_api.enabled', false);
        Http::fake();
        $this->report('Malaruhatan', ['accurate' => 5, 'different' => 1]);
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->get(route('mao.operations'))
            ->assertOk()
            ->assertSee('Farmer Weather Feedback')
            ->assertSee('Most farmers reported that the forecast matched actual conditions.')
            ->assertSee('83%')
            ->assertSee('View Details')
            ->assertDontSee('100% accurate');

        $farmer = $this->farmerIn('Malaruhatan');
        $this->actingAs($farmer)->get(route('mao.dashboard'))->assertForbidden();
    }

    public function test_mao_dashboard_says_not_enough_reports_when_below_minimum(): void
    {
        Cache::clear();
        Config::set('services.weather_api.enabled', false);
        Http::fake();
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->get(route('mao.operations'))
            ->assertOk()
            ->assertSee('Not enough farmer reports yet');
    }
}
