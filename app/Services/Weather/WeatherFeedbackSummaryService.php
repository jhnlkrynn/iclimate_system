<?php

namespace App\Services\Weather;

use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Quality control + summarising of farmer weather feedback.
 *
 * READ-ONLY and evaluation-only: nothing here writes to any table, feeds the
 * production model, or triggers retraining. A single farmer report is never
 * treated as truth; the forecast snapshot stays separate from observations.
 *
 * Quality-control rules (a row counts only if ALL hold):
 *  1. The reporting user is a Farmer.
 *  2. The forecast period has already ended (Asia/Manila).
 *  3. The row's barangay is a canonical Lian barangay AND still equals the
 *     farmer's current profile barangay (the farmer belongs to it).
 *  4. accuracy is one of accurate|partly|different; observed_condition (if any)
 *     is one of the 7 allowed values.
 *  5. Duplicates are excluded: only the earliest answer per
 *     (user, forecast_type, forecast_date) is kept.
 *
 * Effective observed condition: "accurate" => the forecast condition;
 * partly/different => observed_condition (may be null = not stated).
 */
class WeatherFeedbackSummaryService
{
    /** Minimum valid reports before headline percentages / sentences are shown. */
    public const MIN_REPORTS = 5;

    /** Minimum reports in one barangay+date group for it to be "reliable". Fewer => "few reports". */
    public const MIN_GROUP_REPORTS = 3;

    /**
     * A group (with >= MIN_GROUP_REPORTS reports) is "conflicting" when no
     * single accuracy answer holds MORE than this share of its reports
     * (e.g. 2 accurate / 1 partly / 1 different => top share 0.5 => conflicting).
     */
    public const CONFLICT_DOMINANCE_THRESHOLD = 0.5;

