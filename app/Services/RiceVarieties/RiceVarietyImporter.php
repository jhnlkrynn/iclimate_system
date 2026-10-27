<?php

namespace App\Services\RiceVarieties;

use App\Models\RiceVariety;
use App\Support\RiceVarieties\NsicCell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Loads the NSIC list ("Rice" sheet) into rice_varieties. Rows 1-3 are titles, 4-6 the headers (main, sub-header,
 * DS / WS), data starts on row 7 and ends at the first row without a numeric year (the abbreviations under it).
 *
 * Column positions were checked against the data rows, not only the merged headers: stem borer results by site
 * ("PhilRice Midsayap (WSB) - R …", "WSB - I; YSB - MR") sit under Whiteheads / Deadhearts (columns 30-31) as well as
 * under Stem Borer (27). A cell merged across columns (one yield for DS and WS) counts for each column it covers.
 *
 * Re-running is safe: rows are matched by import_key and only changed values are written; a variety IT Personnel
 * added or corrected by hand (source = manual) is never overwritten.
 */
final class RiceVarietyImporter
{
    public const FIRST_DATA_ROW = 7;

    private const COMBO_COLUMNS = ['tpr_ds' => 0, 'tpr_ws' => 1, 'dwsr_ds' => 2, 'dwsr_ws' => 3];

    private const YIELD_FROM = 7;

    private const MATURITY_FROM = 11;

    private const SEED_PRODUCTION = 15;

    private const HEIGHT_FROM = 16;

    private const TILLERS_FROM = 20;

    private const PESTS_FROM = 25;     // RiceVariety::PESTS, in order

    private const DISEASES_FROM = 32;  // RiceVariety::DISEASES, in order

    /**
     * no_maturity: no maturity figure at all (these use the 115-day default); no_tpr_maturity: no transplanted figure
     * for either season (the estimator then falls back to direct-seeded or seed-production days).
     *
     * Farm types (each variety counted once): irrigated only, rainfed only, both, not specified.
     *
     * @return array{inserted: int, updated: int, unchanged: int, skipped: int, no_maturity: int, no_tpr_maturity: int, rows: int,
     *     irrigated: int, rainfed: int, both: int, not_specified: int}
     */
    public function import(string $path): array
    {
        $rows = $this->rows($path);
        $keys = $this->keys($rows);
        $summary = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'no_maturity' => 0, 'no_tpr_maturity' => 0, 'rows' => count($rows),
            'irrigated' => 0, 'rainfed' => 0, 'both' => 0, 'not_specified' => 0];

        foreach ($rows as $index => $row) {
            $attributes = $this->attributes($row) + ['import_key' => $keys[$index], 'source' => RiceVariety::SOURCE_IMPORT];
            $variety = RiceVariety::query()->firstWhere('import_key', $keys[$index]);

            if ($variety?->source === RiceVariety::SOURCE_MANUAL) {
                $summary['skipped']++;
            } elseif ($variety === null) {
                RiceVariety::query()->create($attributes + ['is_active' => true]);
                $summary['inserted']++;
            } else {
                $variety->fill($attributes);
                $variety->isDirty() ? $variety->save() : null;
                $summary[$variety->wasChanged() ? 'updated' : 'unchanged']++;
            }

            if (array_filter(array_intersect_key($attributes, array_flip(['maturity_tpr_ds', 'maturity_tpr_ws', 'maturity_dwsr_ds', 'maturity_dwsr_ws', 'maturity_seed_production'])), fn ($v) => $v !== null) === []) {
                $summary['no_maturity']++;
            }
            if ($attributes['maturity_tpr_ds'] === null && $attributes['maturity_tpr_ws'] === null) {
                $summary['no_tpr_maturity']++;
            }
            $summary[match (true) {
                $attributes['farm_irrigated'] && $attributes['farm_rainfed'] => 'both',
                $attributes['farm_irrigated'] => 'irrigated',
                $attributes['farm_rainfed'] => 'rainfed',
                default => 'not_specified',
            }]++;
        }

