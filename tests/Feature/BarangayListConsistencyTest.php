<?php

namespace Tests\Feature;

use App\Services\Risk\AgriculturalRiskScorer;
use App\Support\LianBarangays;
use App\Support\LianFarmTypes;
use Tests\TestCase;

/**
 * App\Support\LianBarangays::all() is the one official list of Lian's 19 barangays. The places that must keep their own
 * copy or key data by name (the browser map script, the risk scorer's exposure ratings, the farmer-association records)
 * may not drift from it.
 */
class BarangayListConsistencyTest extends TestCase
{
    public function test_the_official_list_has_nineteen_unique_names(): void
    {
        $all = LianBarangays::all();

        $this->assertCount(19, $all);
        $this->assertSame($all, array_values(array_unique($all)));
        $this->assertSame(array_keys(LianBarangays::coordinates()), $all);
    }

    public function test_the_map_script_uses_the_same_official_names(): void
    {
        $js = file_get_contents(resource_path('js/barangay-risk-map.js'));
        $this->assertSame(1, preg_match('/const OFFICIAL_NAMES = \[(.*?)\];/s', $js, $m), 'OFFICIAL_NAMES not found in barangay-risk-map.js');
        preg_match_all("/'([^']+)'/", $m[1], $names);

        $this->assertSame(LianBarangays::all(), $names[1]);
    }

    public function test_the_risk_scorer_rates_exactly_the_official_barangays(): void
    {
        $exposure = (new \ReflectionClassConstant(AgriculturalRiskScorer::class, 'BARANGAY_EXPOSURE'))->getValue();

        $this->assertEqualsCanonicalizing(LianBarangays::all(), array_keys($exposure));
    }

    public function test_every_farmer_association_is_in_an_official_barangay(): void
    {
        $unofficial = LianFarmTypes::records()->pluck('barangay')->unique()->reject(fn ($b) => in_array($b, LianBarangays::all(), true));

        $this->assertSame([], $unofficial->values()->all());
    }
}
