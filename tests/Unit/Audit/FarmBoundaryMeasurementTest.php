<?php

namespace Tests\Unit\Audit;

use App\Services\FarmBoundaryMeasurementService;
use PHPUnit\Framework\TestCase;

/**
 * Audit 2026-11-22: the farm size a farmer gets from drawing their farm on the map (FarmController::saveMap ->
 * FarmBoundaryMeasurementService). Pure calculation, no database.
 */
class FarmBoundaryMeasurementTest extends TestCase
{
    private FarmBoundaryMeasurementService $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = new FarmBoundaryMeasurementService;
    }

    /** A rectangle of the given size in metres, south-west corner near the Lian poblacion (14.04 N, 120.65 E). */
    private function rectangle(float $widthMeters, float $heightMeters, float $lat = 14.04, float $lng = 120.65): array
    {
        $dLat = $heightMeters / 111_195.0;                                   // metres per degree of latitude (mean Earth radius)
        $dLng = $widthMeters / (111_195.0 * cos(deg2rad($lat + $dLat / 2)));

        return [
            ['lat' => $lat, 'lng' => $lng],
            ['lat' => $lat, 'lng' => $lng + $dLng],
            ['lat' => $lat + $dLat, 'lng' => $lng + $dLng],
            ['lat' => $lat + $dLat, 'lng' => $lng],
        ];
    }

    public function test_a_100_by_100_metre_field_is_one_hectare(): void
    {
        $measured = $this->m->measure($this->rectangle(100, 100));

        $this->assertEqualsWithDelta(1.0, $measured['area_hectares'], 0.005);
        $this->assertEqualsWithDelta(400.0, $measured['perimeter_meters'], 1.0);
    }

    public function test_area_scales_with_the_field(): void
    {
        $this->assertEqualsWithDelta(2.5, $this->m->measure($this->rectangle(250, 100))['area_hectares'], 0.01);
        $this->assertEqualsWithDelta(0.05, $this->m->measure($this->rectangle(25, 20))['area_hectares'], 0.0005);
    }

    public function test_the_point_order_clockwise_or_not_gives_the_same_area(): void
    {
        $square = $this->rectangle(100, 100);

        $this->assertSame($this->m->measure($square)['area_hectares'], $this->m->measure(array_reverse($square))['area_hectares']);
    }

    public function test_an_l_shaped_field_is_measured_as_its_real_shape(): void
    {
        // 100 x 100 m with a 50 x 50 m corner cut out = 0.75 ha.
        $full = $this->rectangle(100, 100);
        $half = $this->rectangle(50, 50);
        $shape = [$full[0], $full[1], ['lat' => $half[2]['lat'], 'lng' => $full[1]['lng']], $half[2], ['lat' => $full[2]['lat'], 'lng' => $half[2]['lng']], $full[3]];

        $this->assertTrue($this->m->isValidPolygon($shape));
        $this->assertEqualsWithDelta(0.75, $this->m->measure($shape)['area_hectares'], 0.005);
    }

    public function test_a_proper_field_is_a_valid_polygon(): void
    {
        $this->assertTrue($this->m->isValidPolygon($this->rectangle(80, 60)));
    }

    public function test_fewer_than_three_points_is_not_a_field(): void
    {
        $this->assertFalse($this->m->isValidPolygon(array_slice($this->rectangle(100, 100), 0, 2)));
        $this->assertFalse($this->m->isValidPolygon([]));
    }

    public function test_a_bow_tie_with_crossing_edges_is_refused(): void
    {
        [$a, $b, $c, $d] = $this->rectangle(100, 100);

        $this->assertFalse($this->m->isValidPolygon([$a, $c, $b, $d]));
    }

    public function test_points_on_one_line_have_no_area_and_are_refused(): void
    {
        $line = [['lat' => 14.04, 'lng' => 120.65], ['lat' => 14.0405, 'lng' => 120.6505], ['lat' => 14.041, 'lng' => 120.651]];

        $this->assertSame(0.0, (float) $this->m->measure($line)['area_hectares']);
        $this->assertFalse($this->m->isValidPolygon($line));
    }

    public function test_a_repeated_point_is_refused(): void
    {
        $square = $this->rectangle(100, 100);
        $square[] = $square[1];

        $this->assertFalse($this->m->isValidPolygon($square));
    }

    public function test_a_ring_closed_by_repeating_the_first_point_is_refused(): void
    {
        // GeoJSON-style closed rings repeat the first point at the end. The service treats that as a duplicate point,
        // so a client that sends a closed ring gets the "lines cross" error (documented in the audit).
        $square = $this->rectangle(100, 100);
        $square[] = $square[0];

        $this->assertFalse($this->m->isValidPolygon($square));
    }
}
