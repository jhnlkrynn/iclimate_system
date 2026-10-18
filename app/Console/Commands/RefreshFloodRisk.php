<?php

namespace App\Console\Commands;

use App\Models\HeatmapArea;
use App\Services\Risk\BarangayMapLayers;
use App\Services\Risk\HeatMapLayerService;
use Illuminate\Console\Command;

class RefreshFloodRisk extends Command
{
    protected $signature = 'iclimate:refresh-flood-risk';
    protected $description = 'Persist flood-layer ratings from MAO flood attributes and saved Open-Meteo forecasts.';

    public function handle(BarangayMapLayers $layers, HeatMapLayerService $cache): int
    {
        $payload = $layers->layers('en', '2weeks')['flood'] ?? [];
        foreach ($payload['barangays'] ?? [] as $name => $row) {
            $area = HeatmapArea::query()->where('barangay', $name)->first();
            if (! $area) continue;
            $components = (array) $area->risk_components;
            $components['flood'] = ['level' => $row['level'] ?? 'nodata', 'updated_at' => now()->toIso8601String(), 'source' => 'MAO barangay profile + stored Open-Meteo forecast'];
            $area->risk_components = $components;
            $area->save();
        }
        $cache->forgetCache();
        $this->info('Refreshed flood risk for '.count($payload['barangays'] ?? []).' barangay(s).');
        return self::SUCCESS;
    }
}
