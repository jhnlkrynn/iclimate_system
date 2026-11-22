<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerOrganization;
use App\Models\User;
use App\Services\Farms\FederationAssociationImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FederationAssociationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_federation_import_matches_totals_trims_names_and_excludes_president_and_phone(): void
    {
        $this->artisan('rice-associations:import-federation')
            ->assertExitCode(0);

        $orgs = FarmerOrganization::query()->get();
        $this->assertCount(16, $orgs);

        $rainfed = $orgs->where('farm_type', 'Rainfed');
        $irrigated = $orgs->where('farm_type', 'Irrigated');

        $this->assertCount(3, $rainfed);
        $this->assertCount(13, $irrigated);
        $this->assertEqualsWithDelta(90.59, (float) $rainfed->sum('area_hectares'), 0.01);
        $this->assertEqualsWithDelta(636.98, (float) $irrigated->sum('area_hectares'), 0.01);
        $this->assertEqualsWithDelta(727.57, (float) $orgs->sum('area_hectares'), 0.01);
        $this->assertSame(896, (int) $orgs->sum('farmer_count'));

        $this->assertEqualsCanonicalizing(
            ['Luyahan Farmers Association', 'Matabungkay Farmers Association', 'Tan-ag Farmers Association'],
            $rainfed->pluck('name')->all(),
        );

        $malaruhatan = $orgs->firstWhere('name', 'Malaruhatan Farmers Association');
        $this->assertNotNull($malaruhatan);
        $this->assertSame('Malaruhatan', $malaruhatan->barangay);
        $this->assertSame(FederationAssociationImporter::INFERRED_SOURCE, $malaruhatan->farm_type_source);

        // No president names or cellphone numbers imported anywhere.
        foreach ($orgs as $org) {
            $this->assertNull($org->chairman_name);
            $serialized = json_encode($org->toArray());
            $this->assertStringNotContainsString('9213224761', $serialized);
            $this->assertStringNotContainsString('09368977955', $serialized);
            $this->assertStringNotContainsString('Danilo S. Cahigan', $serialized);
        }
    }

    public function test_federation_import_is_idempotent_and_preserves_mao_confirmations(): void
    {
        $this->artisan('rice-associations:import-federation')->assertExitCode(0);

        $tanAg = FarmerOrganization::query()->where('name', 'Tan-ag Farmers Association')->firstOrFail();
        $tanAg->forceFill([
            'farm_type' => 'Irrigated',
            'farm_type_source' => null,
            'farm_type_confirmed_at' => now(),
        ])->save();

        $this->artisan('rice-associations:import-federation')->assertExitCode(0);

        $this->assertCount(16, FarmerOrganization::query()->get());
        $tanAg->refresh();
        $this->assertSame('Irrigated', $tanAg->farm_type);
        $this->assertNull($tanAg->farm_type_source);
    }

    public function test_federation_import_fails_when_totals_do_not_match(): void
    {
        $path = storage_path('framework/testing/bad-federation.xlsx');
        @mkdir(dirname($path), 0777, true);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['BARANGAY', 'NAME OF FARMER’S ASSOCIATION', 'REG', 'DATE', 'PRES', 'PHONE', 'AREA', 'MEMBERS', '', 'IRRIGATED (HA)', 999.00],
            ['Balibago', 'Balibago Farmers Association', 'RN 644', '2002-12-19', 'Someone', '9999999999', 34.27, 51, '', 'NON IRRIGATED (HA)', 90.59],
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($path);

        $this->artisan('rice-associations:import-federation', ['path' => $path])
            ->assertExitCode(1);

        $this->assertDatabaseCount('farmer_organizations', 0);
        @unlink($path);
    }

    public function test_heat_map_irrigation_layer_and_mao_confirmation_flow(): void
    {
        $this->artisan('rice-associations:import-federation')->assertExitCode(0);

        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'email_verified_at' => now()]);

        $api = $this->actingAs($mao)->getJson('/api/heat-map?layer=irrigation')
            ->assertOk()
            ->assertJsonPath('layer', 'irrigation')
            ->assertJsonPath('summary.has_inferred', true);

        $rows = collect($api->json('barangays'))->keyBy('name');
        $this->assertSame('irrigated', $rows['Balibago']['level']);
        $this->assertSame('Irrigated', $rows['Balibago']['label']);
        $this->assertSame('irrigated_and_rainfed', $rows['San Diego']['level']);
        $this->assertSame('Irrigated and rainfed', $rows['San Diego']['label']);
        $this->assertSame('rainfed', $rows['Luyahan']['level']);
        $this->assertSame('Rainfed', $rows['Luyahan']['label']);
        $this->assertSame('nodata', $rows['Bagong Pook']['level']);
        $this->assertSame('No record', $rows['Bagong Pook']['label']);
        $this->assertCount(10, $rows->where('level', 'nodata'));

        // Non-zero area only on list rows; both on Irrigated and rainfed; detail shows each association with its farm type.
        $heatMapPage = $this->actingAs($mao)->get(route('heat-map.index', ['range' => 'irrigation']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('34.27 ha irrigated', $heatMapPage);
        $this->assertStringNotContainsString('0.00 ha rainfed', $heatMapPage);
        $this->assertStringContainsString('17.20 ha irrigated · 48.13 ha rainfed', $heatMapPage);
        $this->assertStringNotContainsString('Mostly irrigated', $heatMapPage);
        $this->assertStringNotContainsString('Mostly rainfed', $heatMapPage);

        $detailHtml = $this->actingAs($mao)
            ->getJson(route('heat-map.detail', ['range' => 'irrigation', 'barangay' => 'San Diego']))
            ->assertOk()
            ->json('html');
        $this->assertStringContainsString('Samahan ng Gintong Pagasa Ng Magsasaka Ng Lian, Inc.', $detailHtml);
        $this->assertStringContainsString('Tan-ag Farmers Association', $detailHtml);
        $this->assertStringContainsString('Irrigated', $detailHtml);
        $this->assertStringContainsString('Rainfed', $detailHtml);

        $this->actingAs($mao)->get(route('mao.associations.farm-types.index'))
            ->assertOk()
            ->assertSee('Luyahan Farmers Association')
            ->assertSee(FederationAssociationImporter::INFERRED_SOURCE);

        $luyahan = FarmerOrganization::query()->where('name', 'Luyahan Farmers Association')->firstOrFail();
        $this->actingAs($mao)->post(route('mao.associations.farm-types.update', $luyahan), [
            'farm_type' => 'Irrigated',
        ])->assertRedirect();

        $luyahan->refresh();
        $this->assertSame('Irrigated', $luyahan->farm_type);
        $this->assertNull($luyahan->farm_type_source);
        $this->assertNotNull($luyahan->farm_type_confirmed_at);
    }

    public function test_new_farm_defaults_farm_type_from_linked_association(): void
    {
        $this->artisan('rice-associations:import-federation')->assertExitCode(0);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'email_verified_at' => now()]);
        $luyahan = FarmerOrganization::query()->where('name', 'Luyahan Farmers Association')->firstOrFail();

        $this->actingAs($farmer)->post(route('farmer.farms.store'), [
            'farm_name' => 'Luyahan Plot',
            'barangay' => 'Luyahan',
            'association_status' => Farm::ASSOCIATION_MEMBER,
            'farmer_organization_id' => $luyahan->id,
            'farm_size_value' => 1.5,
            'farm_size_unit' => Farm::UNIT_HECTARES,
        ])->assertRedirect(route('farmer.my-farm'));

        $farm = Farm::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertSame('Rainfed', $farm->farm_type);
    }
}
