<?php

namespace App\Services;

class FarmBoundaryMeasurementService
{
    private const EARTH_RADIUS_METERS = 6371008.8;

    /** @param array<int, array{lat: float|int, lng: float|int}> $coordinates */
    public function isValidPolygon(array $coordinates): bool
    {
        $unique = collect($coordinates)
            ->map(fn (array $point): string => $point['lat'].':'.$point['lng'])
            ->unique();

        if (count($coordinates) < 3 || $unique->count() !== count($coordinates)) {
            return false;
        }

        $count = count($coordinates);
        for ($first = 0; $first < $count; $first++) {
            $firstEnd = ($first + 1) % $count;
            for ($second = $first + 1; $second < $count; $second++) {
                $secondEnd = ($second + 1) % $count;

                if ($first === $second || $firstEnd === $second || $secondEnd === $first) {
                    continue;
                }

                if ($this->segmentsIntersect(
                    $coordinates[$first],
                    $coordinates[$firstEnd],
                    $coordinates[$second],
                    $coordinates[$secondEnd],
                )) {
                    return false;
                }
            }
        }

        return $this->measure($coordinates)['area_hectares'] > 0;
    }

    /** @param array<int, array{lat: float|int, lng: float|int}> $coordinates */
    public function measure(array $coordinates): array
    {
        $meanLatitude = collect($coordinates)->avg(fn (array $point): float => (float) $point['lat']);
        $meanLatitudeRadians = deg2rad((float) $meanLatitude);
        $projected = array_map(function (array $point) use ($meanLatitudeRadians): array {
            return [
                'x' => deg2rad((float) $point['lng']) * self::EARTH_RADIUS_METERS * cos($meanLatitudeRadians),
                'y' => deg2rad((float) $point['lat']) * self::EARTH_RADIUS_METERS,
            ];
        }, $coordinates);

        $area = 0.0;
        $perimeter = 0.0;
        $count = count($projected);
        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $area += ($projected[$index]['x'] * $projected[$next]['y'])
                - ($projected[$next]['x'] * $projected[$index]['y']);
            $perimeter += $this->distanceBetween($coordinates[$index], $coordinates[$next]);
        }

        return [
            'area_hectares' => round(abs($area) / 2 / 10000, 4),
            'perimeter_meters' => round($perimeter, 2),
        ];
    }

    private function distanceBetween(array $from, array $to): float
    {
        $latitudeDelta = deg2rad((float) $to['lat'] - (float) $from['lat']);
        $longitudeDelta = deg2rad((float) $to['lng'] - (float) $from['lng']);
        $fromLatitude = deg2rad((float) $from['lat']);
        $toLatitude = deg2rad((float) $to['lat']);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude) * sin($longitudeDelta / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1, sqrt($a)));
    }

    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $orientation = function (array $first, array $second, array $third): float {
            return (($second['lng'] - $first['lng']) * ($third['lat'] - $second['lat']))
                - (($second['lat'] - $first['lat']) * ($third['lng'] - $second['lng']));
        };

        $first = $orientation($a, $b, $c);
        $second = $orientation($a, $b, $d);
        $third = $orientation($c, $d, $a);
        $fourth = $orientation($c, $d, $b);

        return (($first > 0 && $second < 0) || ($first < 0 && $second > 0))
            && (($third > 0 && $fourth < 0) || ($third < 0 && $fourth > 0));
    }
}
