<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\MaoUserBarangay;
use App\Models\RiceVariety;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\PlantingSchedulePredictionService;
use App\Services\RiceVarieties\RiceVarietyImporter;
use App\Support\RiceVarieties\SourceDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RiceVarietiesTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURE = 'tests/fixtures/nsic-rice-sample.xlsx';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-01 02:00:00', 'UTC'));       // 10:00 AM, Aug 1 in Lian
        Storage::fake('public');                                                // the import publishes the source PDF there
        // The yield model is slow and external; saving must not depend on it.
        $this->mock(PlantingSchedulePredictionService::class, fn ($mock) => $mock->shouldReceive('refresh')->andReturnUsing(fn ($schedule) => $schedule));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function farmer(string $barangay = 'Kapito', string $name = 'Juan Dela Cruz'): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => $barangay, 'name' => $name]);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => $barangay, 'farm_area' => 1.5, 'farm_type' => 'Irrigated']);

        return $user;
    }

    private function plan(User $farmer, array $attributes = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($attributes + [
            'user_id' => $farmer->id, 'planting_date' => '2026-07-01', 'scheduled_harvest_date' => '2026-10-24',
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => $farmer->barangay, 'farm_area_hectares' => 1.5,
        ]);
    }

    private function form(array $change = []): array
    {
        return $change + [
            'planting_date' => '2026-07-01', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Kapito',
            'farm_area_hectares' => 1.5, 'crop_establishment' => 'Transplanted', 'date_basis' => 'sowing',
        ];
    }

    // ------------------------------------------------------------------ importer

    public function test_the_importer_reads_the_nsic_sheet_and_a_second_run_changes_nothing(): void
    {
        $first = app(RiceVarietyImporter::class)->import(base_path(self::FIXTURE));

        $this->assertSame(['inserted' => 5, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'no_maturity' => 2, 'no_tpr_maturity' => 2, 'rows' => 5,
            'irrigated' => 2, 'rainfed' => 1, 'both' => 0, 'not_specified' => 2], $first);

        $mestiso = RiceVariety::query()->where('nsic_reg_no', 'NSIC 2026 Rc 824H')->firstOrFail();
        $this->assertSame('Mestiso 176 (RTP750FP)', $mestiso->variety_name);                        // leading space trimmed
        $this->assertSame([109, 100, 120], [$mestiso->maturity_tpr_ds, $mestiso->maturity_tpr_ds_min, $mestiso->maturity_tpr_ds_max]);
        $this->assertSame([108, 103, 113], [$mestiso->maturity_tpr_ws, $mestiso->maturity_tpr_ws_min, $mestiso->maturity_tpr_ws_max]);
        // One merged cell holds the yield of both seasons: its regional numbers are kept and averaged.
        $this->assertSame([8309, 8309], [$mestiso->yield_tpr_ds, $mestiso->yield_tpr_ws]);
        $this->assertSame([7888, 8729], array_column($mestiso->yield_values['tpr_ds']['values'], 'kg'));
        $this->assertSame('13.1', (string) $mestiso->amylose_content);                              // 0.131 → 13.1 %
        $this->assertSame(['Irrigated'], $mestiso->farmTypes());
        // "WSB - I; YSB - MR" is two results.
        $this->assertSame([['White Stem Borer', 'I'], ['Yellow Stem Borer', 'MR']], array_map(fn ($e) => [$e['label'], $e['code']], $mestiso->reactions['pests']['whiteheads']['entries']));
        $this->assertSame(['Induced', 'Modified'], array_column($mestiso->reactions['diseases']['tungro']['entries'], 'label'));

        $this->assertSame('Hybrid', RiceVariety::query()->where('nsic_reg_no', 'NSIC 2017 Rc 486H')->value('variety_type'));   // "Hybird"
        $rc480 = RiceVariety::query()->where('nsic_reg_no', 'NSIC 2016 Rc 480')->firstOrFail();
        $this->assertSame([107, 117, 115, 118], [$rc480->maturity_tpr_ws, $rc480->maturity_seed_production, $rc480->maturity_seed_production_min, $rc480->maturity_seed_production_max]);
        $this->assertSame(['Rainfed'], $rc480->farmTypes());                // "rainfed lowland and saline prone": saline stays in the text
        $this->assertSame([], RiceVariety::query()->where('variety_name', 'Tjeremas')->firstOrFail()->farmTypes());   // "Early Lowland": not specified
        // The two "Philippine Seed Board" rows are told apart by name and year.
        $this->assertSame(['raminad strain 1|1955', 'tjeremas|1955'], RiceVariety::query()->where('nsic_reg_no', 'Philippine Seed Board')->orderBy('import_key')->pluck('import_key')->all());

        $second = app(RiceVarietyImporter::class)->import(base_path(self::FIXTURE));
        $this->assertSame([0, 0, 5], [$second['inserted'], $second['updated'], $second['unchanged']]);
        $this->assertSame(5, RiceVariety::query()->count());
    }

    public function test_the_importer_never_overwrites_a_variety_corrected_by_hand(): void
    {
        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])->assertSuccessful();
        RiceVariety::query()->where('nsic_reg_no', 'NSIC 2016 Rc 480')->update(['variety_name' => 'GSR 8 (corrected)', 'source' => RiceVariety::SOURCE_MANUAL]);

        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])
            ->expectsTable(['Inserted', 'Updated', 'Unchanged', 'Skipped (manual)', 'No maturity data', 'No transplanted maturity'], [[0, 0, 4, 1, 2, 2]])
            ->assertSuccessful();
        $this->assertSame('GSR 8 (corrected)', RiceVariety::query()->where('nsic_reg_no', 'NSIC 2016 Rc 480')->value('variety_name'));
    }

    public function test_the_import_command_fails_cleanly_on_a_missing_file(): void
    {
        $this->artisan('rice-varieties:import', ['path' => base_path('tests/fixtures/missing.xlsx')])->assertFailed();
    }

    // ------------------------------------------------------------------ pages by role

    public function test_a_farmer_sees_their_planted_variety_and_the_catalog_but_no_staff_sections(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();
        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $this->form(['rice_variety_id' => $variety->id]))->assertRedirect();

        $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('data-rv-mine', false)
            ->assertSee('GSR 8')
            ->assertSee('Oct 16, 2026')                                                 // Jul 1 + 107 days
            ->assertSee('Based on Wet-season transplanted trials.')
            ->assertSee('data-rv-open="'.$variety->id.'"', false)
            ->assertDontSee('data-rv-planted', false)
            ->assertDontSee('data-rv-add', false)
            ->assertDontSee('data-rv-prompt', false);
    }

    public function test_a_farmer_with_a_plan_but_no_variety_is_asked_which_rice_they_planted(): void
    {
        $farmer = $this->farmer();
        $plan = $this->plan($farmer);
        $variety = RiceVariety::factory()->rc480()->create();

        $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('data-rv-prompt', false)
            ->assertSee('Which rice did you plant?')
            ->assertSee(route('farmer.planting-schedules.edit', $plan), false)
            ->assertDontSee('data-rv-mine', false);

        $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', $plan))->assertOk()
            ->assertSee('name="rice_variety_id"', false)
            ->assertSee('NSIC Rc 480 (GSR 8)');

        $this->actingAs($farmer)->put(route('farmer.planting-schedules.update', $plan), $this->form([
            'rice_variety_id' => $variety->id,
        ]))->assertRedirect(route('farmer.my-farm'));

        $this->assertSame($variety->id, $plan->fresh()->rice_variety_id);
        $this->assertSame('2026-10-16', $plan->fresh()->scheduled_harvest_date->toDateString());
    }

    public function test_a_missing_or_foreign_planting_schedule_does_not_open_the_edit_form(): void
    {
        $farmer = $this->farmer();
        $otherFarmer = $this->farmer('Prenza', 'Other Farmer');
        $otherPlan = $this->plan($otherFarmer);

        $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', 999999))->assertNotFound();
        $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', $otherPlan))->assertForbidden();
    }

    public function test_the_farmer_page_follows_the_farmers_language(): void
    {
        $farmer = $this->farmer();
        $farmer->forceFill(['preferred_language' => 'fil'])->save();
        RiceVariety::factory()->create();

        $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertOk()->assertSee('Mga Binhi ng Palay')->assertSee('Listahan ng binhi');
    }

    public function test_mao_sees_the_catalog_and_the_planted_overview_but_cannot_add(): void
    {
        RiceVariety::factory()->create();
        $mao = User::factory()->maoPersonnel()->create();

        $this->actingAs($mao)->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('data-rv-planted', false)
            ->assertSee('Planted in my barangays')
            ->assertSee('class="rv-cards"', false)
            ->assertDontSee('data-rv-add', false)
            ->assertDontSee('data-rv-mine', false);
    }

    public function test_it_sees_the_seed_table_with_add_and_no_farmer_or_mao_sections(): void
    {
        RiceVariety::factory()->create();
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->get(route('rice-varieties.index'))->assertOk()
            ->assertViewIs('rice-varieties.it-index')
            ->assertSee('data-rv-it-table', false)
            ->assertSee('data-rv-add', false)
            ->assertDontSee('family=Sora', false)                                   // Inter, like the other IT pages
            ->assertDontSee('data-rv-planted', false)
            ->assertDontSee('data-rv-mine', false);
    }

    public function test_every_role_has_the_menu_item(): void
    {
        foreach ([$this->farmer(), User::factory()->maoPersonnel()->create(), User::factory()->itExpert()->create()] as $user) {
            $this->actingAs($user)->get(route('rice-varieties.index'))->assertOk()
                ->assertSee('<a class="sidebar-link active" href="'.route('rice-varieties.index').'" aria-current="page">', false);
        }
    }

    public function test_farmers_and_mao_cannot_add_edit_or_switch_varieties(): void
    {
        $variety = RiceVariety::factory()->create();
        foreach ([$this->farmer(), User::factory()->maoPersonnel()->create()] as $user) {
            $this->actingAs($user)->get(route('rice-varieties.create'))->assertForbidden();
            $this->actingAs($user)->post(route('rice-varieties.store'), ['variety_name' => 'X'])->assertForbidden();
            $this->actingAs($user)->get(route('rice-varieties.edit', $variety))->assertForbidden();
            $this->actingAs($user)->put(route('rice-varieties.update', $variety), ['variety_name' => 'X'])->assertForbidden();
            $this->actingAs($user)->patch(route('rice-varieties.toggle', $variety))->assertForbidden();
        }
        $this->assertTrue($variety->fresh()->is_active);
        auth()->logout();
        $this->get(route('rice-varieties.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------ source list (PDF) and last import

    public function test_every_role_sees_the_source_line_and_it_sees_the_last_import(): void
    {
        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])->assertSuccessful();
        Storage::disk('public')->assertExists(SourceDocument::PUBLIC_PATH);

        foreach ([$this->farmer(), User::factory()->maoPersonnel()->create(), User::factory()->itExpert()->create()] as $user) {
            $page = $this->actingAs($user)->get(route('rice-varieties.index'))->assertOk()
                ->assertSee('Source: NSIC Registered Rice Varieties (Oryza sativa L.)')
                ->assertDontSee('View source list');
            $user->role === User::ROLE_IT_EXPERT
                ? $page->assertSee('Last imported: Aug 1, 2026, 5 varieties')
                : $page->assertDontSee('data-rv-last-import', false);
        }
        $this->assertSame(1, DB::table('rice_variety_imports')->count());
        $this->assertSame([5, 5, 0], [DB::table('rice_variety_imports')->value('rows'), DB::table('rice_variety_imports')->value('inserted'), DB::table('rice_variety_imports')->value('updated')]);
    }

    public function test_before_any_import_it_says_not_imported_yet(): void
    {
        RiceVariety::factory()->create();

        $this->actingAs(User::factory()->itExpert()->create())->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('Source: NSIC Registered Rice Varieties (Oryza sativa L.)')
            ->assertDontSee('data-rv-source-link', false)
            ->assertSee('Not imported yet');
    }

    // ------------------------------------------------------------------ MAO overview

    public function test_mao_sees_only_the_farmers_of_their_barangays(): void
    {
        $variety = RiceVariety::factory()->rc480()->create();
        $inside = $this->farmer('Kapito', 'Inside Farmer');
        $outside = $this->farmer('Prenza', 'Outside Farmer');
        $this->plan($inside, ['rice_variety_id' => $variety->id, 'crop_establishment' => 'Transplanted']);
        $this->plan($outside);

        $mao = User::factory()->maoPersonnel()->create();
        MaoUserBarangay::query()->create(['user_id' => $mao->id, 'barangay' => 'Kapito']);

        $this->actingAs($mao)->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('Inside Farmer')
            ->assertDontSee('Outside Farmer')
            ->assertSee('NSIC Rc 480 (GSR 8)')
            ->assertSee('data-rv-tile="with">1<', false)
            ->assertSee('data-rv-tile="without">0<', false);

        // The Municipal Agriculturist covers all of Lian.
        $head = User::factory()->maoPersonnel()->create(['is_mao_head' => true]);
        $this->actingAs($head)->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('Inside Farmer')->assertSee('Outside Farmer')
            ->assertSee('data-rv-tile="without">1<', false);
    }

    // ------------------------------------------------------------------ expected harvest date

    public function test_saving_a_plan_with_a_variety_writes_the_variety_based_harvest_date(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();

        // Wet season, transplanted, sowing date: Jul 1 + 107 days (NSIC Rc 480, TPR WS).
        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $this->form(['rice_variety_id' => $variety->id]))
            ->assertRedirect(route('farmer.my-farm'));
        $plan = FarmerPlantingSchedule::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-10-16', $plan->scheduled_harvest_date->toDateString());
        $this->assertFalse($plan->harvest_date_manual);

        // A transplanting date: 107 days after sowing minus 21 seedling days.
        $this->actingAs($farmer)->put(route('farmer.planting-schedules.update', $plan), $this->form([
            'rice_variety_id' => $variety->id, 'date_basis' => 'transplanting', 'seedling_age_days' => 21,
            'scheduled_harvest_date' => '2026-10-16',
        ]))->assertRedirect();
        $this->assertSame('2026-09-25', $plan->fresh()->scheduled_harvest_date->toDateString());
    }

    public function test_the_variety_date_shows_on_the_dashboard_my_farm_and_the_calendar(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();
        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $this->form(['rice_variety_id' => $variety->id]))->assertRedirect();

        $this->actingAs($farmer)->get(route('farmer.my-farm'))->assertOk()
            ->assertSee('Oct 16, 2026')
            ->assertSee('<small class="fmf-variety d-block" data-harvest-variety>NSIC Rc 480 (GSR 8)</small>', false);
        $this->actingAs($farmer)->get(route('farmer.my-farm', ['month' => '2026-10']))->assertOk()->assertSee('data-harvest-variety>NSIC Rc 480 (GSR 8)', false);
        $this->actingAs($farmer)->get(route('calendar.index'))->assertOk()->assertSee('<dd data-harvest-variety>NSIC Rc 480 (GSR 8)</dd>', false);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))->assertOk()->assertSee('NSIC Rc 480 (GSR 8)');
    }

    public function test_a_plan_without_a_variety_keeps_the_115_day_date(): void
    {
        $farmer = $this->farmer();

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.planting-info'), ['planting_date' => '2026-07-01', 'farm_area' => 1.5])->assertOk();
        $plan = FarmerPlantingSchedule::query()->firstOrFail();
        $this->assertSame('2026-10-24', $plan->scheduled_harvest_date->toDateString());     // + 115 days
        $this->assertNull($plan->rice_variety_id);

        // The form still needs a harvest date or month when no variety is chosen.
        $this->actingAs($farmer)->from(route('farmer.planting-schedules.create'))
            ->post(route('farmer.planting-schedules.store'), $this->form(['rice_variety_id' => '']))
            ->assertSessionHasErrors(['scheduled_harvest_date', 'harvest_month']);
    }

    public function test_the_dashboard_pop_up_can_set_a_variety_and_gets_its_date(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();

        $this->actingAs($farmer)->postJson(route('farmer.dashboard.planting-info'), [
            'planting_date' => '2026-07-01', 'farm_area' => 1.5, 'rice_variety_id' => $variety->id, 'crop_establishment' => 'Transplanted',
        ])->assertOk();

        $this->assertSame('2026-10-16', FarmerPlantingSchedule::query()->firstOrFail()->scheduled_harvest_date->toDateString());
    }

    public function test_a_harvest_date_the_farmer_typed_is_kept_and_a_new_planting_date_does_not_move_it(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();

        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $this->form([
            'rice_variety_id' => $variety->id, 'scheduled_harvest_date' => '2026-10-30', 'harvest_date_mode' => 'manual',
        ]))->assertRedirect();
        $plan = FarmerPlantingSchedule::query()->firstOrFail();
        $this->assertSame(['2026-10-30', true], [$plan->scheduled_harvest_date->toDateString(), $plan->harvest_date_manual]);

        // A later change from the dashboard keeps the farmer's own harvest date.
        $this->actingAs($farmer)->postJson(route('farmer.dashboard.planting-info'), ['planting_date' => '2026-07-05', 'farm_area' => 1.5])->assertOk();
        $this->assertSame('2026-10-30', $plan->fresh()->scheduled_harvest_date->toDateString());

        // "Use this date" (auto) goes back to the variety's date.
        $this->actingAs($farmer)->put(route('farmer.planting-schedules.update', $plan), $this->form([
            'planting_date' => '2026-07-05', 'rice_variety_id' => $variety->id, 'scheduled_harvest_date' => '2026-10-20', 'harvest_date_mode' => 'auto',
        ]))->assertRedirect();
        $this->assertSame(['2026-10-20', false], [$plan->fresh()->scheduled_harvest_date->toDateString(), $plan->fresh()->harvest_date_manual]);
    }

    public function test_a_harvested_plan_keeps_its_dates_when_a_variety_is_added(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();
        $plan = $this->plan($farmer, ['scheduled_harvest_date' => '2026-07-30', 'planting_date' => '2026-04-01', 'season' => 'Dry', 'harvested_on' => '2026-07-28', 'progress_status' => 'Harvested']);

        $plan->update(['rice_variety_id' => $variety->id, 'crop_establishment' => 'Transplanted']);

        $this->assertSame('2026-07-30', $plan->fresh()->scheduled_harvest_date->toDateString());
    }

    public function test_the_estimate_endpoint_gives_the_preview_line(): void
    {
        $variety = RiceVariety::factory()->create();   // TPR WS 110 (106-114)

        $this->actingAs($this->farmer())->getJson(route('rice-varieties.estimate', [
            'variety' => $variety->id, 'date' => '2026-07-01', 'season' => 'Wet', 'establishment' => 'Transplanted', 'basis' => 'sowing',
        ]))->assertOk()
            ->assertJsonPath('expected_harvest_date', '2026-10-19')
            ->assertJsonPath('maturity_days_used', 110)
            ->assertJsonPath('label', 'Expected harvest: Oct 19, 2026 (± 4 days)');

        $this->actingAs(User::factory()->maoPersonnel()->create())->getJson(route('rice-varieties.estimate', ['date' => '2026-07-01']))->assertForbidden();
    }

    // ------------------------------------------------------------------ details pop-up

    public function test_the_details_json_has_every_combination_with_null_for_missing_trials(): void
    {
        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])->assertSuccessful();
        $variety = RiceVariety::query()->where('nsic_reg_no', 'NSIC 2026 Rc 824H')->firstOrFail();

        $json = $this->actingAs(User::factory()->maoPersonnel()->create())->getJson(route('rice-varieties.show', $variety))->assertOk();

        $json->assertJsonPath('name', 'Mestiso 176 (RTP750FP)')
            ->assertJsonPath('combos.tpr_ds.maturity', ['avg' => 109, 'min' => 100, 'max' => 120])
            ->assertJsonPath('combos.dwsr_ds.maturity', ['avg' => null, 'min' => null, 'max' => null])
            ->assertJsonPath('combos.dwsr_ws.yield', null)
            ->assertJsonPath('combos.tpr_ws.yield', 8.3)
            ->assertJsonPath('max_yield', 8.7)
            ->assertJsonPath('amylose.level', 'low')
            ->assertJsonPath('recommendation.location', 'Southern Luzon (SL), Zamboanga Peninsula and Western Mindanao (ZPWM)')
            ->assertJsonPath('plant_url', null);
        $whiteheads = collect($json->json('reactions.pests'))->firstWhere('key', 'whiteheads');
        $this->assertSame(['intermediate', 'resistant'], array_column($whiteheads['entries'], 'level'));
        $this->assertSame('not_tested', collect($json->json('reactions.pests'))->firstWhere('key', 'whorl_maggot')['entries'][0]['level']);
    }

    public function test_results_by_station_come_as_one_worst_result_chip_with_each_station(): void
    {
        // NSIC 2011 Rc 266H (Mestiso 40).
        $cells = [
            'diseases' => [
                'blast' => 'PhilRice CES - I PhilRice Midsayap - S PhilRice Isabela - I UPLB - I VSU - I',
                'blb' => 'PhilRice CES - I PhilRice Midsayap - I PhilRice Isabela - I UPLB - I VSU - I WESVIARC - I',
            ],
            'pests' => ['glh' => 'MS', 'whiteheads' => 'WSB - I; YSB - MR'],
        ];
        $reactions = [];
        foreach ($cells as $group => $byKey) {
            foreach ($byKey as $key => $raw) {
                $reactions[$group][$key] = ['raw' => $raw, 'entries' => \App\Support\RiceVarieties\NsicCell::reactions($raw)];
            }
        }
        $variety = RiceVariety::factory()->create(['nsic_reg_no' => 'NSIC 2011 Rc 266H', 'variety_name' => 'Mestiso 40', 'reactions' => $reactions]);

        $json = $this->actingAs($this->farmer())->getJson(route('rice-varieties.show', $variety))->assertOk();
        $row = fn (string $group, string $key): array => collect($json->json('reactions.'.$group))->firstWhere('key', $key);

        $blast = $row('diseases', 'blast');
        $this->assertTrue($blast['by_station']);
        $this->assertSame(['S', 'susceptible', true, 5], [$blast['summary']['code'], $blast['summary']['level'], $blast['summary']['varies'], $blast['summary']['sites']]);
        $this->assertSame(['PhilRice CES', 'PhilRice Midsayap', 'PhilRice Isabela', 'UPLB', 'VSU'], array_column($blast['entries'], 'station'));
        $this->assertSame(['I', false, 6], [$row('diseases', 'blb')['summary']['code'], $row('diseases', 'blb')['summary']['varies'], $row('diseases', 'blb')['summary']['sites']]);
        // A single code and a stem borer split by kind keep their own chips.
        $this->assertFalse($row('pests', 'glh')['by_station']);
        $this->assertFalse($row('pests', 'whiteheads')['by_station']);
        $this->assertNull($row('pests', 'whiteheads')['summary']);
    }

    public function test_a_farmer_gets_the_i_planted_this_link_and_their_own_calendar(): void
    {
        $farmer = $this->farmer();
        $variety = RiceVariety::factory()->rc480()->create();
        $plan = $this->plan($farmer, ['rice_variety_id' => $variety->id, 'crop_establishment' => 'Transplanted']);

        $this->actingAs($farmer)->getJson(route('rice-varieties.show', $variety))->assertOk()
            ->assertJsonPath('plant_url', route('farmer.planting-schedules.edit', ['plantingSchedule' => $plan->id, 'variety' => $variety->id]))
            ->assertJsonPath('planted.date', '2026-07-01')
            ->assertJsonPath('default_combo', 'tpr_ws');

        // The planting form opens with that variety chosen.
        $other = RiceVariety::factory()->create();
        $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', ['plantingSchedule' => $plan->id, 'variety' => $other->id]))->assertOk()
            ->assertSee('<option value="'.$other->id.'" data-search', false)
            ->assertSee('<option value="'.$other->id.'" data-search', false);
        $this->assertMatchesRegularExpression('/<option value="'.$other->id.'" [^>]* selected>/', $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', ['plantingSchedule' => $plan->id, 'variety' => $other->id]))->getContent());
    }

    // ------------------------------------------------------------------ IT: add / edit / switch

    public function test_it_adds_a_variety_and_farmers_can_pick_it_right_away(): void
    {
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->post(route('rice-varieties.store'), [
            'nsic_reg_no' => 'NSIC 2026 Rc 900', 'variety_name' => 'Lian Gold', 'variety_type' => 'Inbred', 'year_approved' => 2026,
            'owner_breeder' => 'PhilRice', 'yield_tpr_ws' => 5100, 'farm_types' => ['Irrigated'],
            'maturity_tpr_ws' => 112, 'maturity_tpr_ws_min' => 108, 'maturity_tpr_ws_max' => 118,
            'pests' => ['glh' => 'MR'], 'diseases' => ['blast' => 'S'], 'amylose_content' => 21.5,
            'adaptation' => 'Recommended in irrigated lowland areas of Batangas.',
        ])->assertRedirect(route('rice-varieties.index'));

        $variety = RiceVariety::query()->where('nsic_reg_no', 'NSIC 2026 Rc 900')->firstOrFail();
        $this->assertSame([RiceVariety::SOURCE_MANUAL, true, $it->id, ['Irrigated']], [$variety->source, $variety->is_active, $variety->created_by, $variety->farmTypes()]);
        $this->assertSame('MR', $variety->reactions['pests']['glh']['entries'][0]['code']);
        $this->assertTrue(SystemLog::query()->where('action', 'Added Rice Variety')->exists());

        $this->actingAs($this->farmer())->get(route('farmer.planting-schedules.create'))->assertOk()->assertSee('NSIC Rc 900 (Lian Gold)');
    }

    public function test_it_validation_checks_ranges_years_and_unique_numbers(): void
    {
        RiceVariety::factory()->create(['nsic_reg_no' => 'NSIC 2025 Rc 777', 'import_key' => 'nsic 2025 rc 777']);
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->from(route('rice-varieties.create'))->post(route('rice-varieties.store'), [
            'nsic_reg_no' => 'nsic 2025 rc 777', 'variety_name' => 'Copy', 'year_approved' => 1900,
            'maturity_tpr_ds' => 100, 'maturity_tpr_ds_min' => 110, 'maturity_tpr_ds_max' => 120,
            'yield_tpr_ds' => -5,
        ])->assertSessionHasErrors(['nsic_reg_no', 'year_approved', 'maturity_tpr_ds', 'yield_tpr_ds']);
        $this->assertSame(1, RiceVariety::query()->count());
    }

    public function test_editing_an_imported_variety_makes_it_manual_and_keeps_compound_reactions(): void
    {
        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])->assertSuccessful();
        $variety = RiceVariety::query()->where('nsic_reg_no', 'NSIC 2026 Rc 824H')->firstOrFail();
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->get(route('rice-varieties.edit', $variety))->assertOk()->assertSee('Keep imported: WSB - I; YSB - MR');

        $this->actingAs($it)->put(route('rice-varieties.update', $variety), [
            'nsic_reg_no' => 'NSIC 2026 Rc 824H', 'variety_name' => 'Mestiso 176', 'variety_type' => 'Hybrid', 'year_approved' => 2026,
            'yield_tpr_ds' => 8309, 'yield_tpr_ws' => 8309,
            'maturity_tpr_ds' => 109, 'maturity_tpr_ds_min' => 100, 'maturity_tpr_ds_max' => 120,
            'pests' => ['whiteheads' => 'keep', 'glh' => 'R'], 'diseases' => ['tungro' => 'keep'],
        ])->assertRedirect();

        $variety->refresh();
        $this->assertSame(['Mestiso 176', RiceVariety::SOURCE_MANUAL, 'nsic 2026 rc 824h'], [$variety->variety_name, $variety->source, $variety->import_key]);
        $this->assertCount(2, $variety->reactions['pests']['whiteheads']['entries']);
        $this->assertSame([7888, 8729], array_column($variety->yield_values['tpr_ds']['values'], 'kg'));      // unchanged yield keeps its regions
        $this->assertTrue(SystemLog::query()->where('action', 'Edited Rice Variety')->exists());

        // The next import leaves the correction alone.
        $this->artisan('rice-varieties:import', ['path' => base_path(self::FIXTURE)])->assertSuccessful();
        $this->assertSame('Mestiso 176', $variety->fresh()->variety_name);
    }

    public function test_an_inactive_variety_leaves_the_pickers_but_stays_on_existing_plans(): void
    {
        $variety = RiceVariety::factory()->rc480()->create();
        $farmer = $this->farmer();
        $plan = $this->plan($farmer, ['rice_variety_id' => $variety->id]);
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->patch(route('rice-varieties.toggle', $variety))->assertRedirect();
        $this->assertFalse($variety->fresh()->is_active);
        $this->assertTrue(SystemLog::query()->where('action', 'Deactivated Rice Variety')->exists());

        $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertOk()->assertDontSee('data-rv-open="'.$variety->id.'" data-rv-name="GSR 8" aria-label', false);
        $this->actingAs($farmer)->get(route('farmer.planting-schedules.create'))->assertOk()->assertDontSee('NSIC Rc 480 (GSR 8)');
        // Their own plan still shows and keeps it.
        $this->actingAs($farmer)->get(route('farmer.planting-schedules.edit', $plan))->assertOk()->assertSee('NSIC Rc 480 (GSR 8)');
        $this->actingAs($farmer)->getJson(route('rice-varieties.show', $variety))->assertOk();
        $this->actingAs($this->farmer('Prenza', 'Other'))->getJson(route('rice-varieties.show', $variety))->assertForbidden();
        // A new plan cannot choose it.
        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), $this->form(['rice_variety_id' => $variety->id]))->assertSessionHasErrors('rice_variety_id');

        $this->actingAs($it)->patch(route('rice-varieties.toggle', $variety))->assertRedirect();
        $this->assertTrue($variety->fresh()->is_active);
    }

    public function test_the_catalog_has_no_type_filter_row(): void
    {
        RiceVariety::factory()->create();
        foreach ([$this->farmer(), User::factory()->maoPersonnel()->create(), User::factory()->itExpert()->create()] as $user) {
            $this->actingAs($user)->get(route('rice-varieties.index'))->assertOk()
                ->assertDontSee('id="rvFilter-type"', false)
                ->assertDontSee('data-rv-filter="type:', false)
                ->assertSee('<span class="rv-filter-title" id="rvFilter-farm_type">Farm type</span>', false)
                ->assertSee('data-rv-filter="farm_type:Irrigated">', false)
                ->assertSee('data-rv-filter="farm_type:Rainfed">', false)
                ->assertDontSee('Saline-prone')
                ->assertDontSee('>Upland</a>', false);
        }
    }

    // ------------------------------------------------------------------ farm type (Irrigated / Rainfed)

    public function test_not_specified_varieties_appear_only_under_all(): void
    {
        RiceVariety::factory()->create(['variety_name' => 'Both Kinds', 'farm_irrigated' => true, 'farm_rainfed' => true]);
        RiceVariety::factory()->create(['variety_name' => 'Nowhere Said', 'farm_irrigated' => false, 'farm_rainfed' => false]);
        RiceVariety::factory()->rc480()->create();
        $farmer = $this->farmer();
        $names = fn (array $query): array => collect($this->actingAs($farmer)->get(route('rice-varieties.index', $query))->viewData('varieties')->items())->pluck('variety_name')->sort()->values()->all();

        $this->assertSame(['Both Kinds', 'GSR 8', 'Nowhere Said'], $names([]));
        $this->assertSame(['Both Kinds'], $names(['farm_type' => 'Irrigated']));
        $this->assertSame(['Both Kinds', 'GSR 8'], $names(['farm_type' => 'Rainfed']));
        // The old values are not filters any more.
        $this->assertSame(['Both Kinds', 'GSR 8', 'Nowhere Said'], $names(['farm_type' => 'Upland']));
    }

    public function test_the_it_form_has_two_farm_type_checkboxes_and_saves_exactly_what_is_checked(): void
    {
        $it = User::factory()->itExpert()->create();
        $this->actingAs($it)->get(route('rice-varieties.create'))->assertOk()
            ->assertSee('<input type="checkbox" name="farm_types[]" value="Irrigated"', false)
            ->assertSee('<input type="checkbox" name="farm_types[]" value="Rainfed"', false)
            ->assertDontSee('name="ecosystem"', false)
            ->assertDontSee('From the adaptation text');

        $form = ['nsic_reg_no' => 'NSIC 2026 Rc 901', 'variety_name' => 'Both Ways', 'year_approved' => 2026,
            'adaptation' => 'Recommended in irrigated lowland areas.', 'farm_types' => ['Irrigated', 'Rainfed']];
        $this->actingAs($it)->post(route('rice-varieties.store'), $form)->assertRedirect();
        $variety = RiceVariety::query()->where('variety_name', 'Both Ways')->firstOrFail();
        $this->assertSame(['Irrigated', 'Rainfed'], $variety->farmTypes());

        // None checked is "Not specified", even when the adaptation text says "irrigated".
        $this->actingAs($it)->put(route('rice-varieties.update', $variety), array_diff_key($form, ['farm_types' => 1]))->assertRedirect();
        $this->assertSame([], $variety->fresh()->farmTypes());
        $this->actingAs($it)->get(route('rice-varieties.edit', $variety))->assertOk()->assertDontSee('value="Irrigated" checked', false);

        $this->actingAs($it)->from(route('rice-varieties.edit', $variety))->put(route('rice-varieties.update', $variety), array_merge($form, ['farm_types' => ['Upland']]))
            ->assertSessionHasErrors('farm_types.0');
    }

    public function test_the_planting_picker_lists_the_farms_type_first_then_other_varieties(): void
    {
        RiceVariety::factory()->create(['variety_name' => 'Newest Rainfed', 'year_approved' => 2026, 'farm_irrigated' => false, 'farm_rainfed' => true]);
        RiceVariety::factory()->create(['variety_name' => 'Older Irrigated', 'year_approved' => 2015, 'farm_irrigated' => true, 'farm_rainfed' => false]);
        RiceVariety::factory()->create(['variety_name' => 'Unsaid', 'year_approved' => 2020, 'farm_irrigated' => false, 'farm_rainfed' => false]);
        $farmer = $this->farmer();    // farm type Irrigated (profile)

        $html = $this->actingAs($farmer)->get(route('farmer.planting-schedules.create'))->assertOk()->getContent();
        $suits = strpos($html, '<optgroup label="For irrigated farms" data-rv-group="suits">');
        $other = strpos($html, '<optgroup label="Other varieties" data-rv-group="other">');
        $this->assertNotFalse($suits);
        $this->assertNotFalse($other);
        $this->assertTrue($suits < strpos($html, 'Older Irrigated') && strpos($html, 'Older Irrigated') < $other);
        $this->assertTrue($other < strpos($html, 'Newest Rainfed') && strpos($html, 'Newest Rainfed') < strpos($html, 'Unsaid'));   // newest first among the others
        $this->assertStringContainsString('data-farm-types="Rainfed"', $html);
    }

    public function test_the_pop_up_shows_the_farm_type_chips(): void
    {
        $both = RiceVariety::factory()->create(['farm_irrigated' => true, 'farm_rainfed' => true]);
        $none = RiceVariety::factory()->create(['farm_irrigated' => false, 'farm_rainfed' => false]);
        $farmer = $this->farmer();

        $this->actingAs($farmer)->getJson(route('rice-varieties.show', $both))->assertOk()
            ->assertJsonPath('farm_types', [['key' => 'Irrigated', 'label' => 'Irrigated'], ['key' => 'Rainfed', 'label' => 'Rainfed']])
            ->assertJsonMissingPath('ecosystem')
            ->assertJsonMissingPath('recommendation.environment');
        $this->actingAs($farmer)->getJson(route('rice-varieties.show', $none))->assertOk()
            ->assertJsonPath('farm_types', [['key' => 'not_specified', 'label' => 'Not specified']]);
    }

    // ------------------------------------------------------------------ IT list layout

    public function test_the_it_list_has_six_columns_and_marks_only_exceptions(): void
    {
        $it = User::factory()->itExpert()->create();
        RiceVariety::factory()->create(['variety_name' => 'Imported One', 'year_approved' => 2026, 'variety_type' => 'Hybrid',
            'owner_breeder' => 'Philippine Rice Research Institute - International Rice Research Institute', 'maturity_tpr_ds' => 111, 'maturity_tpr_ws' => 122, 'yield_tpr_ws' => 10100]);
        RiceVariety::factory()->create(['variety_name' => 'Hand Added', 'year_approved' => 2026, 'source' => RiceVariety::SOURCE_MANUAL]);
        RiceVariety::factory()->create(['variety_name' => 'Switched Off', 'year_approved' => 2025, 'is_active' => false]);

        $html = $this->actingAs($it)->get(route('rice-varieties.index'))->assertOk()->getContent();
        $thead = \Illuminate\Support\Str::betweenFirst(\Illuminate\Support\Str::after($html, 'data-rv-it-table>'), '<thead>', '</thead>');
        preg_match_all('/<th scope="col"[^>]*><span class="(?:rv-th|visually-hidden)">([^<]+)/', $thead, $heads);
        $this->assertSame(['Variety', 'Type', 'Breeder', 'Maturity', 'Avg yield', 'Actions'], $heads[1]);
        foreach (['data-rv-toggle="', 'class="rv-switch"', '>Source<', '>NSIC no.<', 'role="switch"'] as $gone) {
            $this->assertStringNotContainsString($gone, preg_replace('/<div class="rv-menu-list".*?<\/div>/s', '', $html), $gone);
        }

        // Only exceptions are marked: one Manual pill, one Inactive pill on a dimmed row.
        $this->assertSame(1, substr_count($html, 'data-rv-manual'));
        $this->assertSame(1, substr_count($html, 'data-rv-inactive'));
        $this->assertSame(1, preg_match_all('/class="rv-row\s+is-inactive\s*"/', $html));

        $this->assertStringContainsString('<span class="rv-type-pill is-hybrid">Hybrid</span>', $html);
        $this->assertStringContainsString('<span class="rv-sub">· 2026</span>', $html);
        $this->assertStringContainsString('<span class="rv-breeder" title="Philippine Rice Research Institute - International Rice Research Institute">PhilRice - IRRI</span>', $html);
        $this->assertStringContainsString('<span class="rv-pair"><span class="rv-pair-label">DS</span><b>111</b></span>', $html);
        $this->assertStringContainsString('<span class="rv-dot is-late" title="Late (&gt;120)"></span>', $html);    // typical maturity: TPR WS 122
        $this->assertStringContainsString('10.1 t/ha</td>', $html);
        // Grouped by year (Newest), counted over every page; "⋯" holds Edit and Deactivate / Activate.
        $this->assertStringContainsString('<th colspan="6" scope="colgroup">2026 · 2 varieties</th>', $html);
        $this->assertStringContainsString('<th colspan="6" scope="colgroup">2025 · 1 variety</th>', $html);
        $this->assertSame(3, substr_count($html, 'data-rv-menu-btn'));
        $this->assertStringContainsString('data-rv-menu-edit>Edit</a>', $html);
        $this->assertSame(2, substr_count($html, '>Deactivate</button>'));
        $this->assertSame(1, substr_count($html, '>Activate</button>'));
        $this->assertStringContainsString('<span class="rv-pager-range" data-rv-range>1–3 of 3</span>', $html);

        // Other sorts: a plain list without year rows.
        $this->assertStringNotContainsString('rv-year-row', $this->actingAs($it)->get(route('rice-varieties.index', ['sort' => 'yield']))->getContent());
    }

    public function test_filters_sit_behind_a_button_with_a_count_and_show_as_removable_chips(): void
    {
        RiceVariety::factory()->create(['maturity_tpr_ws' => 100]);
        $farmer = $this->farmer();

        $closed = $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<details class="rv-filters" data-rv-filters>', $closed);                  // closed by default
        $this->assertStringNotContainsString('data-rv-filter-count', $closed);
        $this->assertStringNotContainsString('data-rv-clear-all', $closed);

        $this->actingAs($farmer)->get(route('rice-varieties.index', ['farm_type' => 'Irrigated', 'maturity' => 'early', 'q' => 'test']))->assertOk()
            ->assertSee('<span class="rv-badge" data-rv-filter-count>2</span>', false)
            ->assertSee('data-rv-active-chip="farm_type">Irrigated<span aria-hidden="true">×</span>', false)
            ->assertSee('data-rv-active-chip="maturity">Early (≤110 days)<span aria-hidden="true">×</span>', false)
            ->assertSee('href="'.e(route('rice-varieties.index', ['q' => 'test', 'maturity' => 'early'])).'"', false)   // removing Irrigated keeps the rest
            ->assertSee('data-rv-clear-all>Clear all</a>', false)
            ->assertSee('<span class="rv-count" data-rv-count>1 variety</span>', false)
            // The farmer cards use the same pill and maturity dot.
            ->assertSee('<span class="rv-type-pill is-inbred">Inbred</span>', false)
            ->assertSee('<span class="rv-dot is-early"', false);
    }

    public function test_the_catalog_filters_search_and_sorts(): void
    {
        RiceVariety::factory()->rc480()->create();
        RiceVariety::factory()->create(['variety_name' => 'Early Hybrid', 'variety_type' => 'Hybrid', 'year_approved' => 2025, 'maturity_tpr_ws' => 100, 'yield_tpr_ws' => 7000]);
        RiceVariety::factory()->create(['variety_name' => 'Late Inbred', 'year_approved' => 2010, 'maturity_tpr_ws' => 128, 'maturity_tpr_ds' => 130, 'yield_tpr_ws' => 4000]);
        $farmer = $this->farmer();
        $names = fn (array $query): array => array_values(array_map(fn ($v) => $v->variety_name, $this->actingAs($farmer)->get(route('rice-varieties.index', $query))->viewData('varieties')->items()));

        $this->assertSame(['Early Hybrid', 'GSR 8', 'Late Inbred'], $names([]));                       // newest first
        $this->assertSame(['GSR 8'], $names(['q' => 'NSIC Rc 480']));
        $this->assertSame(['GSR 8'], $names(['q' => 'irri']));
        $this->assertSame(['Early Hybrid'], $names(['type' => 'Hybrid']));
        $this->assertSame(['Early Hybrid', 'GSR 8'], $names(['maturity' => 'early']));
        $this->assertSame(['Late Inbred'], $names(['maturity' => 'late']));
        $this->assertSame(['GSR 8'], $names(['farm_type' => 'Rainfed']));
        $this->assertSame(['Early Hybrid', 'Late Inbred'], $names(['farm_type' => 'Irrigated']));
        $this->assertSame(['Early Hybrid', 'Late Inbred'], $names(['season' => 'ds']));
        $this->assertSame(['Early Hybrid', 'Late Inbred', 'GSR 8'], $names(['sort' => 'yield']));
        $this->assertSame(['Early Hybrid', 'GSR 8', 'Late Inbred'], $names(['sort' => 'maturity']));
    }

    // ------------------------------------------------------------------ empty list and Run import

    public function test_an_empty_list_says_nothing_is_loaded_and_it_gets_the_run_import_button(): void
    {
        $this->actingAs(User::factory()->itExpert()->create())->get(route('rice-varieties.index'))->assertOk()
            ->assertSee('data-rv-not-loaded', false)
            ->assertSee('No varieties loaded yet')
            ->assertSee('action="'.route('rice-varieties.import').'"', false)
            ->assertSee('Run import')
            ->assertDontSee('No variety matches');

        foreach ([$this->farmer(), User::factory()->create(['role' => User::ROLE_MAO])] as $user) {
            $this->actingAs($user)->get(route('rice-varieties.index'))->assertOk()
                ->assertSee('The variety list isn&#039;t available yet. Please check back later.', false)
                ->assertDontSee('Run import')
                ->assertDontSee('No varieties loaded yet')
                ->assertDontSee('No variety matches');
        }
    }

    public function test_no_variety_matches_shows_only_when_a_search_or_filter_is_on(): void
    {
        $it = User::factory()->itExpert()->create();
        $farmer = $this->farmer();

        // Nothing loaded, but the farmer searched: that is "no match", not "not loaded".
        $this->actingAs($farmer)->get(route('rice-varieties.index', ['q' => 'zzz']))
            ->assertSee('No variety matches. Try fewer filters.')->assertDontSee('isn&#039;t available yet', false);
        $this->actingAs($it)->get(route('rice-varieties.index', ['farm_type' => 'Rainfed']))
            ->assertSee('No variety matches. Try fewer filters.')->assertDontSee('Run import');
        // A sort alone is not a filter.
        $this->actingAs($it)->get(route('rice-varieties.index', ['sort' => 'yield']))->assertSee('Run import');

        RiceVariety::factory()->create(['variety_name' => 'Only One']);
        $this->actingAs($farmer)->get(route('rice-varieties.index'))->assertSee('Only One')
            ->assertDontSee('No variety matches')->assertDontSee('isn&#039;t available yet', false);
        $this->actingAs($farmer)->get(route('rice-varieties.index', ['q' => 'zzz']))->assertSee('No variety matches. Try fewer filters.');
    }

    public function test_it_runs_the_import_from_the_empty_list_and_it_is_logged(): void
    {
        config(['rice_varieties.import_path' => base_path(self::FIXTURE)]);
        $it = User::factory()->itExpert()->create();

        $this->actingAs($it)->from(route('rice-varieties.index'))->post(route('rice-varieties.import'))
            ->assertRedirect(route('rice-varieties.index'))
            ->assertSessionHas('success', 'Import finished: 5 added, 0 updated, 0 unchanged.');
        $this->assertSame(5, RiceVariety::query()->count());
        $log = SystemLog::query()->where('action', 'Imported Rice Varieties')->sole();
        $this->assertSame($it->id, (int) $log->user_id);
        $this->assertSame(['file' => 'nsic-rice-sample.xlsx', 'inserted' => 5, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'rows' => 5],
            json_decode((string) $log->details, true)['context']);

        // A second run changes nothing, and the list now shows the varieties.
        $this->actingAs($it)->post(route('rice-varieties.import'))->assertSessionHas('success', 'Import finished: 0 added, 0 updated, 5 unchanged.');
        $this->actingAs($it)->get(route('rice-varieties.index'))->assertDontSee('No varieties loaded yet')->assertSee('Mestiso 176');
    }

    public function test_a_failed_import_says_so_and_is_logged(): void
    {
        config(['rice_varieties.import_path' => base_path('tests/fixtures/missing.xlsx')]);

        $this->actingAs(User::factory()->itExpert()->create())->post(route('rice-varieties.import'))
            ->assertSessionHas('error', 'The import could not run. The details are in the System Logs.');
        $this->assertSame(0, RiceVariety::query()->count());
        $this->assertTrue(SystemLog::query()->where('action', 'Rice Variety Import Failed')->exists());
    }

    public function test_farmers_and_mao_cannot_run_the_import(): void
    {
        config(['rice_varieties.import_path' => base_path(self::FIXTURE)]);

        foreach ([$this->farmer(), User::factory()->create(['role' => User::ROLE_MAO])] as $user) {
            $this->actingAs($user)->post(route('rice-varieties.import'))->assertForbidden();
        }
        $this->assertSame(0, RiceVariety::query()->count());
        $this->assertFalse(SystemLog::query()->where('action', 'Imported Rice Varieties')->exists());
    }
}
