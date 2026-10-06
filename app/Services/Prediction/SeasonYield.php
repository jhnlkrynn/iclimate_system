<?php

namespace App\Services\Prediction;

use App\Services\Analytics\FarmerKpiService;
use App\Support\ProjectYieldDataset;
use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Expected yield this season" in one place, so Farmer Home and the Predictions page say the same thing: the current
 * season (auto-detected, App\Support\RiceSeason), one t/ha number, and how it compares with the usual Lian yield
 * (Normal / Mas mataas / Mas mababa).
 */
final class SeasonYield
{
    public function __construct(
        private readonly ProjectYieldDataset $yields,
        private readonly FarmerKpiService $kpi,
    ) {}

    /** "Wet season 2026" / "Tag-araw 2026–2027", in the current language. */
    public function seasonLabel(CarbonImmutable $date): string
    {
        $season = RiceSeason::window($date);

        return __('farmer.home.harvest.season.'.strtolower($season['season']), [
            'year' => $season['start']->year,
            'years' => $season['start']->year.'–'.$season['end']->year,
        ]);
    }

    /** Lian's usual yield (t/ha): the same calendar month in past years, or all months when that month has no records. */
    public function usual(int $month): ?float
    {
        try {
            $latest = $this->yields->years()->first();
            $rows = $latest ? $this->yields->rows()->where('year', '<=', (int) $latest) : collect();
        } catch (Throwable $e) {
            Log::warning('Season yield history failed.', ['source' => 'project yield dataset', 'message' => $e->getMessage()]);

            return null;
        }
        $sameMonth = $rows->where('month', $month);
        $mean = ($sameMonth->isNotEmpty() ? $sameMonth : $rows)->avg('yield');

        return $mean && $mean > 0 ? (float) $mean : null;
    }

    /**
     * @return array{yield: float, usual: ?float, compare: ?string, compare_label: ?string}  compare: typical | above | below
     */
    public function describe(float $yield, int $month): array
    {
        $usual = $this->usual($month);
        $compare = $usual ? $this->kpi->yieldAgainstTypical(round(($yield - $usual) / $usual * 100)) : null;

        return [
            'yield' => round($yield, 2),
            'usual' => $usual !== null ? round($usual, 2) : null,
            'compare' => $compare,
            'compare_label' => $compare ? __('farmer.home.harvest.compare.'.$compare) : null,
        ];
    }
}
