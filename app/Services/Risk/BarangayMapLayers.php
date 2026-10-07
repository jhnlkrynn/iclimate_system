<?php

namespace App\Services\Risk;

use App\Models\BarangayProfile;
use App\Services\Analytics\FarmerDashboardService;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: the Heat Map's extra layers next to This Week / 2 Weeks. Each layer has
 * the same shape as a HeatMapPanel range, so the page and resources/js/heat-map.js show it the same way:
 *  - flood / drought: the MAO's barangay records (Barangays page): high = prone, low = not, nodata = not marked;
 *  - yield: the average of the farms' saved yield estimates this season (low colour = good yield).
 * Only stored data is read; the yield averages are kept for ten minutes.
 */
class BarangayMapLayers
{
    public const KEYS = ['flood', 'drought', 'yield'];

    public const YIELD_GOOD = 4.5;

    public const YIELD_FAIR = 3.5;

    /** @return array<string, array<string, mixed>> */
    public function layers(string $locale): array
    {
        $profiles = rescue(fn () => SchemaCache::hasTable('barangay_profiles') ? BarangayProfile::query()->get()->keyBy('barangay') : collect(), collect(), false);
        $marked = $profiles->filter(fn (BarangayProfile $p) => $p->flood_prone || $p->drought_prone || $p->risk_classification || $p->farm_area_hectares !== null);
        $yields = $this->yields();

        $layers = [];
        foreach (['flood' => 'flood_prone', 'drought' => 'drought_prone'] as $key => $column) {
            $layers[$key] = $this->layer($key, $locale, function (string $name) use ($profiles, $marked, $column): string {
                if ($profiles->get($name)?->{$column}) {
                    return 'high';
                }

                // Before the MAO has marked any barangay the layer says "not checked", never "not prone".
                return $marked->isEmpty() ? 'nodata' : 'low';
            }, fn (string $name, string $level) => [
                'meaning' => $this->t($key.'_meaning.'.$level, $locale),
                'action' => $this->t($key.'_action.'.$level, $locale),
            ], $this->t('source', $locale), $profiles->max('updated_at'));
        }

        $layers['yield'] = $this->layer('yield', $locale, function (string $name) use ($yields): string {
            $y = $yields[$name]['average'] ?? null;

            return match (true) {
                $y === null => 'nodata',
                $y >= self::YIELD_GOOD => 'low',
                $y >= self::YIELD_FAIR => 'moderate',
                default => 'high',
            };
        }, fn (string $name, string $level) => [
            'meaning' => isset($yields[$name])
                ? $this->t('yield_meaning', $locale, ['value' => number_format($yields[$name]['average'], 2), 'count' => $yields[$name]['count']])
                : $this->t('yield_none', $locale),
            'action' => $this->t('yield_action.'.$level, $locale),
        ], $this->t('yield_source', $locale), null);

        return $layers;
    }

    /** Average saved yield estimate (t/ha) and the number of estimates, per barangay that has any. */
    private function yields(): array
    {
        return Cache::remember('manuscript:map-layer-yields', 600, function (): array {
            $out = [];
            foreach (LianBarangays::all() as $barangay) {
                try {
                    $estimates = FarmerDashboardService::storedEstimates($barangay);
                } catch (Throwable) {
                    continue;
                }
                $values = $estimates->map(fn (array $e) => (float) ($e['yield_tons_per_hectare'] ?? $e['predicted_yield'] ?? 0))->filter(fn ($v) => $v > 0);
                if ($values->isNotEmpty()) {
                    $out[$barangay] = ['average' => round($values->avg(), 2), 'count' => $values->count()];
                }
            }

            return $out;
        });
    }

    /**
     * One layer in the HeatMapPanel range shape.
     *
     * @param  callable(string): string  $levelOf
     * @param  callable(string, string): array{meaning: string, action: string}  $text
     */
    private function layer(string $key, string $locale, callable $levelOf, callable $text, string $source, mixed $updatedAt): array
    {
        $labels = $this->t($key.'_levels', $locale);
        $barangays = [];
        $counts = ['low' => 0, 'moderate' => 0, 'high' => 0, 'nodata' => 0];
        foreach (LianBarangays::all() as $name) {
            $level = $levelOf($name);
            $counts[$level]++;
            $barangays[$name] = [
                'barangay' => $name,
                'name' => HeatMapPanel::displayName($name),
                'level' => $level,
                'label' => $labels[$level] ?? $level,
                'also' => [],
                'partial' => null,
                'numbers' => [],
            ] + $text($name, $level);
        }

        return [
            'range' => $key,
            'layer' => true,
            'days' => 0,
            'state' => $counts['nodata'] === count($barangays) ? 'nodata' : 'ok',
            'updated_at' => $updatedAt,
            'delayed' => false,
            'title' => $this->t($key.'_title', $locale),
            'counts' => $counts,
            'count_lines' => collect(['high', 'moderate', 'low', 'nodata'])
                ->filter(fn (string $level) => $counts[$level] > 0 && isset($labels[$level]))
                ->map(fn (string $level) => ['level' => $level, 'text' => trans_choice('farmer.heat_map.layers.count', $counts[$level], ['count' => $counts[$level]], $locale).': '.$labels[$level]])
                ->values()->all(),
            'outlook' => [],
            'labels' => $labels,
            'source' => $source,
            'barangays' => $barangays,
        ];
    }

    private function t(string $key, string $locale, array $replace = []): mixed
    {
        return trans('farmer.heat_map.layers.'.$key, $replace, $locale);
    }
}
