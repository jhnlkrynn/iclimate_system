<?php

namespace App\Support;

use Illuminate\Support\Collection;

class LianFarmTypes
{
    private const ASSOCIATIONS = [
        ['barangay' => 'Balibago', 'association' => 'Balibago Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 34.27, 'farmers' => 51],
        ['barangay' => 'Binubusan', 'association' => 'Cuaco Irrigator\'s Association', 'farm_type' => 'Irrigated', 'area_hectares' => 77.97, 'farmers' => 102],
        ['barangay' => 'Bungahan', 'association' => 'Bungahan Baldeo Irrigators Association Inc', 'farm_type' => 'Irrigated', 'area_hectares' => 118.92, 'farmers' => 95],
        ['barangay' => 'Kapito', 'association' => 'Molino-Resagwa Farmers Irrigators Association Inc', 'farm_type' => 'Irrigated', 'area_hectares' => 38.17, 'farmers' => 43],
        ['barangay' => 'Kapito', 'association' => 'Lumang Tubigan Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 45.51, 'farmers' => 48],
        ['barangay' => 'Kapito', 'association' => 'Saluysoy Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 27.00, 'farmers' => 37],
        ['barangay' => 'Luyahan', 'association' => 'Luyahan Farmers Association', 'farm_type' => 'Rainfed', 'area_hectares' => 20.27, 'farmers' => 43],
        ['barangay' => 'Malaruhatan', 'association' => 'Malaruhatan Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 15.55, 'farmers' => 24],
        ['barangay' => 'Matabungkay', 'association' => 'Matabungkay Farmers Association', 'farm_type' => 'Rainfed', 'area_hectares' => 22.19, 'farmers' => 48],
        ['barangay' => 'Prenza', 'association' => 'Prenza Bagbag Bungahan Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 85.62, 'farmers' => 78],
        ['barangay' => 'Prenza', 'association' => 'Sitio Calaca Barangay Prenza Lian, Batangas Farmers Assn. Inc.', 'farm_type' => 'Irrigated', 'area_hectares' => 49.65, 'farmers' => 51],
        ['barangay' => 'Prenza', 'association' => 'Laguluan Spring Irrigators FA', 'farm_type' => 'Irrigated', 'area_hectares' => 33.95, 'farmers' => 54],
        ['barangay' => 'Prenza', 'association' => 'Masipag Prenza Organic Farmers Association', 'farm_type' => 'Irrigated', 'area_hectares' => 4.50, 'farmers' => 11],
        ['barangay' => 'Prenza', 'association' => 'Prenza Ladron Balanoy Sampalocan Calumpit Irrigators Association Inc.', 'farm_type' => 'Irrigated', 'area_hectares' => 88.67, 'farmers' => 132],
        ['barangay' => 'San Diego', 'association' => 'Samahan ng Gintong Pagasa Ng Magsasaka Ng Lian, Inc.', 'farm_type' => 'Irrigated', 'area_hectares' => 17.20, 'farmers' => 19],
        ['barangay' => 'San Diego', 'association' => 'Tan-ag Farmers Association', 'farm_type' => 'Rainfed', 'area_hectares' => 48.13, 'farmers' => 60],
    ];

    public static function summaryFor(string $barangay): ?array
    {
        $records = self::records()
            ->filter(fn (array $record): bool => strcasecmp($record['barangay'], trim($barangay)) === 0)
            ->values();

        if ($records->isEmpty()) {
            return null;
        }

        $rainfedArea = round((float) $records->where('farm_type', 'Rainfed')->sum('area_hectares'), 2);
        $irrigatedArea = round((float) $records->where('farm_type', 'Irrigated')->sum('area_hectares'), 2);

        return [
            'farm_system' => self::label($rainfedArea, $irrigatedArea),
            'farm_system_basis' => 'farm_type_location_workbook',
            'farm_system_latest' => null,
            'farm_system_year' => null,
            'farm_system_season' => null,
            'rainfed_record_count' => $records->where('farm_type', 'Rainfed')->count(),
            'irrigated_record_count' => $records->where('farm_type', 'Irrigated')->count(),
            'rainfed_area_hectares' => $rainfedArea,
            'irrigated_area_hectares' => $irrigatedArea,
            'farm_association_count' => $records->count(),
            'farm_system_source' => 'Irrigated and non-irrigated farm types location workbook',
        ];
    }

    public static function records(): Collection
    {
        return collect(self::ASSOCIATIONS);
    }

    private static function label(float $rainfedArea, float $irrigatedArea): string
    {
        if ($rainfedArea > 0 && $irrigatedArea > 0) {
            return 'Mixed';
        }

        if ($rainfedArea > 0) {
            return 'Rainfed';
        }

        if ($irrigatedArea > 0) {
            return 'Irrigated';
        }

        return 'Unknown';
    }
}
