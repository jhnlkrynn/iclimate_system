<?php

namespace App\Support;

class LianBarangays
{
    private const COORDINATES = [
        'Bagong Pook' => [14.0182000, 120.6408000],
        'Balibago' => [13.9898000, 120.6524000],
        'Barangay 1 (Pob.)' => [14.0361000, 120.6507000],
        'Barangay 2 (Pob.)' => [14.0371000, 120.6530000],
        'Barangay 3 (Pob.)' => [14.0349000, 120.6489000],
        'Barangay 4 (Pob.)' => [14.0383000, 120.6484000],
        'Barangay 5 (Pob.)' => [14.0339000, 120.6525000],
        'Binubusan' => [14.0296000, 120.6364000],
        'Bungahan' => [14.0480000, 120.6285000],
        'Cumba' => [14.0205000, 120.7045000],
        'Humayingan' => [14.0108000, 120.6888000],
        'Kapito' => [14.0557000, 120.6765000],
        'Lumaniag' => [14.0479000, 120.6613000],
        'Luyahan' => [13.9926000, 120.6355000],
        'Malaruhatan' => [14.0119000, 120.6669000],
        'Matabungkay' => [13.9593000, 120.6227000],
        'Prenza' => [14.0353000, 120.6822000],
        'Puting-Kahoy' => [14.0290000, 120.7015000],
        'San Diego' => [13.9793000, 120.6118000],
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
        return self::COORDINATES[$barangay] ?? null;
    }
}
