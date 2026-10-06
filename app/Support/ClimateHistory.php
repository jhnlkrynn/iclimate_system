<?php

namespace App\Support;

use App\Models\ClimateRecord;
use App\Services\Climate\AmbulongHistoricalClimateDataset;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The climate history the prediction models read, decided in one place so the dashboard forecast
 * (PredictionEngine) and the on-demand prediction (AnalyticsPayloadFactory, run by the Python service) can
 * never work from different records.
 *
 * The models work in months (they were trained on monthly station data), so the history is always monthly:
 * entries of Climate Records are averaged per calendar month, the same way both forecast models bucket them.
 *
 * Rule: the plausible, non-sample entries of Climate Records are used when they cover at least six different
 * months. Otherwise the verified Ambulong station record is used on its own, never mixed with the entered rows.
 * Nothing is invented: when neither source is enough, the models are given what exists and report the
 * missing inputs themselves.
 */
class ClimateHistory
{
    /** The model's trailing windows (rainfall_6m, temp_6m) need this many records. */
    public const WINDOW = 6;

    /**
     * Records for the Python models, oldest first, each with record_date, rainfall, temperature, humidity,
     * wind_speed, season and source.
     *
     * @return list<array<string, mixed>>
     */
    public static function forModels(): array
    {
        return self::months()->sortBy('record_date')->values()->map(fn (object $month): array => (array) $month)->all();
    }

    /**
     * The monthly climate history, newest month first: entered records when they cover six months, otherwise
     * the station record, otherwise whatever months were entered (the models then report what is missing).
     *
     * @return Collection<int, object>
     */
    public static function months(): Collection
    {
        $entered = self::enteredMonths();

        if ($entered->count() >= self::WINDOW) {
            return $entered;
        }

        return self::stationMonths() ?? $entered;
    }

    /**
     * Entered records as one row per calendar month (the average of that month's entries; a reading that is
     * missing in every entry of a month stays missing), newest first.
     *
     * @return Collection<int, object>
     */
    public static function enteredMonths(): Collection
    {
        $average = fn (Collection $rows, string $field): ?float => ($values = $rows->pluck($field)->filter(fn ($value) => is_numeric($value)))->isEmpty()
            ? null
            : round((float) $values->avg(), 2);

        return self::entered()->get(['record_date', 'rainfall', 'temperature', 'humidity', 'wind_speed', 'season', 'source'])
            ->filter(fn (ClimateRecord $record): bool => $record->record_date !== null)
            ->groupBy(fn (ClimateRecord $record): string => $record->record_date->format('Y-m'))
            ->map(fn (Collection $rows, string $month): object => (object) [
                'record_date' => $month.'-01',
                'rainfall' => $average($rows, 'rainfall'),
                'temperature' => $average($rows, 'temperature'),
                'humidity' => $average($rows, 'humidity'),
                'wind_speed' => $average($rows, 'wind_speed'),
                'season' => $rows->sortByDesc('record_date')->first()->season ?: ClimateRecord::seasonForMonth((int) substr($month, 5, 2)),
                'source' => $rows->pluck('source')->filter()->unique()->implode(', ') ?: null,
            ])
            ->sortByDesc('record_date')
            ->values();
    }

    /**
     * Entered Climate Records a model may read: physically plausible, and never the seeded sample rows
     * (database seeder / factory data), which are not observations.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ClimateRecord>
     */
    public static function entered(): \Illuminate\Database\Eloquent\Builder
    {
        return SampleData::without(ClimateRecord::query()->plausible(), 'climate_records');
    }

    /**
     * Monthly observations of the Ambulong station record, newest first (all of them, or the latest $take).
     * Only months with every reading present are returned. Null when the record cannot be read or holds
     * fewer than six such months.
     *
     * @return Collection<int, object>|null
     */
    public static function stationMonths(?int $take = null): ?Collection
    {
        try {
            $dataset = app(AmbulongHistoricalClimateDataset::class)->load();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! ($dataset['available'] ?? false)) {
            return null;
        }

        $months = collect($dataset['observations'] ?? [])
            ->filter(fn (array $obs): bool => collect(['rainfall', 'temperature', 'humidity', 'wind_speed'])->every(fn (string $field): bool => is_numeric($obs[$field] ?? null)))
            ->sortByDesc(fn (array $obs): int => ((int) $obs['year']) * 12 + (int) $obs['month'])
            ->map(fn (array $obs): object => (object) [
                'record_date' => sprintf('%04d-%02d-01', (int) $obs['year'], (int) $obs['month']),
                'rainfall' => (float) $obs['rainfall'],
                'temperature' => (float) $obs['temperature'],
                'humidity' => (float) $obs['humidity'],
                'wind_speed' => (float) $obs['wind_speed'],
                'season' => $obs['season'] ?? ClimateRecord::seasonForMonth((int) $obs['month']),
                'source' => \App\Http\Controllers\ClimateRecordController::AMBULONG_SOURCE,
            ])
            ->values();

        if ($months->count() < self::WINDOW) {
            return null;
        }

        return $take === null ? $months : $months->take($take)->values();
    }
}
