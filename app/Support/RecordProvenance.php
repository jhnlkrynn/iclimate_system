<?php

namespace App\Support;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Where a record comes from, decided from the record itself and never from the fact that it exists.
 *
 * Four classes (docs/data-audit/phase-3-ml-dataset-methodology.md):
 *   - verified:   validated by MAO (verified_at is set) and neither a copy nor demo data;
 *   - unverified: entered, not validated yet;
 *   - derived:    computed from other figures instead of measured, such as one season total split across
 *                 barangays, which shows as the same yield repeated in several barangays of one season;
 *   - demo:       written by the demo seeders.
 *
 * Only verified records count as evidence of model accuracy. Nothing here changes a record.
 */
final class RecordProvenance
{
    public const VERIFIED = 'verified';

    public const UNVERIFIED = 'unverified';

    public const DERIVED = 'derived';

    public const DEMO = 'demo';

    /** Marker the demo seeders write (prediction_source, and "[demo_seed]" at the start of notes / remarks). */
    public const DEMO_MARKER = 'demo_seed';

    /** Email domain of the demo farmer accounts (FarmerDemoSeeder). */
    public const DEMO_EMAIL_DOMAIN = 'iclimate.test';

    /** Remark the demo seeder writes on the rows it allocates from the municipal MAO planting summary. */
    public const DERIVED_REMARK = 'Official MAO planting summary';

    /** Yields this close (t/ha) are the same figure after rounding. */
    private const SAME_YIELD = 0.0101;

    /** The same yield in this many records of one season is a copied figure, not separate measurements. */
    private const COPIES = 3;

    public const LABELS = [
        self::VERIFIED => 'Verified observations',
        self::UNVERIFIED => 'Unverified MAO records',
        self::DERIVED => 'Derived records',
        self::DEMO => 'Demo records',
    ];

    public const NOTES = [
        self::VERIFIED => 'Validated by MAO and measured separately.',
        self::UNVERIFIED => 'Entered, but not yet validated by MAO.',
        self::DERIVED => 'Copies of a season total split across barangays. Not separate measurements.',
        self::DEMO => 'Created by the demo seeder. Not real records.',
    ];

    private function __construct() {}

