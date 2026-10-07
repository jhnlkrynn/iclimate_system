<?php

namespace App\Models;

use App\Services\Climate\ClimateRecordHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    public const TYPES = [
        'climate_records' => 'Climate Records Report',
        'rice_production' => 'Rice Production Report',
        'farmer_registration' => 'Farmer Registration Report',
        'advisory' => 'Advisory Report',
        'community_feed' => 'Community Feed Report',
        // Manuscript – Naicel (2026-10-08): built in PHP from stored data (App\Services\Reports\ManuscriptReportBuilder).
        'seasonal_production' => 'Seasonal Production Report',
        'yield_prediction' => 'Yield Prediction Summary',
        'climate_trend' => 'Climate Trend Report',
        'barangay_risk' => 'Barangay Risk Report',
    ];

    // Manuscript – Naicel (2026-10-08): Draft / Published, and a version per type + filters.
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            if (! \App\Support\SchemaCache::hasColumn('reports', 'version')) {
                return;
            }
            $key = hash('sha256', $report->typeKey().'|'.json_encode(\Illuminate\Support\Arr::sortRecursive($report->savedFilters())));
            $report->series_key = $key;
            $report->version = (int) self::query()->where('series_key', $key)->max('version') + 1;
            $report->status ??= self::STATUS_DRAFT;
        });
    }
    // End Manuscript – Naicel

    /** Record Type values in a climate report: the two groups are summarised separately, never together. */
    public const CLIMATE_HISTORICAL = 'Historical Weather';

    public const CLIMATE_ENTERED = 'Entered Record';

    protected $fillable = [
        'report_type',
        'title',
        'generated_by',
        'payload',
        'is_generated',
        'status',          // Manuscript – Naicel
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_generated' => 'boolean',
        'published_at' => 'datetime',   // Manuscript – Naicel
    ];

    public function scopeRealGenerated(Builder $query): Builder
    {
        return $query
            ->where('is_generated', true)
            ->whereIn('report_type', array_values(self::TYPES));
    }

    public function filterSummary(): string
    {
        $filters = (array) data_get($this->payload, 'filters', []);

        if ($filters === []) {
            return 'All records';
        }

        return collect($filters)
            ->map(fn ($value, string $key): string => str($key)->replace('_', ' ')->headline().': '.$value)
            ->implode(', ');
    }

    public function typeKey(): string
    {
        $savedType = data_get($this->payload, 'report_type');

        return is_string($savedType) && array_key_exists($savedType, self::TYPES)
            ? $savedType
            : (string) array_search($this->report_type, self::TYPES, true);
    }

    /** @return array<string, string> */
    public function savedColumns(): array
    {
        $columns = data_get($this->payload, 'columns', []);

        return is_array($columns) ? $columns : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function savedRecords(): array
    {
        $records = data_get($this->payload, 'records', []);

        return is_array($records) ? array_values($records) : [];
    }

    public function savedFilters(): array
    {
        $filters = data_get($this->payload, 'filters', []);

        return is_array($filters) ? $filters : [];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * A short, type-specific summary of the saved rows, computed on demand
     * from the already-persisted payload — no extra query, no Python
     * round-trip. Returns [] when there are no rows to summarize (a climate
     * report always explains both of its groups, even when one is empty).
     *
     * @return array<int, array{label?: string, value?: string, group?: string, source?: string, note?: string}>
     */
    public function summary(): array
    {
        $rows = collect($this->savedRecords());

        if ($this->typeKey() === 'climate_records') {
            return $this->climateSummary($rows);
        }

        if ($rows->isEmpty()) {
            return [];
        }

        return match ($this->typeKey()) {
            'rice_production' => [
                ['label' => 'Total Production', 'value' => number_format($rows->sum('total_production'), 2).' tons'],
                ['label' => 'Average Yield', 'value' => number_format($rows->avg('yield_per_hectare'), 2).' t/ha'],
                ['label' => 'Barangays Covered', 'value' => (string) $rows->pluck('barangay')->filter()->unique()->count()],
            ],
            'farmer_registration' => [
                ['label' => 'Farmers Registered', 'value' => (string) $rows->count()],
                ['label' => 'Barangays Covered', 'value' => (string) $rows->pluck('barangay')->filter()->unique()->count()],
                ['label' => 'Most Common Farm Type', 'value' => (string) ($rows->countBy('farm_type')->sortDesc()->keys()->first() ?? 'N/A')],
            ],
            'advisory' => collect($rows->countBy('status'))
                ->map(fn (int $count, string $status): array => ['label' => str($status)->headline().' Advisories', 'value' => (string) $count])
                ->values()
                ->all(),
            'community_feed' => [
                ['label' => 'Posts Published', 'value' => (string) $rows->count()],
                ['label' => 'Categories Used', 'value' => (string) $rows->pluck('category')->filter()->unique()->count()],
            ],
            // Manuscript – Naicel: the new report types save their own summary with the report.
            default => array_values((array) data_get($this->payload, 'summary', [])),
        };
    }

    /**
     * Historical station months and entered records each get their own summary; nothing is averaged across them
     * (a month's rainfall total and one day's reading are not the same measure). A row is historical when the
     * report marked it so, or when its date is a month ("2024-01"); older reports, made only from entered
     * records, are therefore summarised as entered records. Missing values are left out, never counted as 0.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $rows
     * @return list<array<string, string>>
     */
    private function climateSummary(\Illuminate\Support\Collection $rows): array
    {
        [$historical, $entered] = $rows->partition(fn ($row): bool => data_get($row, 'record_type') === self::CLIMATE_HISTORICAL
            || (data_get($row, 'record_type') === null && strlen((string) data_get($row, 'record_date')) === 7));
        $value = fn (?float $number, string $unit, int $decimals = 1): string => $number === null ? 'Not available' : number_format($number, $decimals).$unit;
        $historicalGroup = ['group' => 'Historical Weather', 'source' => ClimateRecordHistory::AMBULONG_DISPLAY_LABEL];
        $enteredGroup = ['group' => 'Entered Records', 'source' => 'iClimate staff records'];
        $items = [];

        if ($h = ClimateRecordHistory::historicalSummary($historical)) {
            $range = $h['first'] === $h['last'] ? $h['first'] : $h['first'].' – '.$h['last'];
            $temperatureRange = $h['max_temperature'] === null ? 'Not available' : number_format($h['max_temperature'], 1).'°C / '.number_format($h['min_temperature'], 1).'°C';
            array_push($items,
                $historicalGroup + ['label' => 'Months Included', 'value' => $h['months'].' ('.$range.')'],
                $historicalGroup + ['label' => 'Total Rainfall', 'value' => $value($h['total_rainfall'], ' mm')],
                $historicalGroup + ['label' => 'Average Monthly Rainfall', 'value' => $value($h['avg_monthly_rainfall'], ' mm')],
                $historicalGroup + ['label' => 'Average Temperature', 'value' => $value($h['avg_temperature'], '°C')],
                $historicalGroup + ['label' => 'Highest / Lowest Monthly Temperature', 'value' => $temperatureRange],
                $historicalGroup + ['label' => 'Average Humidity', 'value' => $value($h['avg_humidity'], '%')],
            );
        } else {
            $items[] = $historicalGroup + ['note' => 'No historical weather months for this period.'];
        }

        if ($e = ClimateRecordHistory::enteredSummary($entered)) {
            $range = $e['first'] === $e['last'] ? $e['first'] : $e['first'].' – '.$e['last'];
            array_push($items,
                $enteredGroup + ['label' => 'Records', 'value' => $e['records'].' ('.$range.')'],
                $enteredGroup + ['label' => 'Average Rainfall per Record', 'value' => $value($e['avg_rainfall'], ' mm')],
                $enteredGroup + ['label' => 'Average Temperature', 'value' => $value($e['avg_temperature'], '°C')],
                $enteredGroup + ['label' => 'Average Humidity', 'value' => $value($e['avg_humidity'], '%')],
            );
        } else {
            $items[] = $enteredGroup + ['note' => 'No entered climate records for this period.'];
        }

        return $items;
    }
}
