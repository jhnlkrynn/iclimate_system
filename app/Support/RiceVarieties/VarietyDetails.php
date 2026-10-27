<?php

namespace App\Support\RiceVarieties;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;

/**
 * The Variety Details pop-up's data (GET /rice-varieties/{id}): every figure per season × establishment combination
 * (null where the trials have none, never 0), the reaction chips, grain facts and the recommendation domain. With the
 * farmer's own plan for this variety it also carries the planting date, for the "On your calendar" bar.
 */
final class VarietyDetails
{
    /** @return array<string, mixed> */
    public static function payload(RiceVariety $variety, ?FarmerPlantingSchedule $plan = null, ?string $plantUrl = null): array
    {
        $combos = [];
        $tons = [];
        foreach (RiceVariety::COMBOS as $combo) {
            $values = $variety->yield_values[$combo]['values'] ?? [];
            $regions = array_map(fn (array $v): array => [
                'label' => $v['label'], 't' => self::tons($v['kg']),
                'min' => self::tons($v['min'] ?? null), 'max' => self::tons($v['max'] ?? null),      // a trial range ("5918-8573")
            ], $values);
            // Max Yield: the highest trial figure, the top of a range included.
            $tons = [...$tons, ...array_map(fn (array $v): ?float => self::tons($v['max'] ?? $v['kg']), $values), self::tons($variety->{'yield_'.$combo})];
            $combos[$combo] = [
                'yield' => self::tons($variety->{'yield_'.$combo}),
                'yield_regions' => count($regions) > 1 ? array_values(array_filter($regions, fn ($r) => $r['label'] !== null)) : [],
                'maturity' => self::range($variety->range('maturity', $combo)),
                'height' => self::range($variety->range('height', $combo)),
                'tillers' => self::range($variety->range('tillers', $combo)),
            ];
        }
        $tons = array_filter($tons, fn ($t) => $t !== null);

        $hasData = fn (string $combo): bool => array_filter([$combos[$combo]['yield'], $combos[$combo]['maturity']['avg'], $combos[$combo]['height']['avg']], fn ($v) => $v !== null) !== [];
        $seasons = array_values(array_filter(['ds', 'ws'], fn (string $s): bool => $hasData('tpr_'.$s) || $hasData('dwsr_'.$s)));
        $methods = array_values(array_filter(['tpr', 'dwsr'], fn (string $m): bool => $hasData($m.'_ds') || $hasData($m.'_ws')));

        $first = collect(RiceVariety::TYPICAL_ORDER)->first($hasData) ?? 'tpr_ws';
        if ($plan !== null) {
            $wanted = ($plan->crop_establishment && $plan->crop_establishment !== 'Transplanted' ? 'dwsr' : 'tpr').'_'.($plan->season === 'Dry' ? 'ds' : 'ws');
            $first = $hasData($wanted) ? $wanted : $first;
        }

        return [
            'id' => $variety->id,
            'name' => $variety->variety_name,
            'nsic' => $variety->nsic_reg_no,
            'nsic_short' => $variety->shortNsic(),
            'line_name' => $variety->line_name,
            'breeder' => $variety->owner_breeder,
            'year' => $variety->year_approved,
            'type' => $variety->variety_type,
            'type_label' => $variety->variety_type ? __('rice_varieties.types.'.$variety->variety_type) : null,
            // Farm type chips: Irrigated / Rainfed, or one "Not specified".
            'farm_types' => array_map(fn (string $type): array => ['key' => $type, 'label' => __('rice_varieties.farm_types.'.$type)], $variety->farmTypes())
                ?: [['key' => 'not_specified', 'label' => __('rice_varieties.farm_types.not_specified')]],
            'source' => $variety->source,
            'is_active' => (bool) $variety->is_active,
            'default_combo' => $first,
            'combos' => $combos,
            'max_yield' => $tons !== [] ? max($tons) : null,
            'seed_production' => self::range($variety->range('maturity', 'seed_production')),
            'culm_strength' => $variety->culm_strength,
            'reactions' => [
                'pests' => self::reactions($variety->reactions['pests'] ?? [], RiceVariety::PESTS, 'pests'),
                'diseases' => self::reactions($variety->reactions['diseases'] ?? [], RiceVariety::DISEASES, 'diseases'),
            ],
            'amylose' => self::amylose($variety->amylose_content !== null ? (float) $variety->amylose_content : null),
            'grain_facts' => self::facts($variety->remarks),
            'recommendation' => [
                'location' => NsicCell::location($variety->adaptation),
                'season' => implode(', ', array_map(fn (string $s): string => __('rice_varieties.seasons.'.$s), $seasons)) ?: null,
                'establishment' => implode(', ', array_map(fn (string $m): string => __('rice_varieties.modal.'.$m), $methods)) ?: null,
                'adaptation' => $variety->adaptation,
            ],
            'planted' => $plan?->planting_date ? [
                'date' => $plan->planting_date->toDateString(),
                // Maturity counts from sowing: a transplanting date is that many seedling days later.
                'offset' => $plan->date_basis === 'transplanting' && ($plan->crop_establishment ?? 'Transplanted') === 'Transplanted' ? (int) ($plan->seedling_age_days ?? 21) : 0,
                'expected' => $plan->harvestDateForPlanning()?->toDateString(),
            ] : null,
            'plant_url' => $plantUrl,
        ];
    }

