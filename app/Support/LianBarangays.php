<?php

namespace App\Support;

class LianBarangays
{
    /**
     * Representative point per barangay, used as the query location for
     * live per-barangay weather (LianBarangayWeatherService) and as the
     * default map marker position. Each point is the polygon centroid of
     * that barangay's own feature in public/geojson/lian-barangays.geojson
     * (verified to fall inside that polygon — see
     * tests/Unit/LianBarangaysCoordinatesTest.php), NOT hand-guessed.
     *
     * History: the coordinates here before the 2026-09 audit fix were
     * hand-entered and wrong for 17 of 19 barangays — most landed inside a
     * *different* barangay's polygon (e.g. the old "Balibago" point was
     * actually inside Puting-Kahoy), and 5 landed outside every barangay
     * boundary in the file. That silently corrupted the live-weather input
     * to the risk score for those barangays. These values are recomputed
     * directly from the shipped GeoJSON polygons, not re-guessed.
     */
    private const COORDINATES = [
        'Bagong Pook' => [14.0320331, 120.6807593],
        'Balibago' => [13.9413816, 120.6489014],
        'Barangay 1 (Pob.)' => [14.0354539, 120.6510264],
        'Barangay 2 (Pob.)' => [14.0381382, 120.6513488],
        'Barangay 3 (Pob.)' => [14.0405819, 120.6507332],
        'Barangay 4 (Pob.)' => [14.0379401, 120.6536591],
        'Barangay 5 (Pob.)' => [14.0413525, 120.6528515],
        'Binubusan' => [13.9694821, 120.6384280],
        'Bungahan' => [14.0470903, 120.6379867],
        'Cumba' => [13.9718581, 120.6609465],
        'Humayingan' => [13.9967494, 120.6724865],
        'Kapito' => [14.0162390, 120.6689030],
        'Lumaniag' => [13.9946196, 120.6343614],
        'Luyahan' => [13.9641912, 120.6176801],
        'Malaruhatan' => [14.0383984, 120.6654801],
        'Matabungkay' => [13.9467451, 120.6249676],
        'Prenza' => [14.0128685, 120.6398701],
        'Puting-Kahoy' => [13.9894782, 120.6534129],
        'San Diego' => [14.0336258, 120.6335108],
    ];

    public static function all(): array
    {
        return [
            'Bagong Pook',
            'Balibago',
            'Barangay 1 (Pob.)',
            'Barangay 2 (Pob.)',
            'Barangay 3 (Pob.)',
            'Barangay 4 (Pob.)',
            'Barangay 5 (Pob.)',
            'Binubusan',
            'Bungahan',
            'Cumba',
            'Humayingan',
            'Kapito',
            'Lumaniag',
            'Luyahan',
            'Malaruhatan',
            'Matabungkay',
            'Prenza',
            'Puting-Kahoy',
            'San Diego',
        ];
    }

    public static function options(bool $includeAll = true): array
    {
        $options = collect(self::all())->mapWithKeys(fn (string $barangay): array => [
            $barangay => $barangay,
        ])->all();

        return $includeAll ? ['' => 'All Barangays'] + $options : $options;
    }

    public static function coordinates(): array
    {
        return self::COORDINATES;
    }

    public static function coordinateFor(string $barangay): ?array
    {
        $canonical = self::canonicalName($barangay);

        return $canonical ? self::COORDINATES[$canonical] : null;
    }

    public static function canonicalName(?string $barangay): ?string
    {
        $needle = self::normalize((string) $barangay);

        if ($needle === '') {
            return null;
        }

        foreach (array_keys(self::COORDINATES) as $name) {
            if (self::normalize($name) === $needle) {
                return $name;
            }
        }

        // Keep local Poblacion aliases at the system boundary while all map
        // and risk logic uses one stable canonical name.
        return match ($needle) {
            '1 bonbon', 'bonbon', '1 bonbon poblacion' => 'Barangay 1 (Pob.)',
            '5 tabok city', 'tabok city', '5 tabok city poblacion' => 'Barangay 5 (Pob.)',
            default => null,
        };
    }

    private static function normalize(string $barangay): string
    {
        $value = strtolower($barangay);
        $value = str_replace(['barangay', 'brgy', '(pob.)', 'pob.', 'poblacion', '-', '.'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }
}
