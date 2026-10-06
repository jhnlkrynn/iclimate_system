<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Read-only view of the project's own historical yield series: the
 * AVERAGE_YIELD column (municipal monthly average, t/ha) of the modeling
 * dataset the rice-yield model was built from.
 *
 * It is the only yield history in the project that is not seeded sample data.
 * It is never written to here (the file is a training input), values are used
 * exactly as stored, and it is always shown under its own source label — never
 * merged with the rice_productions table.
 *
 * What it does NOT contain: production tonnage, planted area or barangay, so
 * those figures stay "Insufficient verified data" until real records exist.
 */
class ProjectYieldDataset
{
    public const FILE = 'storage/datasets/iClimate_Modeling_Ready_TimeAware.csv';

    public const SOURCE_NAME = 'iClimate modeling dataset — AVERAGE_YIELD (municipal monthly average)';

    public function path(): string
    {
        return base_path(self::FILE);
    }

    public function available(): bool
    {
        return is_file($this->path());
    }

    /**
     * @return Collection<int, array{year: int, month: int, yield: float, season: ?string, split: ?string}>
     */
    public function rows(): Collection
    {
        if (! $this->available()) {
            return collect();
        }

        $path = $this->path();

        return Cache::rememberForever(
            // v2: rows carry the file's SEASON column (an older cached copy without it is never reused).
            'project-yield-dataset:v2:'.filemtime($path).':'.filesize($path),
            fn (): Collection => $this->parse($path),
        );
    }

    /** @return Collection<int, int> years present, newest first */
    public function years(): Collection
    {
        return $this->rows()->pluck('year')->unique()->sortDesc()->values();
    }

    /**
     * Mean of the monthly averages recorded for a year, or null when the year
     * has no rows. The mean of the months on file — not a full-year figure when
     * only part of the year is present (see monthsRecorded()).
     */
    public function yearlyMean(int $year): ?float
    {
        $rows = $this->rows()->where('year', $year);

        return $rows->isEmpty() ? null : (float) $rows->avg('yield');
    }

    public function monthsRecorded(int $year): int
    {
        return $this->rows()->where('year', $year)->count();
    }

    /** @return array<int, ?float> twelve entries, null where the month is not on file */
    public function monthly(int $year): array
    {
        $byMonth = $this->rows()->where('year', $year)->keyBy('month');

        return array_map(
            fn (int $m): ?float => $byMonth->has($m) ? round((float) $byMonth[$m]['yield'], 2) : null,
            range(1, 12),
        );
    }

    /**
     * Mean of all monthly averages up to and including a year.
     */
    public function meanUpTo(int $year): ?float
    {
        $rows = $this->rows()->where('year', '<=', $year);

        return $rows->isEmpty() ? null : (float) $rows->avg('yield');
    }

    /**
     * Change in mean yield against the closest earlier year on file, using only
     * the months recorded in BOTH years — otherwise a year with fewer months on
     * file would look like a yield swing that never happened.
     *
     * @return array{previous_year: int, months: int, latest: float, previous: float, change: float}|null
     */
    public function compareWithPrevious(int $year): ?array
    {
        $previousYear = $this->rows()->pluck('year')->filter(fn ($y) => $y < $year)->max();
        if (! $previousYear) {
            return null;
        }

        $now = $this->rows()->where('year', $year)->keyBy('month');
        $before = $this->rows()->where('year', $previousYear)->keyBy('month');
        $common = $now->keys()->intersect($before->keys());
        if ($common->isEmpty()) {
            return null;
        }

        $latest = (float) $common->avg(fn ($m) => $now[$m]['yield']);
        $previous = (float) $common->avg(fn ($m) => $before[$m]['yield']);
        if ($previous <= 0) {
            return null;
        }

        return [
            'previous_year' => (int) $previousYear,
            'months' => $common->count(),
            'latest' => $latest,
            'previous' => $previous,
            'change' => round((($latest - $previous) / $previous) * 100, 1),
        ];
    }

    /** @return array{name: string, file: string, records: int, first: ?string, last: ?string, updated: ?Carbon} */
    public function describe(): array
    {
        $rows = $this->rows();
        $first = $rows->sortBy(fn ($r) => $r['year'] * 100 + $r['month'])->first();
        $last = $rows->sortByDesc(fn ($r) => $r['year'] * 100 + $r['month'])->first();

        return [
            'name' => self::SOURCE_NAME,
            'file' => self::FILE,
            'records' => $rows->count(),
            'first' => $first ? Carbon::create($first['year'], $first['month'], 1)->format('M Y') : null,
            'last' => $last ? Carbon::create($last['year'], $last['month'], 1)->format('M Y') : null,
            'updated' => $this->available() ? Carbon::createFromTimestamp(filemtime($this->path())) : null,
        ];
    }

    /** @return Collection<int, array{year: int, month: int, yield: float, season: ?string, split: ?string}> */
    private function parse(string $path): Collection
    {
        $rows = collect();

        try {
            $handle = fopen($path, 'rb');
            $header = array_map(fn ($h) => strtoupper(trim((string) $h, "\xEF\xBB\xBF \t\r\n")), (array) fgetcsv($handle, escape: '\\'));
            $index = array_flip($header);

            foreach (['YEAR', 'MONTH', 'AVERAGE_YIELD'] as $required) {
                if (! isset($index[$required])) {
                    fclose($handle);

                    return collect();
                }
            }

            while (($line = fgetcsv($handle, escape: '\\')) !== false) {
                $yield = $line[$index['AVERAGE_YIELD']] ?? '';
                $year = (int) ($line[$index['YEAR']] ?? 0);
                $month = (int) ($line[$index['MONTH']] ?? 0);

                // Blank / non-numeric cells are missing data — skipped, never zero-filled.
                if (! is_numeric($yield) || $year < 1900 || $month < 1 || $month > 12) {
                    continue;
                }

                $rows->push([
                    'year' => $year,
                    'month' => $month,
                    'yield' => (float) $yield,
                    // Wet / Dry exactly as the file records it; a blank cell stays unknown.
                    'season' => isset($index['SEASON']) ? trim((string) ($line[$index['SEASON']] ?? '')) ?: null : null,
                    'split' => isset($index['SPLIT']) ? trim((string) ($line[$index['SPLIT']] ?? '')) ?: null : null,
                ]);
            }

            fclose($handle);
        } catch (Throwable) {
            return collect();
        }

        return $rows->values();
    }
}
