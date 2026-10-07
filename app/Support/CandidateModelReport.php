<?php

namespace App\Support;

use Throwable;

/**
 * What the Model Evaluation page shows about the candidate rice-yield model (2026-10; this replaced the separate
 * candidate test page): its recorded method and hold-out metrics, its month-by-month 2025 validation, and the
 * season-calendar retraining report (python_scripts/retrain_yield_season.py). Read from files the scripts wrote;
 * nothing is run from a web request, and a missing file is simply a missing section.
 */
final class CandidateModelReport
{
    public const METADATA = 'storage/models/rice_yield_model_candidate_metadata.json';

    public const VALIDATION_2025 = 'storage/datasets/candidate_2025_full_predictions.csv';

    public const RETRAIN_REPORT = 'storage/models/rice_yield_season_retrain_report.json';

    /** @return array{metadata: ?array<string, mixed>, validation: array<int, array<string, mixed>>, retrain: ?array<string, mixed>} */
    public static function load(): array
    {
        return [
            'metadata' => self::json(self::METADATA),
            'validation' => self::validation(),
            'retrain' => self::json(self::RETRAIN_REPORT),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function json(string $relative): ?array
    {
        try {
            $path = base_path($relative);
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array{month: string, actual: ?float, predicted: ?float, error: ?float, status: string}> */
    private static function validation(): array
    {
        try {
            $path = base_path(self::VALIDATION_2025);
            if (! is_file($path)) {
                return [];
            }
            $lines = array_map('str_getcsv', array_filter((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));
            $header = array_shift($lines);
            $number = fn (array $row, string $key): ?float => is_numeric($row[$key] ?? null) ? round((float) $row[$key], 2) : null;

            return array_values(array_map(function (array $line) use ($header, $number): array {
                $row = array_combine($header, array_pad($line, count($header), null));

                return [
                    'month' => (string) ($row['MONTH'] ?? ''),
                    'actual' => $number($row, 'ACTUAL_YIELD'),
                    'predicted' => $number($row, 'PREDICTED_YIELD'),
                    'error' => $number($row, 'ABS_ERROR'),
                    'status' => (string) ($row['STATUS'] ?? ''),
                ];
            }, $lines));
        } catch (Throwable) {
            return [];
        }
    }
}