    /**
     * Farmer harvests without the demo seeders' records: by the seeder's marker on the record and by the demo
     * farmer account that owns it.
     *
     * @param  Builder<FarmerPlantingSchedule>  $query
     * @return Builder<FarmerPlantingSchedule>
     */
    public static function withoutDemoHarvests(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('prediction_source')->orWhere('prediction_source', '!=', self::DEMO_MARKER))
            ->where(fn (Builder $q) => $q->whereNull('notes')->orWhere('notes', 'not like', '['.self::DEMO_MARKER.']%'))
            ->whereDoesntHave('farmer', fn (Builder $q) => $q->where('email', 'like', '%@'.self::DEMO_EMAIL_DOMAIN));
    }

    /** True when a harvest record was written by the demo seeders. */
    public static function isDemoHarvest(FarmerPlantingSchedule $plan): bool
    {
        return $plan->prediction_source === self::DEMO_MARKER
            || str_starts_with((string) $plan->notes, '['.self::DEMO_MARKER.']')
            || str_ends_with(strtolower((string) $plan->farmer?->email), '@'.self::DEMO_EMAIL_DOMAIN);
    }

    /**
     * The class of every rice-production row given, keyed by id.
     *
     * @param  iterable<int, object|array<string, mixed>>  $rows  id, year, season, yield_per_hectare, remarks, verified_at
     * @param  array<int, int>  $sampleIds  ids SampleDataDetector already treats as seeded sample rows
     * @return array<int, string>
     */
    public static function classifyRice(iterable $rows, array $sampleIds = []): array
    {
        $rows = collect($rows)->map(fn ($row): object => (object) (is_array($row) ? $row : (method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row)));
        $sample = array_flip($sampleIds);
        $classes = [];

        foreach ($rows as $row) {
            if (isset($sample[$row->id]) || str_starts_with((string) ($row->remarks ?? ''), '['.self::DEMO_MARKER.']')) {
                $classes[$row->id] = self::DEMO;
            }
        }

        $real = $rows->reject(fn (object $row): bool => isset($classes[$row->id]));
        foreach ($real->groupBy(fn (object $row): string => ((int) $row->year).'|'.$row->season) as $season) {
            foreach ($season as $row) {
                $classes[$row->id] = match (true) {
                    str_starts_with((string) ($row->remarks ?? ''), self::DERIVED_REMARK), self::isCopiedYield($row, $season) => self::DERIVED,
                    ! empty($row->verified_at) => self::VERIFIED,
                    default => self::UNVERIFIED,
                };
            }
        }

        return $classes;
    }

    /**
     * Counts of the saved rice-production records by class, with the number of separate seasons behind them.
     *
     * @return array{total: int, verified: int, unverified: int, derived: int, demo: int, seasons: int, verified_seasons: int}
     */
    public static function riceSummary(): array
    {
        $rows = self::savedRice();
        $classes = self::classifyRice($rows, self::sampleRiceIds());
        $counts = array_count_values($classes) + [self::VERIFIED => 0, self::UNVERIFIED => 0, self::DERIVED => 0, self::DEMO => 0];
        $seasons = fn (Collection $set): int => $set->map(fn (object $row): string => ((int) $row->year).'|'.$row->season)->unique()->count();

        return [
            'total' => $rows->count(),
            self::VERIFIED => $counts[self::VERIFIED],
            self::UNVERIFIED => $counts[self::UNVERIFIED],
            self::DERIVED => $counts[self::DERIVED],
            self::DEMO => $counts[self::DEMO],
            'seasons' => $seasons($rows->filter(fn (object $row): bool => $classes[$row->id] !== self::DEMO)),
            'verified_seasons' => $seasons($rows->filter(fn (object $row): bool => $classes[$row->id] === self::VERIFIED)),
        ];
    }

    /**
     * The class of every saved rice-production record, keyed by id.
     *
     * @return array<int, string>
     */
    public static function riceClasses(): array
    {
        return self::classifyRice(self::savedRice(), self::sampleRiceIds());
    }

    /**
     * The class a figure built from several records may be shown under: verified only when every record is, derived
     * when every record is, otherwise unverified. Null without records. Demo records are expected to be left out first.
     *
     * @param  iterable<int, string>  $classes
     */
    public static function combined(iterable $classes): ?string
    {
        $classes = collect($classes)->unique()->values();

        return match (true) {
            $classes->isEmpty() => null,
            $classes->all() === [self::VERIFIED] => self::VERIFIED,
            $classes->all() === [self::DERIVED] => self::DERIVED,
            $classes->all() === [self::DEMO] => self::DEMO,
            default => self::UNVERIFIED,
        };
    }

    /** @return Collection<int, object> */
    private static function savedRice(): Collection
    {
        return RiceProduction::query()->toBase()->get(['id', 'year', 'season', 'yield_per_hectare', 'remarks', 'verified_at']);
    }

    /** @return array<int, int> */
    private static function sampleRiceIds(): array
    {
        return rescue(fn (): array => app(SampleDataDetector::class)->excludedIds('rice_productions'), [], false);
    }

    /** The row's yield is repeated in at least COPIES records of its season. */
    private static function isCopiedYield(object $row, Collection $season): bool
    {
        if (! is_numeric($row->yield_per_hectare ?? null)) {
            return false;
        }

        return $season->filter(fn (object $other): bool => is_numeric($other->yield_per_hectare ?? null)
            && abs((float) $other->yield_per_hectare - (float) $row->yield_per_hectare) <= self::SAME_YIELD)->count() >= self::COPIES;
    }
}
