<?php

namespace Tests\Unit;

use App\Support\LianBarangays;
use App\Support\LianGeography;
use Tests\TestCase;

/** Positions are placed by the official boundary files, never by a place name printed on the map. */
class LianGeographyTest extends TestCase
{
    public function test_every_barangays_own_centre_is_inside_lian_and_inside_that_barangay(): void
    {
        foreach (LianBarangays::all() as $barangay) {
            [$lat, $lng] = LianBarangays::coordinateFor($barangay);

            $this->assertTrue(LianGeography::contains($lat, $lng), $barangay);
            $this->assertSame($barangay, LianGeography::barangayAt($lat, $lng), $barangay);
        }
    }

    public function test_neighbouring_towns_and_the_sea_are_not_lian(): void
    {
        // Nasugbu (north), Calatagan (south), Balayan (east), open water (west), Manila.
        foreach ([[14.0700, 120.6350], [13.9200, 120.6400], [13.9900, 120.7100], [14.0000, 120.5900], [14.5995, 120.9842]] as [$lat, $lng]) {
            $this->assertFalse(LianGeography::contains($lat, $lng), "{$lat}, {$lng}");
            $this->assertNull(LianGeography::barangayAt($lat, $lng), "{$lat}, {$lng}");
        }
    }

    public function test_an_outline_is_inside_only_when_all_of_it_is(): void
    {
        $inside = [['lat' => 14.0300, 'lng' => 120.6500], ['lat' => 14.0310, 'lng' => 120.6500], ['lat' => 14.0310, 'lng' => 120.6512]];
        $this->assertSame(['inside' => true, 'partly' => false, 'barangay' => 'San Diego'], LianGeography::locate($inside));

        $oneCornerOut = [$inside[0], $inside[1], ['lat' => 14.0700, 'lng' => 120.6350]];
        $this->assertSame(['inside' => false, 'partly' => true, 'barangay' => null], LianGeography::locate($oneCornerOut));

        $sideLeavesLian = [['lat' => 13.968067, 'lng' => 120.612469], ['lat' => 13.967618, 'lng' => 120.614915], ['lat' => 13.966375, 'lng' => 120.613423]];
        $this->assertSame(['inside' => false, 'partly' => true, 'barangay' => null], LianGeography::locate($sideLeavesLian));

        $allOut = [['lat' => 14.0700, 'lng' => 120.6350], ['lat' => 14.0710, 'lng' => 120.6350], ['lat' => 14.0710, 'lng' => 120.6360]];
        $this->assertSame(['inside' => false, 'partly' => false, 'barangay' => null], LianGeography::locate($allOut));

        $this->assertSame(['inside' => false, 'partly' => false, 'barangay' => null], LianGeography::locate([]));
    }

    public function test_barangay_names_read_naturally(): void
    {
        $this->assertSame('Barangay San Diego', LianGeography::barangayLabel('San Diego'));
        $this->assertSame('Barangay 1 (Pob.)', LianGeography::barangayLabel('Barangay 1 (Pob.)'));
    }
}
