<?php

namespace App\Support;

use App\Models\FarmerPlantingSchedule;

/**
 * Decides whether a stored model evaluation may be shown as a validated result.
 *
 * It may only when every check passes: the run computed metrics, enough verified and separate rice records
 * exist, the run used no more rows than those records, the test kept each season together, and Lian climate
 * observations are on record. Otherwise the page says "Insufficient verified data" and the run's figures are
 * kept only as an unverified historical record (docs/data-audit/phase-3-ml-dataset-methodology.md).
 */
final class EvaluationEvidence
{
    public const HEADLINE = 'Insufficient verified data';

    /** Test methods that keep all rows of one season on the same side of the split. */
    public const GROUPED_METHODS = ['leave_one_season_out', 'forward_chaining'];

    private function __construct() {}

    /**
     * @param  array<string, mixed>  $evaluation  the "evaluation" part of a dataset_processing run result
     * @return array{verified: bool, headline: ?string, reasons: list<string>, minimum: int, rice: array<string, int>,
     *     climate_observations: int, harvests: array{reported: int, demo: int}, historical: bool}
     */
    public static function assess(array $evaluation): array
    {
        $minimum = max(1, (int) config('iclimate.model_evaluation_min_observations', 30));
        $rice = RecordProvenance::riceSummary();
        $climate = (int) rescue(fn (): int => ClimateHistory::entered()->count(), 0, false);
        $computed = (bool) ($evaluation['ready'] ?? false) && ! empty($evaluation['rows']);
        $verified = $rice[RecordProvenance::VERIFIED];
        $reasons = [];

        if (! $computed) {
            $reasons[] = 'No evaluation could be computed from the saved records.';
        }
        if ($verified < $minimum) {
            $reasons[] = $verified === 0
                ? "No rice record has been validated by MAO. At least {$minimum} validated, separately measured records are needed."
                : "Only {$verified} validated, separately measured rice ".($verified === 1 ? 'record exists' : 'records exist').". At least {$minimum} are needed.";
        }
        if ($climate === 0) {
            $reasons[] = 'No Lian climate observations are on record.';
        }
        if ($computed) {
            $used = (int) ($evaluation['sample_count'] ?? 0);
            if ($used > $verified) {
                $reasons[] = "This run used {$used} rows, more than the {$verified} verified records, so it included unverified, derived or demo rows.";
            }
            if (! in_array(data_get($evaluation, 'validation.method'), self::GROUPED_METHODS, true)) {
                $reasons[] = 'This run split the rows at random, so records of the same season could be in both training and testing.';
            }
        }

        // Every reason so far blocks. The count of derived and demo records is added as information only.
        $blocking = $reasons !== [];
        if ($blocking && $rice[RecordProvenance::DERIVED] + $rice[RecordProvenance::DEMO] > 0) {
            $reasons[] = $rice[RecordProvenance::DERIVED].' derived and '.$rice[RecordProvenance::DEMO].' demo rice records are on file. They are not counted as observations.';
        }

        return [
            'verified' => ! $blocking,
            'headline' => $blocking ? self::HEADLINE : null,
            'reasons' => $reasons,
            'minimum' => $minimum,
            'rice' => $rice,
            'climate_observations' => $climate,
            'harvests' => self::harvests(),
            'historical' => $blocking && $computed,
        ];
    }

    /**
     * Harvests with both a predicted and an actual yield: those farmers reported, and those the demo seeders wrote.
     *
     * @return array{reported: int, demo: int}
     */
    private static function harvests(): array
    {
        return rescue(function (): array {
            $with = fn () => FarmerPlantingSchedule::query()->where('actual_yield', '>', 0)->where('predicted_yield', '>', 0);
            $reported = RecordProvenance::withoutDemoHarvests($with())->count();

            return ['reported' => $reported, 'demo' => $with()->count() - $reported];
        }, ['reported' => 0, 'demo' => 0], false);
    }
}