        // "Last imported" on the Rice Varieties page.
        if (\App\Support\SchemaCache::hasTable('rice_variety_imports')) {
            \Illuminate\Support\Facades\DB::table('rice_variety_imports')->insert([
                'file' => basename($path), 'rows' => $summary['rows'], 'inserted' => $summary['inserted'], 'updated' => $summary['updated'],
                'unchanged' => $summary['unchanged'], 'skipped' => $summary['skipped'], 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $summary;
    }

    /**
     * The data rows (0-based columns), merged cells filled in.
     *
     * @return array<int, array<int, mixed>>
     */
    public function rows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Spreadsheet not found: {$path}");
        }
        $reader = IOFactory::createReaderForFile($path);
        $book = $reader->load($path);
        $sheet = $book->getSheetByName('Rice') ?? $book->getSheet(0);

        $cells = $sheet->toArray(null, true, false, false);
        $this->fillMerged($sheet, $cells);

        $rows = [];
        foreach (array_slice($cells, self::FIRST_DATA_ROW - 1) as $row) {
            if (! is_numeric($row[1] ?? null)) {
                break;                                          // the abbreviations under the list
            }
            if (NsicCell::text($row[3] ?? null) !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param array<int, array<int, mixed>> $cells */
    private function fillMerged(Worksheet $sheet, array &$cells): void
    {
        foreach (array_keys($sheet->getMergeCells()) as $range) {
            [[$fromCol, $fromRow], [$toCol, $toRow]] = array_map(
                fn (string $cell): array => [Coordinate::columnIndexFromString(preg_replace('/\d+/', '', $cell)) - 1, (int) preg_replace('/\D+/', '', $cell)],
                explode(':', $range),
            );
            if ($fromRow < self::FIRST_DATA_ROW) {
                continue;
            }
            $value = $cells[$fromRow - 1][$fromCol] ?? null;
            for ($r = $fromRow; $r <= $toRow; $r++) {
                for ($c = $fromCol; $c <= $toCol; $c++) {
                    $cells[$r - 1][$c] = $value;
                }
            }
        }
    }

    /**
     * The NSIC number when it is unique in the list, else "name|year" (the old "Philippine Seed Board" rows).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, string>
     */
    private function keys(array $rows): array
    {
        $numbers = array_map(fn (array $row): ?string => self::nsicKey(NsicCell::text($row[2] ?? null)), $rows);
        $counts = array_count_values(array_filter($numbers));

        return array_map(fn (array $row, ?string $number): string => $number !== null && $counts[$number] === 1
            ? $number
            : self::nameKey(NsicCell::text($row[3]) ?? '', (int) $row[1]), $rows, $numbers);
    }

    /** "NSIC 2016 Rc 480" → "nsic 2016 rc 480"; anything not an NSIC number → null. */
    public static function nsicKey(?string $number): ?string
    {
        $number = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $number)));

        return str_starts_with($number, 'nsic') ? $number : null;
    }

    public static function nameKey(string $name, ?int $year): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', $name))).'|'.($year ?: '');
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    public function attributes(array $row): array
    {
        $attributes = [
            'source_no' => is_numeric($row[0] ?? null) ? (int) $row[0] : null,
            'year_approved' => (int) $row[1],
            'nsic_reg_no' => NsicCell::text($row[2] ?? null),
            'variety_name' => NsicCell::text($row[3] ?? null),
            'line_name' => NsicCell::text($row[4] ?? null),
            'variety_type' => NsicCell::varietyType($row[5] ?? null),
            'owner_breeder' => NsicCell::text($row[6] ?? null),
            'culm_strength' => NsicCell::text($row[24] ?? null),
            'amylose_content' => NsicCell::amylose($row[39] ?? null),
            'adaptation' => NsicCell::text($row[40] ?? null),
            'remarks' => NsicCell::text($row[41] ?? null),
        ];

        $yields = [];
        foreach (self::COMBO_COLUMNS as $combo => $offset) {
            $yield = NsicCell::yield($row[self::YIELD_FROM + $offset] ?? null);
            $attributes['yield_'.$combo] = $yield['kg'];
            if ($yield['raw'] !== null) {
                $yields[$combo] = ['raw' => $yield['raw'], 'values' => $yield['values']];
            }
            $this->range($attributes, 'maturity_'.$combo, $row[self::MATURITY_FROM + $offset] ?? null, true);
            $this->range($attributes, 'height_'.$combo, $row[self::HEIGHT_FROM + $offset] ?? null);
            $this->range($attributes, 'tillers_'.$combo, $row[self::TILLERS_FROM + $offset] ?? null);
        }
        $this->range($attributes, 'maturity_seed_production', $row[self::SEED_PRODUCTION] ?? null, true);
        $attributes['yield_values'] = $yields ?: null;

        $attributes['reactions'] = [
            'pests' => $this->reactions($row, self::PESTS_FROM, RiceVariety::PESTS),
            'diseases' => $this->reactions($row, self::DISEASES_FROM, RiceVariety::DISEASES),
        ];
        // Irrigated / Rainfed from the adaptation text; saline, cool-elevated and the like stay in that text.
        $farm = NsicCell::farmTypes($attributes['adaptation']);
        $attributes['farm_irrigated'] = $farm['irrigated'];
        $attributes['farm_rainfed'] = $farm['rainfed'];

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function range(array &$attributes, string $column, mixed $cell, bool $whole = false): void
    {
        $round = fn (?float $v): int|float|null => $v === null ? null : ($whole ? (int) round($v) : round($v, 1));
        [$avg, $min, $max] = NsicCell::measure($cell);
        $attributes[$column] = $round($avg);
        $attributes[$column.'_min'] = $round($min);
        $attributes[$column.'_max'] = $round($max);
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, string>  $keys
     * @return array<string, array{raw: string, entries: array<int, array<string, mixed>>}>
     */
    private function reactions(array $row, int $from, array $keys): array
    {
        $out = [];
        foreach ($keys as $i => $key) {
            $raw = NsicCell::text($row[$from + $i] ?? null);
            if ($raw !== null) {
                $out[$key] = ['raw' => $raw, 'entries' => NsicCell::reactions($raw)];
            }
        }

        return $out;
    }
}
