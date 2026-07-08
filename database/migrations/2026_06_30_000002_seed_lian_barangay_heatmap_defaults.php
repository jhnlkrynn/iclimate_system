<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $areas = [
            ['barangay' => 'Bagong Pook', 'latitude' => 14.0182000, 'longitude' => 120.6408000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Balibago', 'latitude' => 13.9898000, 'longitude' => 120.6524000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Barangay 1 (Pob.)', 'latitude' => 14.0361000, 'longitude' => 120.6507000, 'risk_level' => 'Low', 'risk_type' => 'Flood', 'risk_score' => 0.25, 'predicted_yield' => 4.80, 'rainfall_status' => 'Adequate rainfall', 'planting_advisory' => 'Planting conditions are favorable.', 'irrigation_recommendation' => 'No immediate irrigation support needed.'],
            ['barangay' => 'Barangay 2 (Pob.)', 'latitude' => 14.0371000, 'longitude' => 120.6530000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Barangay 3 (Pob.)', 'latitude' => 14.0349000, 'longitude' => 120.6489000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Barangay 4 (Pob.)', 'latitude' => 14.0383000, 'longitude' => 120.6484000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Barangay 5 (Pob.)', 'latitude' => 14.0339000, 'longitude' => 120.6525000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Binubusan', 'latitude' => 14.0296000, 'longitude' => 120.6364000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.60, 'predicted_yield' => 3.70, 'rainfall_status' => 'Moderate rainfall', 'planting_advisory' => 'Proceed with planting while monitoring rainfall changes.', 'irrigation_recommendation' => 'Prepare supplemental irrigation if dry days continue.'],
            ['barangay' => 'Bungahan', 'latitude' => 14.0480000, 'longitude' => 120.6285000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Cumba', 'latitude' => 14.0205000, 'longitude' => 120.7045000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Humayingan', 'latitude' => 14.0108000, 'longitude' => 120.6888000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Kapito', 'latitude' => 14.0557000, 'longitude' => 120.6765000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Lumaniag', 'latitude' => 14.0479000, 'longitude' => 120.6613000, 'risk_level' => 'Low', 'risk_type' => 'Flood', 'risk_score' => 0.30, 'predicted_yield' => 4.60, 'rainfall_status' => 'Adequate rainfall', 'planting_advisory' => 'Planting window is favorable.', 'irrigation_recommendation' => 'Maintain routine irrigation monitoring.'],
            ['barangay' => 'Luyahan', 'latitude' => 13.9926000, 'longitude' => 120.6355000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'Malaruhatan', 'latitude' => 14.0119000, 'longitude' => 120.6669000, 'risk_level' => 'High', 'risk_type' => 'Heat', 'risk_score' => 0.90, 'predicted_yield' => 2.80, 'rainfall_status' => 'Low rainfall', 'planting_advisory' => 'Delay planting or use drought-tolerant practices.', 'irrigation_recommendation' => 'Prioritize irrigation support within 7 days.'],
            ['barangay' => 'Matabungkay', 'latitude' => 13.9593000, 'longitude' => 120.6227000, 'risk_level' => 'High', 'risk_type' => 'Typhoon', 'risk_score' => 0.85, 'predicted_yield' => 2.95, 'rainfall_status' => 'High rainfall', 'planting_advisory' => 'Avoid low-lying plots until drainage improves.', 'irrigation_recommendation' => 'Focus on drainage clearing before irrigation release.'],
            ['barangay' => 'Prenza', 'latitude' => 14.0353000, 'longitude' => 120.6822000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.55, 'predicted_yield' => 3.90, 'rainfall_status' => 'Moderate rainfall', 'planting_advisory' => 'Planting is acceptable with close field monitoring.', 'irrigation_recommendation' => 'Schedule rotational irrigation support.'],
            ['barangay' => 'Puting-Kahoy', 'latitude' => 14.0290000, 'longitude' => 120.7015000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
            ['barangay' => 'San Diego', 'latitude' => 13.9793000, 'longitude' => 120.6118000, 'risk_level' => 'Moderate', 'risk_type' => 'Drought', 'risk_score' => 0.50, 'predicted_yield' => null, 'rainfall_status' => 'Pending climate update', 'planting_advisory' => 'Review latest climate and rice production data before planting.', 'irrigation_recommendation' => 'Monitor field water availability and update after the next rainfall record.'],
        ];

        foreach ($areas as $area) {
            DB::table('heatmap_areas')->updateOrInsert(
                ['barangay' => $area['barangay']],
                array_merge($area, [
                    'description' => 'Barangay agricultural risk score generated from rainfall, temperature, season, and yield indicators.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('heatmap_areas')
            ->whereIn('barangay', [
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
            ])
            ->delete();
    }
};
