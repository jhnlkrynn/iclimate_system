<?php

namespace App\Services\Climate;

use App\Models\ClimateRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every stored climate record, in two groups that are never mixed:
 *
 * - historical: the PAGASA Ambulong monthly record (database/data/Ambulong Monthly Data.csv, read through
 *   AmbulongHistoricalClimateDataset, the same loader as the forecast model). One row per month; rainfall is the
 *   month's total. The station is in Tanauan, Batangas; iClimate uses it as the weather reference for Lian.
 * - entered: the climate_records rows staff added, each for one date.
 *
 * Climate Records and Reports both read this, so they always list the same records. Nothing is written or invented.
 */
class ClimateRecordHistory
{
    /** How the Ambulong monthly record is labelled; the station is in Tanauan, the nearest PAGASA station to Lian. */
    public const AMBULONG_SOURCE = 'PAGASA Ambulong Station (Tanauan, Batangas)';

    /**
     * Farmer-facing names for the Ambulong rows: the page presents them as the climate reference used for Lian,
     * while the stored source above stays the true one (filters still match it) and the page notes the station.
     */
    public const AMBULONG_ROW_LABEL = 'Lian Climate Reference';

    public const AMBULONG_FILTER_LABEL = 'Lian Past Weather Records';

    public const HISTORICAL = 'historical';

    public const ENTERED = 'entered';

    public function __construct(private readonly AmbulongHistoricalClimateDataset $dataset) {}

    /**
     * The historical months and the entered rows (all of them, or only plausible ones for farmers). A part that
     * cannot be read is named in $unavailable instead of being reported as "no records".
     *
     * Duplicates: a record is identified by its month and its source. When an entered row has the same month and
     * source as an Ambulong row (a copy of the station month), the entered row is the one listed, so staff data is
     * never hidden or overwritten; the station month is not listed twice. Rows from any other source are separate
     * records and are all listed.
     *
     * @return array{0: Collection<int, object>, 1: list<string>}
     */
    public function records(bool $plausibleOnly): array
    {
        $rows = collect();
        $unavailable = [];
        $entered = collect();

        try {
            $query = ClimateRecord::query();
            $entered = ($plausibleOnly ? $query->plausible() : $query)->get(['id', 'record_date', 'rainfall', 'temperature', 'humidity', 'wind_speed', 'season', 'source']);
        } catch (QueryException $exception) {
            Log::warning('Climate records: climate_records could not be read.', ['message' => $exception->getMessage()]);
            $unavailable[] = 'entered climate records';
        }
        $enteredMonths = $entered->map(fn (ClimateRecord $record) => $record->record_date?->format('Y-m').'|'.$record->source)->flip();

        try {
            $dataset = $this->dataset->load();
            if (! ($dataset['available'] ?? false)) {
                throw new \RuntimeException((string) ($dataset['message'] ?? 'Ambulong dataset is not available.'));
            }
            foreach ($dataset['observations'] as $obs) {
                $date = Carbon::create((int) $obs['year'], (int) $obs['month'], 1)->startOfDay();
                if ($enteredMonths->has($date->format('Y-m').'|'.self::AMBULONG_SOURCE)) {
                    continue;
                }
                $rows->push($this->row(null, $date, 'month', $obs['rainfall'] ?? null, $obs['temperature'] ?? null, $obs['humidity'] ?? null, $obs['season'] ?? null, self::AMBULONG_SOURCE, $obs['wind_speed'] ?? null));
            }
        } catch (Throwable $exception) {
            Log::warning('Climate records: Ambulong monthly record could not be read.', ['message' => $exception->getMessage()]);
            $unavailable[] = 'the Ambulong station record';
        }

        $entered->each(fn (ClimateRecord $record) => $rows->push($this->row($record->id, $record->record_date, 'day', $record->rainfall, $record->temperature, $record->humidity, $record->season, $record->source, $record->wind_speed)));

        return [$rows, $unavailable];
    }

