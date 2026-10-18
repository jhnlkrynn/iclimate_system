<?php

namespace App\Services\Risk;

use App\Models\BarangayProfile;
use App\Models\FarmerPlantingSchedule;
use App\Models\HeatmapArea;
use App\Services\Prediction\BarangayYieldForecastService;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class HeatMapLayerService
{
    public const CACHE_PREFIX = 'heat-map:api:v1:';
    private const VERSION_KEY = 'heat-map:api-version';

    public function __construct(private readonly BarangayMapLayers $layers) {}

    public function forgetCache(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    /** @return array<string, mixed> */
    public function get(string $layer, string $period, ?\App\Models\User $user = null): array
    {
        $layer = in_array($layer, ['weather_risk', 'flood', 'drought', 'yield'], true) ? $layer : 'weather_risk';
        $period = $period === '2weeks' ? '2weeks' : 'week';
        $scope = MaoScope::barangays($user);
        $scopeKey = $scope === null ? 'all' : sha1(implode('|', $scope));
        $key = self::CACHE_PREFIX.Cache::get(self::VERSION_KEY, 1).':'.$layer.':'.$period.':'.$scopeKey;

        return Cache::remember($key, now()->addMinutes(10), fn (): array => $this->build($layer, $period, $scope));
    }

    /** @param array<int, string>|null $scope @return array<string, mixed> */
    private function build(string $layer, string $period, ?array $scope): array
    {
        $days = $period === '2weeks' ? 14 : 7;
        $names = $scope === null ? LianBarangays::all() : array_values(array_filter(LianBarangays::all(), fn (string $name): bool => in_array($name, array_map(fn ($v) => LianBarangays::canonicalName($v) ?? $v, $scope), true)));
        $source = $layer === 'weather_risk' ? 'Stored heatmap_areas / barangay weather risk' : 'Stored Open-Meteo barangay forecast and MAO barangay profile';
        $rows = [];
        $data = ['updated_at' => null, 'barangays' => []];
        $map = ['generated_at' => null, 'barangays' => []];
        if ($layer === 'weather_risk') {
            $map = app(BarangayRiskMapService::class)->snapshot();
            $entries = collect($map['barangays'] ?? [])->keyBy('barangay');
            foreach ($names as $name) {
                $e = $entries->get($name, []); $level = strtolower((string) ($e['risk_level'] ?? 'unavailable')); if (! in_array($level, ['low','moderate','high','severe'], true)) $level = 'unavailable';
                $rows[] = $this->row($name, $level, $e['risk_score'] ?? null, [$e['risk_type'] ?? 'Stored agricultural risk'], $e['description'] ?? null);
            }
        } elseif ($layer === 'yield') {
            [$rows, $data['updated_at']] = $this->yieldRows($names, $period);
            $source = 'Based on trained model · '.($period === '2weeks' ? "next 2 weeks' forecast" : "this week's forecast");
        } else {
            $data = $this->layers->layers('en', $period)[$layer] ?? ['barangays' => [], 'updated_at' => null, 'source' => $source, 'labels' => []];
            $profiles = $layer === 'flood' && Schema::hasTable('barangay_profiles')
                ? BarangayProfile::query()->get()->keyBy(fn (BarangayProfile $profile): string => LianBarangays::canonicalName($profile->barangay) ?? $profile->barangay)
                : collect();
            $storedFlood = $layer === 'flood' && Schema::hasTable('heatmap_areas')
                ? HeatmapArea::query()->get()->keyBy(fn (HeatmapArea $area): string => LianBarangays::canonicalName($area->barangay) ?? $area->barangay)
                : collect();
            $fallbackUpdated = null;
            foreach ($names as $name) {
                $e = $data['barangays'][$name] ?? []; $level = (string) ($e['level'] ?? 'unavailable');
                $profile = $profiles->get($name);
                if ($layer === 'flood' && $profile?->flood_prone) $level = 'flood_prone';
                if ($layer === 'flood' && $level === 'high') $level = 'flood_prone';
                if ($level === 'nodata') $level = 'unavailable';
                $stored = $storedFlood->get($name);
                $storedComponent = is_array($stored?->risk_components) ? ($stored->risk_components['flood'] ?? null) : null;
                if ($layer === 'flood' && $level === 'unavailable' && is_array($storedComponent)) {
                    $storedLevel = (string) ($storedComponent['level'] ?? '');
                    $level = match ($storedLevel) {
                        'high', 'flood_prone' => 'flood_prone',
                        'moderate', 'low' => $storedLevel,
                        default => 'unavailable',
                    };
                    if ($level !== 'unavailable') {
                        $fallbackUpdated = max($fallbackUpdated ?? '', (string) ($storedComponent['updated_at'] ?? $stored->updated_at?->toIso8601String()));
                    }
                }
                $rows[] = $this->row($name, $level, null, array_filter([$profile?->flood_prone ? 'MAO flood-prone attribute' : null, $level !== 'unavailable' && $storedComponent ? 'Most recent stored flood rating' : null, (string) ($e['meaning'] ?? ''), (string) ($e['action'] ?? '')]), $e['meaning'] ?? ($storedComponent['source'] ?? null));
            }
            if ($layer === 'flood' && $fallbackUpdated !== null && ($data['updated_at'] ?? null) === null) $data['updated_at'] = $fallbackUpdated;
        }
        $counts = collect($rows)->countBy('level')->all();
        $standing = $layer === 'flood' ? (float) FarmerPlantingSchedule::query()->whereNull('harvested_on')->whereIn('barangay', collect($rows)->where('level', 'flood_prone')->pluck('name'))->sum('farm_area_hectares') : null;
        return ['layer' => $layer, 'period' => $period, 'updated_at' => $data['updated_at'] ?? ($map['generated_at'] ?? null), 'source' => $source, 'summary' => ['count' => count($rows), 'levels' => $counts, 'standing_crop_hectares' => $standing], 'barangays' => $rows];
    }

    /**
     * Expected yield per barangay from the trained rice-yield model for This Week / 2 Weeks (BarangayYieldForecastService:
     * the area-weighted average of its farms' saved estimates, else the barangay's own run, with that window's forecast),
     * next to the season estimate the farmer dashboard shows. Colours follow the legend: Good is green (level low), Fair
     * yellow (moderate), Low orange (high), no estimate gray.
     *
     * @param  array<int, string>  $names
     * @return array{0: list<array<string, mixed>>, 1: ?string} rows and the newest generation time
     */
    private function yieldRows(array $names, string $period): array
    {
        $yields = rescue(fn (): array => app(BarangayYieldForecastService::class)->all($period), [], false);
        $labels = trans('farmer.heat_map.layers.yield_levels', [], 'en');
        $rows = [];
        $newest = null;
        foreach ($names as $name) {
            $p = $yields[$name] ?? [];
            $value = is_numeric($p['predicted_yield'] ?? null) ? round((float) $p['predicted_yield'], 2) : null;
            $class = match (true) {
                $value === null => 'none',
                $value >= BarangayMapLayers::YIELD_GOOD => 'good',
                $value >= BarangayMapLayers::YIELD_FAIR => 'fair',
                default => 'low',
            };
            $level = ['good' => 'low', 'fair' => 'moderate', 'low' => 'high', 'none' => 'unavailable'][$class];
            $basis = $p['basis'] ?? null;
            $reason = match ($basis) {
                'barangay_model' => 'Trained rice-yield model run for this barangay.',
                'farm_average' => 'Average of '.($p['farms_used'] ?? 0).' farm estimates from the trained model, weighted by farm size.',
                default => 'No estimate yet.',
            };
            $row = $this->row($name, $level, $value, [$reason], $reason);
            $row['label'] = $labels[$level === 'unavailable' ? 'nodata' : $level] ?? $row['label'];
            $row['yield'] = [
                't_ha' => $value,
                'class' => $class,
                'basis' => $basis,
                'period' => $period,
                'season_t_ha' => is_numeric($p['season_yield'] ?? null) ? round((float) $p['season_yield'], 2) : null,
                'vs_season' => $value !== null && is_numeric($p['season_yield'] ?? null) ? round($value - (float) $p['season_yield'], 2) : null,
                'farms_used' => (int) ($p['farms_used'] ?? 0),
                'season' => $p['season_label'] ?? null,
                'model_version' => $p['model_version'] ?? null,
                'generated_at' => $value !== null ? ($p['prediction_timestamp'] ?? null) : null,
            ];
            if ($row['yield']['generated_at'] !== null) {
                $newest = max($newest ?? '', (string) $row['yield']['generated_at']);
            }
            $rows[] = $row;
        }

        return [$rows, $newest];
    }

    /** A stable id for a barangay: "Humayingan" -> humayingan, "Barangay 1 (Pob.)" -> barangay_1_pob, "Puting-Kahoy" -> puting_kahoy. */
    public static function key(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(LianBarangays::canonicalName($name) ?? $name)), '_');
    }

    private function row(string $name, string $level, mixed $score, array $drivers, ?string $detail): array
    {
        return ['key' => self::key($name), 'name' => $name, 'level' => $level, 'score' => $score, 'label' => str_replace('_', ' ', ucfirst($level)), 'drivers' => array_values(array_filter($drivers)), 'details' => ['reason' => $detail]];
    }
}
