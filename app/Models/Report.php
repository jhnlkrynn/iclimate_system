<?php

namespace App\Models;

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
    ];

    protected $fillable = [
        'report_type',
        'title',
        'generated_by',
        'payload',
        'is_generated',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_generated' => 'boolean',
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
     * round-trip. Returns [] when there are no rows to summarize.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function summary(): array
    {
        $rows = collect($this->savedRecords());

        if ($rows->isEmpty()) {
            return [];
        }

        return match ($this->typeKey()) {
            'climate_records' => [
                ['label' => 'Average Rainfall', 'value' => number_format($rows->avg('rainfall'), 1).' mm'],
                ['label' => 'Average Temperature', 'value' => number_format($rows->avg('temperature'), 1).'°C'],
                ['label' => 'Average Humidity', 'value' => number_format($rows->avg('humidity'), 1).'%'],
            ],
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
            default => [],
        };
    }
}
