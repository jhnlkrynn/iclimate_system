<?php

namespace App\Support;

use RuntimeException;

/**
 * Where a map position is, according to the official boundary files the app ships
 * (public/geojson/lian-boundary.geojson for the municipality, lian-barangays.geojson for its 19 barangays):
 * inside Lian or not, and in which barangay. Only these shapes decide; a place name printed on a map tile never does.
 *
 * The farm map (resources/js/farmer-dashboard.js) runs the same checks in the browser from the same two files, so what
 * the farmer sees is what the server accepts.
 */
class LianGeography
{
    /** @var array<int, array<int, array<int, array{0: float, 1: float}>>>|null polygons -> rings -> [lng, lat] */
    private static ?array $municipality = null;

    /** @var array<string, array<int, array<int, array<int, array{0: float, 1: float}>>>>|null barangay name -> polygons */
    private static ?array $barangays = null;

    public static function contains(float $lat, float $lng): bool
    {
        return self::inPolygons($lng, $lat, self::municipality());
    }

    /** The barangay whose own boundary holds this position; null outside Lian. */
    public static function barangayAt(float $lat, float $lng): ?string
    {
        foreach (self::barangays() as $name => $polygons) {
            if (self::inPolygons($lng, $lat, $polygons)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Where a farm outline is. `inside` is true only when the whole outline is in Lian: every corner, and no side
     * cutting across the municipal boundary. `partly` tells "some of it is outside" from "all of it is outside".
     * The barangay is the one holding most of the outline's corners and its middle point; a tie goes to the barangay
     * that comes first in the official list, so the answer never changes for the same outline.
     *
     * @param  array<int, array{lat: float|int|string, lng: float|int|string}>  $points
     * @return array{inside: bool, partly: bool, barangay: ?string}
     */
    public static function locate(array $points): array
    {
        $points = array_values(array_map(fn (array $p): array => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']], $points));
        if ($points === []) {
            return ['inside' => false, 'partly' => false, 'barangay' => null];
        }

        $cornersInside = count(array_filter($points, fn (array $p): bool => self::contains($p['lat'], $p['lng'])));
        if ($cornersInside < count($points)) {
            return ['inside' => false, 'partly' => $cornersInside > 0, 'barangay' => null];
        }
        if (self::crossesBoundary($points)) {
            return ['inside' => false, 'partly' => true, 'barangay' => null];
        }

        $votes = array_fill_keys(array_keys(self::barangays()), 0);
        $middle = ['lat' => array_sum(array_column($points, 'lat')) / count($points), 'lng' => array_sum(array_column($points, 'lng')) / count($points)];
        foreach ([...$points, $middle] as $p) {
            $name = self::barangayAt($p['lat'], $p['lng']);
            if ($name !== null) {
                $votes[$name]++;
            }
        }
        $best = max($votes);

        return ['inside' => true, 'partly' => false, 'barangay' => $best > 0 ? array_search($best, $votes, true) : null];
    }

    /** "Barangay San Diego", but "Barangay 1 (Pob.)" stays as it is. */
    public static function barangayLabel(string $name): string
    {
        return str_starts_with($name, 'Barangay') ? $name : 'Barangay '.$name;
    }

    // ------------------------------------------------------------------ shapes

    /** Does any side of the outline cut across the municipal boundary? */
    private static function crossesBoundary(array $points): bool
    {
        $count = count($points);
        if ($count < 2) {
            return false;
        }
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        [$south, $north, $west, $east] = [min($lats), max($lats), min($lngs), max($lngs)];
        $sides = $count === 2 ? 1 : $count;

        foreach (self::municipality() as $polygon) {
            foreach ($polygon as $ring) {
                for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
                    [$ax, $ay] = $ring[$i];
                    [$bx, $by] = $ring[$i + 1];
                    // Almost every boundary segment is nowhere near the farm: skipped by a plain box test.
                    if (max($ax, $bx) < $west || min($ax, $bx) > $east || max($ay, $by) < $south || min($ay, $by) > $north) {
                        continue;
                    }
                    for ($s = 0; $s < $sides; $s++) {
                        $from = $points[$s];
                        $to = $points[($s + 1) % $count];
                        if (self::segmentsCross($from['lng'], $from['lat'], $to['lng'], $to['lat'], $ax, $ay, $bx, $by)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private static function segmentsCross(float $ax, float $ay, float $bx, float $by, float $cx, float $cy, float $dx, float $dy): bool
    {
        $side = fn (float $px, float $py, float $qx, float $qy, float $rx, float $ry): float => ($qx - $px) * ($ry - $py) - ($qy - $py) * ($rx - $px);
        $d1 = $side($cx, $cy, $dx, $dy, $ax, $ay);
        $d2 = $side($cx, $cy, $dx, $dy, $bx, $by);
        $d3 = $side($ax, $ay, $bx, $by, $cx, $cy);
        $d4 = $side($ax, $ay, $bx, $by, $dx, $dy);

        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0)) && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    /** Inside one of the polygons: within its outer ring and not in one of its holes. */
    private static function inPolygons(float $lng, float $lat, array $polygons): bool
    {
        foreach ($polygons as $rings) {
            if (! self::inRing($lng, $lat, $rings[0])) {
                continue;
            }
            foreach (array_slice($rings, 1) as $hole) {
                if (self::inRing($lng, $lat, $hole)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    private static function inRing(float $lng, float $lat, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1, $n = count($ring); $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function municipality(): array
    {
        return self::$municipality ??= array_merge(...array_map(self::polygonsOf(...), self::features('lian-boundary')));
    }

    private static function barangays(): array
    {
        if (self::$barangays === null) {
            $byName = [];
            foreach (self::features('lian-barangays') as $feature) {
                $name = LianBarangays::canonicalName((string) ($feature['properties']['brgy_name'] ?? ''));
                if (in_array($name, LianBarangays::all(), true)) {
                    $byName[$name] = self::polygonsOf($feature);
                }
            }
            // The official list's order, so a tie is always settled the same way.
            self::$barangays = array_filter(array_map(fn (string $name) => $byName[$name] ?? null, array_combine(LianBarangays::all(), LianBarangays::all())));
        }

        return self::$barangays;
    }

    /** @return array<int, array<string, mixed>> */
    private static function features(string $file): array
    {
        $json = json_decode((string) @file_get_contents(public_path('geojson/'.$file.'.geojson')), true);
        if (! is_array($json) || empty($json['features'])) {
            throw new RuntimeException('The Lian boundary file "'.$file.'" could not be read.');
        }

        return $json['features'];
    }

    private static function polygonsOf(array $feature): array
    {
        $geometry = $feature['geometry'] ?? [];

        return match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
    }
}
