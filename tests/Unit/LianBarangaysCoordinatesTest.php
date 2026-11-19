<?php

namespace Tests\Unit;

use App\Support\LianBarangays;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09 Heat Map coordinate audit: the
 * hardcoded points in LianBarangays::COORDINATES were found to be wrong for
 * 17 of 19 barangays (most fell inside a *different* barangay's polygon; 5
 * fell outside every barangay boundary in the file), which silently
 * corrupted the live-weather input to the risk score for those barangays.
 *
 * This test verifies, directly against the shipped GeoJSON polygons (not a
 * duplicated/hand-maintained copy of the coordinates), that every one of the
 * 19 canonical barangay points genuinely lies inside its own polygon — so a
 * future hand-edit of LianBarangays that reintroduces a wrong point fails
 * CI instead of silently corrupting weather data again.
 */
class LianBarangaysCoordinatesTest extends TestCase
{
    private function geojsonPolygons(): array
    {
        $path = public_path('geojson/lian-barangays.geojson');
        $this->assertFileExists($path, 'Bundled barangay boundary GeoJSON must exist.');

        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);

        $polygons = [];
        foreach ($data['features'] as $feature) {
            $name = $feature['properties']['brgy_name'] ?? null;
            if ($name !== null) {
                $polygons[$name] = $feature['geometry'];
            }
        }

        return $polygons;
    }

    /**
     * Standard ray-casting point-in-polygon test on a single linear ring.
     * $ring is a list of [lon, lat] pairs (GeoJSON coordinate order).
     */
    private function pointInRing(float $lon, float $lat, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: PHP_FLOAT_EPSILON) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function pointInGeometry(float $lat, float $lon, array $geometry): bool
    {
        $type = $geometry['type'];
        $coordinates = $geometry['coordinates'];

        if ($type === 'Polygon') {
            // First ring is the outer boundary; a point in any hole (subsequent rings) is outside.
            if (! $this->pointInRing($lon, $lat, $coordinates[0])) {
                return false;
            }
            for ($i = 1; $i < count($coordinates); $i++) {
                if ($this->pointInRing($lon, $lat, $coordinates[$i])) {
                    return false;
                }
            }

            return true;
        }

        if ($type === 'MultiPolygon') {
            foreach ($coordinates as $polygon) {
                if ($this->pointInGeometry($lat, $lon, ['type' => 'Polygon', 'coordinates' => $polygon])) {
                    return true;
                }
            }

            return false;
        }

        $this->fail("Unsupported geometry type in test: {$type}");
    }

    public function test_all_19_canonical_barangays_have_a_geojson_polygon(): void
    {
        $polygons = $this->geojsonPolygons();

        foreach (LianBarangays::all() as $barangay) {
            $this->assertArrayHasKey($barangay, $polygons, "Missing GeoJSON polygon for '{$barangay}'.");
        }

        $this->assertCount(19, LianBarangays::all());
        $this->assertCount(19, $polygons, 'GeoJSON must not carry barangays outside the canonical 19.');
    }

    public function test_every_barangay_coordinate_falls_inside_its_own_polygon(): void
    {
        $polygons = $this->geojsonPolygons();
        $coordinates = LianBarangays::coordinates();

        foreach (LianBarangays::all() as $barangay) {
            $this->assertArrayHasKey($barangay, $coordinates, "LianBarangays::coordinates() is missing '{$barangay}'.");

            [$lat, $lon] = $coordinates[$barangay];
            $geometry = $polygons[$barangay];

            $this->assertTrue(
                $this->pointInGeometry($lat, $lon, $geometry),
                "LianBarangays coordinate for '{$barangay}' [{$lat}, {$lon}] does not fall inside its own GeoJSON polygon."
            );
        }
    }

    /**
     * Guards against a coordinate that's correct-ish in isolation but was
     * actually copy-pasted from a neighboring barangay (the exact failure
     * mode found by the audit) by asserting no two barangays share a point.
     */
    public function test_no_two_barangays_share_the_same_coordinate(): void
    {
        $coordinates = LianBarangays::coordinates();
        $seen = [];

        foreach ($coordinates as $barangay => [$lat, $lon]) {
            $key = round($lat, 5).','.round($lon, 5);
            $this->assertArrayNotHasKey($key, $seen, "'{$barangay}' shares a coordinate with '".($seen[$key] ?? '?')."'.");
            $seen[$key] = $barangay;
        }
    }
}
