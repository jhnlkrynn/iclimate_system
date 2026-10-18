<?php

namespace Tests\Feature;

use App\Models\FarmerOrganization;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\MaoActivityLog;
use App\Models\MaoUserBarangay;
use App\Models\MunicipalYieldPrediction;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Analytics\MaoHomeService;
use App\Services\Analytics\MaoYieldAnalytics;
use App\Services\FarmerOrganizationSummaryService;
use App\Services\Risk\BarangayWeekRiskService;
use App\Support\HarvestTiming;
use App\Support\MaoScope;
use App\Support\YieldUnit;
use Carbon\CarbonImmutable;
use Database\Seeders\MaoPersonnelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The thesis panel's revisions (docs/panel-revisions.md), one group of tests per item. */
class PanelRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00', 'Asia/Manila'));
        config(['iclimate.mao_allow_skip_password_change' => true]);
        $this->seed(MaoPersonnelSeeder::class);
        // The flow itself is tested below; the other tests act as personnel who already chose their password.
        User::query()->where('role', User::ROLE_MAO)->update(['must_change_password' => false]);
    }

    private function mao(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function ofelia(): User
    {
        return $this->mao('ofeliarodil@gmail.com');
    }

    private function jean(): User
    {
        return $this->mao('jeanlaviste@gmail.com');
    }

    private function farmer(string $barangay = 'Prenza', array $profile = []): FarmerProfile
    {
        $user = User::factory()->farmer()->create(['barangay' => $barangay, 'status' => User::STATUS_ACTIVE]);

        return FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay] + $profile);
    }

    private function organization(string $name, string $barangay = 'Prenza'): FarmerOrganization
    {
        return FarmerOrganization::query()->create(['name' => $name, 'barangay' => $barangay, 'farm_type' => 'Irrigated']);
    }

    // ------------------------------------------------------------ 1.1 Multiple farmer organizations

    public function test_a_farmer_can_belong_to_several_organizations_and_is_counted_in_each(): void
    {
        $a = $this->organization('Samahan A');
        $b = $this->organization('Samahan B', 'Kapito');
        $profile = $this->farmer('Prenza', ['farmer_organization_id' => $a->id]);

        $this->assertSame([$a->id], $profile->fresh()->farmer_organization_ids, 'the primary organization is a membership');

        $profile->syncOrganizations([$a->id, $b->id]);
        $profile->refresh();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $profile->farmer_organization_ids);
        $this->assertSame($a->id, $profile->farmer_organization_id, 'the primary stays');
        $this->assertSame('Samahan A, Samahan B', $profile->organization_names);

        Cache::flush();
        $chart = app(FarmerOrganizationSummaryService::class)->summarize()['farmerOrganizationChart'];
        $counts = array_combine($chart['labels'], $chart['counts']);
        $this->assertSame(1, $counts['Samahan A']);
        $this->assertSame(1, $counts['Samahan B']);

        // The MAO list's organization filter finds the farmer under either organization.
        foreach ([$a, $b] as $organization) {
            $this->actingAs($this->ofelia())->get(route('farmer-profiles.index', ['farmer_organization_id' => $organization->id]))
                ->assertOk()->assertSee($profile->full_name);
        }
    }

    public function test_a_farmer_ticks_their_organizations_on_the_profile_page(): void
    {
        $a = $this->organization('Samahan A');
        $b = $this->organization('Samahan B');
        $profile = $this->farmer();
        $user = $profile->user;

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'farmer_organization_ids_present' => 1, 'farmer_organization_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $profile->fresh()->farmer_organization_ids);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Samahan A')->assertSee('data-farmer-organizations', false);
    }

    // ------------------------------------------------------------ 1.2 Yield in t/ha

    public function test_yields_convert_to_tons_per_hectare_with_the_configured_sack_weight(): void
    {
        config(['yield.sack_kg' => 50]);
        $this->assertSame(4.0, YieldUnit::toTonsPerHectare(160, YieldUnit::SACKS, 2));     // 160 × 50 kg = 8 t ÷ 2 ha
        $this->assertSame(3.5, YieldUnit::toTonsPerHectare(7000, YieldUnit::KG, 2));
        $this->assertSame(4.33, YieldUnit::toTonsPerHectare(13, YieldUnit::TONS, 3));
        $this->assertNull(YieldUnit::toTonsPerHectare(13, YieldUnit::TONS, 0));
        $this->assertSame('4.25 t/ha', YieldUnit::format(4.249));

        config(['yield.sack_kg' => 40]);
        $this->assertSame(3.2, YieldUnit::toTonsPerHectare(160, YieldUnit::SACKS, 2));
    }

    public function test_a_production_record_entered_in_sacks_is_stored_in_tons_with_its_yield_in_t_ha(): void
    {
        $this->actingAs($this->ofelia())->post(route('rice-productions.store'), [
            'barangay' => 'Prenza', 'season' => 'Wet', 'irrigation_type' => 'Irrigated', 'year' => 2026, 'production_month' => '2026-09',
            'area_hectares' => 2.5, 'area_harvested_hectares' => 2, 'total_production' => 160, 'total_production_unit' => 'sacks',
        ])->assertSessionHasNoErrors();

        $record = RiceProduction::query()->latest('id')->firstOrFail();
        $this->assertEquals(8.0, (float) $record->total_production);
        $this->assertEquals(4.0, (float) $record->yield_per_hectare);
    }

    // ------------------------------------------------------------ 1.3 Early / late harvest reason

    public function test_harvest_timing_is_on_time_early_or_late_and_early_or_late_needs_a_reason(): void
    {
        $this->assertSame(HarvestTiming::ON_TIME, HarvestTiming::classify('2026-10-20', '2026-10-22'));
        $this->assertSame(HarvestTiming::EARLY, HarvestTiming::classify('2026-10-20', '2026-10-05'));
        $this->assertSame(HarvestTiming::LATE, HarvestTiming::classify('2026-10-20', '2026-11-02'));

        $profile = $this->farmer();
        $plan = ['planting_date' => '2026-06-01', 'scheduled_harvest_date' => '2026-09-24', 'season' => 'Wet', 'farm_type' => 'Irrigated',
            'barangay' => 'Prenza', 'harvested_on' => '2026-09-10', 'actual_yield' => 4.1];

        $this->actingAs($profile->user)->post(route('farmer.planting-schedules.store'), $plan)
            ->assertSessionHasErrors('harvest_timing_reason');
        $this->assertSame(0, FarmerPlantingSchedule::query()->count());

        $this->actingAs($profile->user)->post(route('farmer.planting-schedules.store'), $plan + ['harvest_timing_reason' => 'other', 'harvest_timing_reason_other' => 'Waited for the harvester'])
            ->assertSessionHasNoErrors();
        $saved = FarmerPlantingSchedule::query()->firstOrFail();
        $this->assertSame('Early', $saved->harvest_timing);
        $this->assertSame('other', $saved->harvest_timing_reason);
        $this->assertSame('Early (14 days) – Waited for the harvester', $saved->harvestTimingSummary('en'));

        // The MAO sees it on the planner.
        $this->actingAs($this->ofelia())->get(route('mao.planner.index'))->assertOk()->assertSee('Waited for the harvester');
    }

    public function test_an_on_time_harvest_needs_no_reason(): void
    {
        $profile = $this->farmer();
        $this->actingAs($profile->user)->post(route('farmer.planting-schedules.store'), [
            'planting_date' => '2026-06-01', 'scheduled_harvest_date' => '2026-09-24', 'season' => 'Wet', 'farm_type' => 'Irrigated',
            'barangay' => 'Prenza', 'harvested_on' => '2026-09-25', 'actual_yield' => 4.1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('On time', FarmerPlantingSchedule::query()->value('harvest_timing'));
    }

    // ------------------------------------------------------------ 1.4 Live search

    public function test_live_farmer_search_matches_parts_of_name_id_barangay_and_organization_in_any_case(): void
    {
        $org = $this->organization('Lumang Tubigan Farmers Association', 'Kapito');
        $juan = $this->farmer('Kapito', ['full_name' => 'Juan Dela Cruz', 'rsbsa_no' => '04-10-13-012-20001', 'farmer_organization_id' => $org->id]);
        $this->farmer('Prenza', ['full_name' => 'Maria Santos']);

        $search = fn (User $as, string $q) => $this->actingAs($as)->getJson(route('farmers.search', ['q' => $q]))->assertOk()->json('results');

        foreach (['dela', 'DELA CRUZ', '20001', 'kapito', 'tubigan', 'juan kapito'] as $q) {
            $results = $search($this->ofelia(), $q);
            $this->assertSame(['Juan Dela Cruz'], array_column($results, 'title'), $q);
        }
        $this->assertStringContainsString('Lumang Tubigan Farmers Association', $search($this->ofelia(), 'juan')[0]['meta']);
        $this->assertSame([], $search($this->ofelia(), 'zzz'));

        // A technologist finds only the farmers of their barangays (Jean: Kapito, Malaruhatan, Bungahan, San Diego).
        $this->assertSame([], $search($this->jean(), 'maria'));
        $this->assertCount(1, $search($this->jean(), 'juan'));

        // Farmers cannot search other farmers.
        $this->actingAs($juan->user)->getJson(route('farmers.search', ['q' => 'maria']))->assertForbidden();
    }

    // ------------------------------------------------------------ 2.1 Weather risk levels

    public function test_the_heat_map_rates_four_weather_risk_levels(): void
    {
        $day = fn (float $rain, float $wind = 10, float $temp = 31): array => ['date' => '2026-10-11', 'rainfall' => $rain, 'wind_speed_max' => $wind, 'temperature_max' => $temp];

        $this->assertSame('low', BarangayWeekRiskService::rate([$day(2), $day(3)], 'Irrigated')['level']);
        $this->assertSame('moderate', BarangayWeekRiskService::rate([$day(30)], 'Irrigated')['level']);
        $this->assertSame('high', BarangayWeekRiskService::rate([$day(60)], 'Irrigated')['level']);
        $severe = BarangayWeekRiskService::rate([$day(120)], 'Irrigated');
        $this->assertSame('severe', $severe['level']);
        $this->assertSame('heavy_rain', $severe['factor']['key']);
        $this->assertSame('severe', BarangayWeekRiskService::rate([$day(2, 95)], 'Irrigated')['level']);

        // The thresholds come from config/weather_risk.php.
        config(['weather_risk.thresholds.extreme_rain_mm' => 150.0]);
        $this->assertSame('high', BarangayWeekRiskService::rate([$day(120)], 'Irrigated')['level']);
    }

    // ------------------------------------------------------------ 3.1 / 3.2 / 4.1 / 4.2 MAO dashboard

    public function test_total_farmers_counts_every_registered_farmer_account_and_is_labelled_when_filtered(): void
    {
        $this->farmer('Prenza');
        $this->farmer('Kapito');
        User::factory()->farmer()->create(['barangay' => 'Kapito', 'status' => User::STATUS_PENDING]);   // no profile yet: still a farmer
        User::factory()->farmer()->create(['barangay' => 'Kapito', 'status' => User::STATUS_REJECTED]);  // rejected sign-up: not counted

        $all = app(MaoHomeService::class)->payload();
        $this->assertSame(3, $all['farmer_kpi']['total']);
        $this->assertSame(1, $all['farmer_kpi']['pending']);
        $this->assertFalse($all['farmer_kpi']['filtered']);

        $jean = app(MaoHomeService::class)->payload($this->jean()->coveredBarangays());
        $this->assertSame(2, $jean['farmer_kpi']['total']);
        $this->assertTrue($jean['farmer_kpi']['filtered']);
        $this->assertSame(3, $jean['farmer_kpi']['all_total']);

        Cache::flush();
        $this->actingAs($this->jean())->get(route('mao.dashboard'))->assertOk()
            ->assertSee('Total farmers (filtered)')->assertSee('All of Lian: 3');

        // The municipal view shows the full total (read-only for the technologist).
        $this->actingAs($this->jean())->post(route('mao.view.switch'), ['view' => MaoScope::VIEW_MUNICIPAL])->assertRedirect();
        Cache::flush();
        $this->actingAs($this->jean())->get(route('mao.dashboard'))->assertOk()->assertDontSee('Total farmers (filtered)');
    }

    public function test_the_chart_filter_refuses_a_start_year_after_the_end_year(): void
    {
        $this->actingAs($this->ofelia())->get(route('mao.dashboard', ['from' => 2025, 'to' => 2021]))->assertOk()
            ->assertSee('The start year (2025) cannot be after the end year (2021).');
    }

    public function test_predicted_vs_actual_reports_the_difference_and_mape(): void
    {
        foreach ([[2024, 6, 4.0, 4.4, 'TRAIN'], [2025, 6, 3.6, 4.0, 'TEST'], [2025, 7, 4.2, 4.0, 'TEST']] as [$year, $month, $predicted, $actual, $split]) {
            MunicipalYieldPrediction::query()->create(['year' => $year, 'month' => $month, 'season' => 'Wet', 'predicted_yield' => $predicted,
                'actual_yield' => $actual, 'split' => $split, 'model' => 'Test model']);
        }

        $result = app(MaoYieldAnalytics::class)->predictedActual();
        $this->assertSame('ok', $result['state']);
        $this->assertSame(3, $result['overall']['count']);
        $this->assertSame(-0.2, $result['overall']['difference']);
        $this->assertSame(2, $result['test']['count']);
        $this->assertSame(7.5, $result['test']['mape']);   // (10% + 5%) / 2
        $this->assertSame(92.5, $result['test']['accuracy']);
    }

    // ------------------------------------------------------------ 4.4 MAO personnel, coverage, stamps, log

    public function test_the_mao_personnel_of_lian_are_seeded_with_their_positions_and_barangays(): void
    {
        $this->assertTrue($this->ofelia()->isMaoHead());
        $this->assertSame('Municipal Agriculturist', $this->ofelia()->position);
        $this->assertSame(['Bungahan', 'Kapito', 'Malaruhatan', 'San Diego'], $this->jean()->coveredBarangays());
        $this->assertEquals(245.15, (float) $this->jean()->area_covered_ha);
        $this->assertSame('Tan-ag', MaoUserBarangay::query()->where('user_id', $this->mao('lodivicocachuela@gmail.com')->id)->where('barangay', 'San Diego')->value('sitio'));
        $this->assertSame(['Prenza'], $this->mao('evelynmayari@gmail.com')->coveredBarangays());

        // The farmer side: "Your Agricultural Technologist: …" by barangay (and sitio).
        $this->assertSame(['Jean L. Laviste'], User::maoPersonnelFor('San Diego')->pluck('name')->all());
        $this->assertSame(['Lodivico A. Cachuela'], User::maoPersonnelFor('San Diego', 'Tan-ag')->pluck('name')->all());
        $profile = $this->farmer('Prenza');
        $this->actingAs($profile->user)->get(route('profile.edit'))->assertOk()
            ->assertSee('Evelyn M. Mayari')->assertSee('Ofelia V. Rodil');
    }

    public function test_the_header_names_the_signed_in_mao_user_and_their_coverage(): void
    {
        Cache::flush();
        $this->actingAs($this->jean())->get(route('mao.dashboard'))->assertOk()
            ->assertSee('Jean L. Laviste')->assertSee('Agricultural Technologist')
            ->assertSee('Bungahan, Kapito, Malaruhatan, San Diego')
            ->assertSee('Municipality of Lian');
    }

    public function test_only_the_municipal_agriculturist_manages_mao_accounts_and_coverage(): void
    {
        $jean = $this->jean();
        $payload = ['name' => $jean->name, 'position' => $jean->position, 'station_type' => 'Municipal', 'area_covered_ha' => 250,
            'status' => 'Active', 'barangays' => ['Kapito', 'Cumba']];

        $this->actingAs($jean)->put(route('mao.personnel.update', $jean), $payload)->assertForbidden();
        $this->actingAs($this->ofelia())->put(route('mao.personnel.update', $jean), $payload)->assertRedirect();

        $this->assertSame(['Cumba', 'Kapito'], $jean->fresh()->coveredBarangays());
        $this->assertEquals(250, (float) $jean->fresh()->area_covered_ha);
        $this->assertTrue(MaoActivityLog::query()->where('user_id', $this->ofelia()->id)->where('action', 'edited')->exists());
    }

    public function test_a_technologist_changes_only_records_of_their_barangays_and_every_change_is_stamped_and_logged(): void
    {
        $inside = $this->farmer('Kapito');
        $outside = $this->farmer('Prenza');
        $jean = $this->jean();

        // The list shows their barangays only.
        $this->actingAs($jean)->get(route('farmer-profiles.index'))->assertOk()
            ->assertSee($inside->full_name)->assertDontSee($outside->full_name)->assertSee('Showing records for your barangays');

        $update = fn (FarmerProfile $p) => ['user_id' => $p->user_id, 'full_name' => $p->full_name.' Jr.', 'barangay' => $p->barangay, 'farm_type' => $p->farm_type, 'farm_area' => 1.5];
        $this->actingAs($jean)->put(route('farmer-profiles.update', $outside), $update($outside))->assertForbidden();   // outside their coverage: read-only
        $this->actingAs($jean)->put(route('farmer-profiles.update', $inside), $update($inside))->assertRedirect();

        $inside->refresh();
        $this->assertSame($jean->id, (int) $inside->updated_by);
        $log = MaoActivityLog::query()->where('user_id', $jean->id)->latest('id')->firstOrFail();
        $this->assertSame('edited', $log->action);
        $this->assertSame('Kapito', $log->barangay);
        $this->assertSame('Agricultural Technologist', $log->user_position);

        $this->actingAs($this->ofelia())->get(route('farmer-profiles.show', $inside))->assertOk()->assertSee('Edited by:')->assertSee('Jean L. Laviste');
    }

    public function test_the_activity_log_shows_everyone_to_the_head_and_only_their_own_to_others(): void
    {
        MaoActivityLog::query()->create(['user_id' => $this->jean()->id, 'user_name' => 'Jean L. Laviste', 'user_position' => 'Agricultural Technologist',
            'action' => 'verified', 'description' => 'Validated record', 'record_type' => 'Rice Production', 'barangay' => 'Kapito']);
        MaoActivityLog::query()->create(['user_id' => $this->mao('evelynmayari@gmail.com')->id, 'user_name' => 'Evelyn M. Mayari',
            'user_position' => 'Administrative Aide I', 'action' => 'created', 'description' => 'Created Farmer Profile', 'barangay' => 'Prenza']);

        $this->actingAs($this->ofelia())->get(route('mao.activity-log.index'))->assertOk()->assertSee('Jean L. Laviste')->assertSee('Evelyn M. Mayari');
        $this->actingAs($this->ofelia())->get(route('mao.activity-log.index', ['barangay' => 'Prenza']))->assertOk()->assertDontSee('Validated record');
        $this->actingAs($this->jean())->get(route('mao.activity-log.index'))->assertOk()->assertSee('Validated record')->assertDontSee('Evelyn M. Mayari');
    }

    // ------------------------------------------------------------ 4.4 A accounts, default password, first sign-in

    public function test_the_four_mao_accounts_sign_in_with_the_default_password_stored_hashed_and_must_change_it(): void
    {
        User::query()->where('role', User::ROLE_MAO)->update(['must_change_password' => true]);
        $expected = ['ofeliarodil@gmail.com' => 'Admin', 'jeanlaviste@gmail.com' => 'MAO', 'lodivicocachuela@gmail.com' => 'MAO', 'evelynmayari@gmail.com' => 'MAO'];
        foreach ($expected as $email => $role) {
            $user = $this->mao($email);
            $this->assertNotSame('Password@123', $user->getRawOriginal('password'));
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Password@123', $user->password), $email);
            $this->assertSame($role, $user->maoRoleLabel());
        }

        // Signed in, every page sends them to "Change your password" until they do (or skip, for testing).
        $jean = $this->jean()->fresh();
        $this->actingAs($jean)->get(route('mao.dashboard'))->assertRedirect(route('password.first-change'));
        $this->actingAs($jean)->get(route('password.first-change'))->assertOk()->assertSee('Skip for now');

        $this->actingAs($jean)->post(route('password.first-change.update'), ['password' => 'Password@123', 'password_confirmation' => 'Password@123'])
            ->assertSessionHasErrors('password');   // not the default again
        $this->actingAs($jean)->post(route('password.first-change.update'), ['password' => 'Kapito-Rice-2026', 'password_confirmation' => 'Kapito-Rice-2026'])
            ->assertRedirect(route('mao.dashboard'));
        $this->assertFalse($jean->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Kapito-Rice-2026', $jean->fresh()->password));
        $this->actingAs($jean->fresh())->get(route('mao.dashboard'))->assertOk();
    }

    public function test_skip_lasts_for_the_session_and_can_be_turned_off(): void
    {
        $evelyn = $this->mao('evelynmayari@gmail.com');
        $evelyn->forceFill(['must_change_password' => true])->save();

        $this->actingAs($evelyn)->post(route('password.first-change.skip'))->assertRedirect(route('mao.dashboard'));
        $this->actingAs($evelyn)->get(route('mao.dashboard'))->assertOk();
        $this->assertTrue($evelyn->fresh()->must_change_password, 'still asked at the next sign-in');

        config(['iclimate.mao_allow_skip_password_change' => false]);
        $this->flushSession();
        $this->actingAs($evelyn)->post(route('password.first-change.skip'))->assertForbidden();
    }

    public function test_the_admin_adds_an_account_with_a_generated_email_and_resets_passwords_to_the_default(): void
    {
        $this->actingAs($this->ofelia())->post(route('mao.personnel.store'), [
            'name' => 'Jean B. Laviste', 'position' => 'Agricultural Technologist', 'station_type' => 'Municipal', 'barangays' => ['Cumba'],
        ])->assertSessionHasNoErrors();
        $new = User::query()->where('name', 'Jean B. Laviste')->firstOrFail();
        $this->assertSame('jeanlaviste2@gmail.com', $new->email);   // jeanlaviste@gmail.com is taken
        $this->assertTrue($new->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Password@123', $new->password));
        $this->assertSame(['Cumba'], $new->coveredBarangays());

        // Duplicate e-mails are refused.
        $this->actingAs($this->ofelia())->post(route('mao.personnel.store'), [
            'name' => 'Someone Else', 'email' => 'evelynmayari@gmail.com', 'position' => 'Agricultural Technologist', 'station_type' => 'Municipal',
        ])->assertSessionHasErrors('email');

        // Reset to the default password: Admin only.
        $jean = $this->jean();
        $jean->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('My-Own-Pass-1')])->save();
        $this->actingAs($jean)->post(route('mao.personnel.reset-password', $jean))->assertForbidden();
        $this->actingAs($this->ofelia())->post(route('mao.personnel.reset-password', $jean))->assertRedirect();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Password@123', $jean->fresh()->password));
        $this->assertTrue($jean->fresh()->must_change_password);

        // Deactivate.
        $this->actingAs($this->ofelia())->put(route('mao.personnel.update', $new), ['name' => $new->name, 'position' => $new->position,
            'station_type' => 'Municipal', 'status' => 'Inactive', 'barangays' => ['Cumba']])->assertRedirect();
        $this->assertSame('Inactive', $new->fresh()->status);
    }

    // ------------------------------------------------------------ 4.5 white government theme

    public function test_every_signed_in_page_uses_the_white_government_theme(): void
    {
        Cache::flush();
        $html = $this->actingAs($this->ofelia())->get(route('mao.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString("setAttribute('data-theme','light')", $html);
        $this->assertStringNotContainsString("setAttribute('data-theme','dark')", $html);
        $this->assertStringNotContainsString('theme-dark-pages', $html);
        $this->assertStringNotContainsString('gov-strip', $html);
        $this->assertStringContainsString('Municipal Agriculture Office', $html);
        $this->assertStringContainsString('#2E7D32', file_get_contents(resource_path('css/gov-theme.css')));
    }
}