    /**
     * @param  array{barangay?: ?string, from?: ?string, to?: ?string, condition?: ?string}  $filters
     * @return Collection<int, array<string, mixed>>  validated observations
     */
    public function validatedObservations(array $filters = []): Collection
    {
        if (! Schema::hasTable('weather_forecast_feedback')) {
            return collect();
        }

        $rows = WeatherForecastFeedback::query()
            ->with(['user:id,role,barangay', 'user.farmerProfile:id,user_id,barangay'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $now = CarbonImmutable::now(WeatherFeedbackService::TIMEZONE);
        $seen = [];
        $valid = collect();

        foreach ($rows as $row) {
            $farmer = $row->user;
            if (! $farmer || $farmer->role !== User::ROLE_FARMER) {
                continue;
            }
            $date = CarbonImmutable::parse($row->forecast_date->format('Y-m-d'), WeatherFeedbackService::TIMEZONE);
            if ($row->forecast_type === WeatherForecastFeedback::TYPE_MONTHLY) {
                if (! $now->greaterThan($date->startOfMonth()->endOfMonth())) {
                    continue;
                }
            } elseif (! $now->greaterThan($date->endOfDay())) {
                continue;
            }
            $canonical = LianBarangays::canonicalName($row->barangay);
            $current = LianBarangays::canonicalName($farmer->farmerProfile?->barangay ?: $farmer->barangay);
            if ($canonical === null || $canonical !== $current) {
                continue;
            }
            if (! in_array($row->accuracy, WeatherForecastFeedback::ACCURACY_VALUES, true)) {
                continue;
            }
            if ($row->observed_condition !== null
                && ! in_array($row->observed_condition, WeatherForecastFeedback::OBSERVED_CONDITIONS, true)) {
                continue;
            }
            $key = $row->user_id.'|'.$row->forecast_type.'|'.$row->forecast_date->format('Y-m-d');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $valid->push([
                'barangay' => $canonical,
                'forecast_type' => $row->forecast_type,
                'forecast_date' => $row->forecast_date->format('Y-m-d'),
                // Original forecast snapshot (kept separate from the observation).
                'forecast' => [
                    'condition' => $row->forecast_condition,
                    'rainfall_mm' => $row->forecast_rainfall_mm,
                    'temperature_c' => $row->forecast_temperature_c,
                    'humidity_percent' => $row->forecast_humidity_percent,
                ],
                // Farmer observation.
                'accuracy' => $row->accuracy,
                'observed_condition' => $row->accuracy === WeatherForecastFeedback::ACCURATE
                    ? $row->forecast_condition
                    : $row->observed_condition,
                'submitted_at' => $row->submitted_at?->toIso8601String(),
            ]);
        }

        return $valid
            ->when($filters['barangay'] ?? null, fn ($c, $v) => $c->where('barangay', LianBarangays::canonicalName($v) ?? $v))
            ->when($filters['from'] ?? null, fn ($c, $v) => $c->filter(fn ($r) => $r['forecast_date'] >= $v))
            ->when($filters['to'] ?? null, fn ($c, $v) => $c->filter(fn ($r) => $r['forecast_date'] <= $v))
            ->when($filters['condition'] ?? null, fn ($c, $v) => $c->filter(
                fn ($r) => $r['forecast']['condition'] === $v || $r['observed_condition'] === $v
            ))
            ->values();
    }

    /**
     * @param  array{barangay?: ?string, from?: ?string, to?: ?string, condition?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function summarize(array $filters = []): array
    {
        $observations = $this->validatedObservations($filters);
        $total = $observations->count();
        $counts = $this->countAccuracy($observations);
        $enough = $total >= self::MIN_REPORTS;

        $groups = $observations
            ->groupBy(fn ($r) => $r['barangay'].'|'.$r['forecast_date'].'|'.$r['forecast_type'])
            ->map(function (Collection $items) {
                $groupCounts = $this->countAccuracy($items);
                $groupTotal = $items->count();
                $top = max($groupCounts);
                $dominant = $groupTotal > 0 ? array_search($top, $groupCounts, true) : null;
                $lowConfidence = $groupTotal < self::MIN_GROUP_REPORTS;

                return [
                    'barangay' => $items->first()['barangay'],
                    'forecast_date' => $items->first()['forecast_date'],
                    'forecast_condition' => $items->first()['forecast']['condition'],
                    'total' => $groupTotal,
                    'counts' => $groupCounts,
                    'dominant' => $lowConfidence ? null : $dominant,
                    'low_confidence' => $lowConfidence,
                    'conflicting' => ! $lowConfidence && ($top / $groupTotal) <= self::CONFLICT_DOMINANCE_THRESHOLD,
                    'observed_conditions' => $items->pluck('observed_condition')->filter()->countBy()->sortDesc()->all(),
                ];
            })
            ->sortByDesc('forecast_date')
            ->values();

        return [
            'total' => $total,
            'counts' => $counts,
            'percentages' => $enough ? $this->percentages($counts, $total) : null,
            'enough_data' => $enough,
            'min_reports' => self::MIN_REPORTS,
            'message' => $this->message($enough, $counts, $total),
            'groups' => $groups,
            'barangays' => $observations->pluck('barangay')->unique()->sort()->values()->all(),
        ];
    }

    /** @return array<string, int> */
    private function countAccuracy(Collection $items): array
    {
        $counts = array_fill_keys(WeatherForecastFeedback::ACCURACY_VALUES, 0);
        foreach ($items as $item) {
            $counts[$item['accuracy']]++;
        }

        return $counts;
    }

    /** @return array<string, int> */
    private function percentages(array $counts, int $total): array
    {
        return array_map(fn (int $count) => (int) round(($count / max(1, $total)) * 100), $counts);
    }

    private function message(bool $enough, array $counts, int $total): string
    {
        if (! $enough) {
            return 'Not enough farmer reports yet';
        }

        $accurate = $counts[WeatherForecastFeedback::ACCURATE] / $total;
        $different = $counts[WeatherForecastFeedback::DIFFERENT] / $total;

        return match (true) {
            $accurate > 0.5 => 'Most farmers reported that the forecast matched actual conditions.',
            $different > 0.5 => 'Most farmers reported that actual conditions were different from the forecast.',
            default => 'Farmer reports are mixed.',
        };
    }
}
