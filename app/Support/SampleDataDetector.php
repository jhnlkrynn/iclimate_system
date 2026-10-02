<?php

namespace App\Support;

use App\Models\ClimateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Finds rows that came from the project's DatabaseSeeder / model factories
 * rather than from a real source, so they are never presented as verified.
 *
 * Read-only. Nothing here deletes or edits data.
 *
 * How a row is recognised (deliberately conservative and explainable):
 *   - The seeder fills text columns with Faker "Lorem ipsum" Latin
 *     (rice_productions.remarks, notifications.message/title, advisory text).
 *   - Everything the seeder inserts in one run shares the same created_at
 *     second. So a "seed batch" is any second in which at least one row with
 *     Lorem text was created, and every row of the seeded tables created in
 *     that second belongs to the batch.
 *   - Climate rows that are impossible on their face (dated in the future, or
 *     outside ClimateRecord's own plausibility limits) are reported separately.
 */
class SampleDataDetector
{
    /** table => text columns that carry placeholder text when seeded */
    private const TEXT_COLUMNS = [
        'rice_productions' => ['remarks'],
        'notifications' => ['message', 'title'],
        'planting_advisories' => ['content', 'title'],
    ];

    private const SCAN_LIMIT = 2000;

    /** Tables whose rows are checked against the seed batches. */
    private const SEEDED_TABLES = ['rice_productions', 'climate_records', 'notifications', 'planting_advisories'];

    private const LOREM = [
        'quia', 'voluptas', 'aut', 'nihil', 'ipsum', 'dolorem', 'eveniet', 'distinctio', 'expedita', 'consequatur',
        'sunt', 'est', 'et', 'ut', 'non', 'quas', 'vero', 'odit', 'natus', 'optio', 'harum', 'ratione', 'nemo',
        'laboris', 'magnam', 'nesciunt', 'qui', 'sint', 'cum', 'fuga', 'omnis', 'quod', 'hic', 'porro', 'eos',
    ];

    /** @var array<int, string>|null */
    private ?array $batches = null;

    /** True when the text is Faker placeholder Latin rather than real prose. */
    public static function looksLikeLorem(?string $text): bool
    {
        $text = trim((string) $text);
        if (strlen($text) < 12) {
            return false;
        }

        $words = preg_split('/[^a-z]+/i', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 3) {
            return false;
        }

        $hits = count(array_filter($words, fn (string $w): bool => in_array($w, self::LOREM, true)));

        return $hits / count($words) >= 0.5;
    }

    /** @return array<int, string> created_at seconds (Y-m-d H:i:s) that contain placeholder-text rows */
    public function batches(): array
    {
        if ($this->batches !== null) {
            return $this->batches;
        }

        return $this->batches = Cache::remember(
            'sample-data:batches:v'.DashboardData::version(),
            now()->addMinutes(10),
            fn (): array => $this->scanBatches(),
        );
    }

    /** @return array<int, string> */
    private function scanBatches(): array
    {
        $seconds = [];

        foreach (self::TEXT_COLUMNS as $table => $columns) {
            try {
                // Seeded rows are the oldest in each table, so the first rows are enough.
                foreach (DB::table($table)->orderBy('id')->limit(self::SCAN_LIMIT)->get(array_merge(['created_at'], $columns)) as $row) {
                    foreach ($columns as $column) {
                        if (self::looksLikeLorem($row->{$column} ?? null) && $row->created_at) {
                            $seconds[Carbon::parse($row->created_at)->format('Y-m-d H:i:s')] = true;
                        }
                    }
                }
            } catch (Throwable) {
                // table missing on this database — nothing to detect there
            }
        }

        return array_keys($seconds);
    }

    /**
     * Ids of rows in a seeded table that were created in a seed batch.
     *
     * @return array<int, int>
     */
    public function sampleIds(string $table): array
    {
        if (! in_array($table, self::SEEDED_TABLES, true) || $this->batches() === []) {
            return [];
        }

        $batches = array_flip($this->batches());

        try {
            return DB::table($table)->get(['id', 'created_at'])
                ->filter(fn ($row) => $row->created_at && isset($batches[Carbon::parse($row->created_at)->format('Y-m-d H:i:s')]))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Completed prediction runs whose weather step was trained on fewer than a
     * year of monthly records — the older model that learned from the six seeded
     * climate rows and predicted impossible values (thousands of mm of rain in a
     * month). They are real records of what the system produced, but their
     * numbers are not valid, so they are not counted as usable predictions.
     *
     * @return array<int, int>
     */
    public function underTrainedRunIds(): array
    {
        try {
            return DB::table('analytics_runs')
                ->whereIn('type', ['rice_yield_prediction', 'weather_prediction'])
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'result'])
                ->filter(function ($run): bool {
                    $months = data_get(json_decode((string) $run->result, true), 'weather_result.months_available');

                    return is_numeric($months) && $months < 12;
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * climate_records rows that are impossible or outside plausibility limits.
     *
     * @return array<int, int>
     */
    public function implausibleClimateIds(): array
    {
        try {
            $plausible = ClimateRecord::query()->plausible()->pluck('id')->all();

            return ClimateRecord::query()->whereNotIn('id', $plausible)->pluck('id')->map(fn ($id) => (int) $id)->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Ids to keep out of dashboards and statistics for a table (seed rows, plus
     * impossible climate rows). Empty when the exclusion switch is off.
     *
     * @return array<int, int>
     */
    public function excludedIds(string $table): array
    {
        if (! config('iclimate.exclude_sample_data', true)) {
            return [];
        }

        return Cache::remember('sample-data:excluded:'.$table.':v'.DashboardData::version(), now()->addMinutes(10), function () use ($table): array {
            $ids = $this->sampleIds($table);

            return match ($table) {
                'climate_records' => array_values(array_unique(array_merge($ids, $this->implausibleClimateIds()))),
                'analytics_runs' => $this->underTrainedRunIds(),
                default => $ids,
            };
        });
    }

    /**
     * @return array{total: int, sample: int, implausible: int, verified: int}
     */
    public function summary(string $table): array
    {
        try {
            $total = DB::table($table)->count();
        } catch (Throwable) {
            return ['total' => 0, 'sample' => 0, 'implausible' => 0, 'verified' => 0];
        }

        $sample = $this->sampleIds($table);
        $implausible = $table === 'climate_records'
            ? array_values(array_diff($this->implausibleClimateIds(), $sample))
            : [];

        return [
            'total' => $total,
            'sample' => count($sample),
            'implausible' => count($implausible),
            'verified' => max(0, $total - count($sample) - count($implausible)),
        ];
    }
}