    /**
     * The rows that fall in a date range (Y-m-d bounds, either may be null). An entered row counts when its date is
     * in the range; a historical month counts when any of its days is (the summary names the months it used).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public static function inRange(Collection $rows, ?string $from, ?string $to): Collection
    {
        return $rows->filter(function (object $row) use ($from, $to): bool {
            $start = $row->record_date->format('Y-m-d');
            $end = $row->period === 'month' ? $row->record_date->copy()->endOfMonth()->format('Y-m-d') : $start;

            return (! $from || $end >= $from) && (! $to || $start <= $to);
        })->values();
    }

    /**
     * Summary of the historical months. Rainfall is each month's total, so it is added up (the rainfall over those
     * months) and also shown as the usual month; temperature and humidity are averages of the monthly values. A
     * value missing for a month is left out of that figure, never counted as 0. Null when there are no months.
     *
     * @param  iterable<int, object|array<string, mixed>>  $months
     * @return array{months: int, first: string, last: string, total_rainfall: ?float, avg_monthly_rainfall: ?float, avg_temperature: ?float, max_temperature: ?float, min_temperature: ?float, avg_humidity: ?float}|null
     */
    public static function historicalSummary(iterable $months): ?array
    {
        $months = collect($months)->map(fn ($row) => (array) $row);
        if ($months->isEmpty()) {
            return null;
        }

        $values = fn (string $field) => $months->pluck($field)->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);
        $rain = $values('rainfall');
        $temperature = $values('temperature');
        $humidity = $values('humidity');
        $order = $months->sortBy(fn ($row) => self::sortDate($row['record_date'] ?? null));

        return [
            'months' => $months->count(),
            'first' => self::monthLabel($order->first()['record_date'] ?? null),
            'last' => self::monthLabel($order->last()['record_date'] ?? null),
            'total_rainfall' => $rain->isNotEmpty() ? $rain->sum() : null,
            'avg_monthly_rainfall' => $rain->isNotEmpty() ? $rain->avg() : null,
            'avg_temperature' => $temperature->isNotEmpty() ? $temperature->avg() : null,
            'max_temperature' => $temperature->max(),
            'min_temperature' => $temperature->min(),
            'avg_humidity' => $humidity->isNotEmpty() ? $humidity->avg() : null,
        ];
    }

    /**
     * Summary of entered records: how many, their dates, and averages per record. Entered records are single-date
     * readings with no promise of covering every day, so their rainfall is never added up as a period total. Null
     * when there are no records.
     *
     * @param  iterable<int, object|array<string, mixed>>  $records
     * @return array{records: int, first: string, last: string, avg_rainfall: ?float, avg_temperature: ?float, avg_humidity: ?float}|null
     */
    public static function enteredSummary(iterable $records): ?array
    {
        $records = collect($records)->map(fn ($row) => (array) $row);
        if ($records->isEmpty()) {
            return null;
        }

        $values = fn (string $field) => $records->pluck($field)->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);
        $order = $records->sortBy(fn ($row) => self::sortDate($row['record_date'] ?? null));
        $day = fn ($value) => $value ? Carbon::parse(self::sortDate($value))->format('M j, Y') : '—';

        return [
            'records' => $records->count(),
            'first' => $day($order->first()['record_date'] ?? null),
            'last' => $day($order->last()['record_date'] ?? null),
            'avg_rainfall' => ($rain = $values('rainfall'))->isNotEmpty() ? $rain->avg() : null,
            'avg_temperature' => ($temperature = $values('temperature'))->isNotEmpty() ? $temperature->avg() : null,
            'avg_humidity' => ($humidity = $values('humidity'))->isNotEmpty() ? $humidity->avg() : null,
        ];
    }

    private static function sortDate(mixed $value): string
    {
        $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;

        // A historical month is stored as "2024-01"; it sorts as its first day.
        return strlen($value) === 7 ? $value.'-01' : substr($value, 0, 10);
    }

    private static function monthLabel(mixed $value): string
    {
        return $value ? Carbon::parse(self::sortDate($value))->format('M Y') : '—';
    }

    private function row(?int $id, ?Carbon $date, string $period, mixed $rainfall, mixed $temperature, mixed $humidity, ?string $season, ?string $source, mixed $windSpeed = null): object
    {
        $date ??= Carbon::create(1970);
        $label = $period === 'month' ? $date->format('M Y') : $date->format('M j, Y');
        // Missing stays missing (null), never 0.
        $number = fn ($value) => is_numeric($value) ? (float) $value : null;

        return (object) [
            'id' => $id,
            'group' => $id === null ? self::HISTORICAL : self::ENTERED,
            'record_date' => $date,
            'date_label' => $label,
            'period' => $period,
            'rainfall' => $number($rainfall),
            'temperature' => $number($temperature),
            'humidity' => $number($humidity),
            'wind_speed' => $number($windSpeed),
            'season' => $season ?: null,
            'source' => $source ?: null,
            'source_label' => $source === self::AMBULONG_SOURCE ? self::AMBULONG_ROW_LABEL : ($source ?: null),
            'search_text' => mb_strtolower(implode(' ', [$label, $date->format('F Y'), $date->format('Y-m'), $date->format('Y-m-d'), $season, $source, $source === self::AMBULONG_SOURCE ? self::AMBULONG_ROW_LABEL.' '.self::AMBULONG_FILTER_LABEL : null])),
        ];
    }
}
