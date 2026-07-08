<?php

namespace App\Support;

class LianBarangays
{
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
}