    private static function tons(int|float|string|null $kg): ?float
    {
        return $kg === null || (float) $kg <= 0 ? null : round((float) $kg / 1000, 1);
    }

    /** @param array{avg: float|null, min: float|null, max: float|null} $range */
    private static function range(array $range): array
    {
        $round = fn (?float $v): int|float|null => $v === null ? null : (fmod($v, 1.0) === 0.0 ? (int) $v : round($v, 1));

        return ['avg' => $round($range['avg']), 'min' => $round($range['min']), 'max' => $round($range['max'])];
    }

    /** @return array<int, array{key: string, label: string, raw: string|null, entries: array<int, array<string, mixed>>}> */
    private static function reactions(array $stored, array $keys, string $group): array
    {
        $level = fn (?string $code): array => ['level' => NsicCell::reactionLevel($code), 'level_label' => __('rice_varieties.reaction_levels.'.NsicCell::reactionLevel($code))];

        return array_map(function (string $key) use ($stored, $group, $level): array {
            $raw = $stored[$key]['raw'] ?? null;
            // Read again from the cell's text, so every variety follows the current reading (stations, typos).
            $parsed = $raw !== null ? NsicCell::reactions($raw) : ($stored[$key]['entries'] ?? []);
            $entries = array_map(fn (array $e): array => [
                'label' => $e['label'] ?? null,
                'station' => $e['station'] ?? null,
                'kind' => $e['kind'] ?? null,
                'code' => $e['code'] ?? null,
                'note' => $e['note'] ?? null,
            ] + $level($e['code'] ?? null), $parsed);

            // Results by testing station: one chip with the worst result, the stations listed under it.
            $summary = NsicCell::isByStation($parsed) ? NsicCell::summary($parsed) : null;

            return [
                'key' => $key,
                'label' => __('rice_varieties.'.$group.'.'.$key),
                'raw' => $raw,
                'by_station' => $summary !== null,
                'summary' => $summary === null ? null : ['code' => $summary['code'], 'varies' => $summary['varies'], 'sites' => $summary['sites']] + $level($summary['code']),
                'entries' => $entries ?: [['label' => null, 'station' => null, 'kind' => null, 'code' => null, 'note' => null] + $level(null)],
            ];
        }, $keys);
    }

    /** Low under 20 %, Intermediate 20–25 %, High over 25 %. */
    public static function amylose(?float $percent): ?array
    {
        if ($percent === null) {
            return null;
        }
        $level = $percent < 20 ? 'low' : ($percent <= 25 ? 'intermediate' : 'high');

        return ['value' => round($percent, 1), 'level' => $level, 'label' => __('rice_varieties.modal.amylose_levels.'.$level)];
    }

    /**
     * The remarks as short facts: "Premium milling recovery (72.8%), fair brown rice (78.9%) …. Long (6.6mm) and
     * slender grain shape." → one line per sentence (decimals such as 6.6 are not split).
     *
     * @return array<int, string>
     */
    public static function facts(?string $remarks): array
    {
        $remarks = NsicCell::text($remarks);
        if ($remarks === null) {
            return [];
        }
        $parts = preg_split('/(?<=[.;])\s*(?=[A-Z])/u', $remarks) ?: [$remarks];

        return array_values(array_filter(array_map(
            fn (string $part): string => ucfirst(rtrim(trim($part), ' .;')),
            $parts,
        ), fn (string $part): bool => $part !== ''));
    }
}
