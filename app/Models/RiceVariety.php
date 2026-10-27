<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An NSIC-registered rice variety (imported from the NSIC list by rice-varieties:import) or one added by IT Personnel.
 *
 * Trial figures come per combination of crop establishment and season (COMBOS): tpr = transplanted, dwsr = direct
 * wet seeded; ds / ws = dry / wet season. Maturity is in days after sowing (DAS).
 */
class RiceVariety extends Model
{
    use HasFactory, Concerns\RecordsAuthor;

    public const COMBOS = ['tpr_ds', 'tpr_ws', 'dwsr_ds', 'dwsr_ws'];

    /** The order the catalog's one-figure sort and filters read the combinations in. */
    public const TYPICAL_ORDER = ['tpr_ws', 'tpr_ds', 'dwsr_ws', 'dwsr_ds'];

    public const TYPES = ['Inbred', 'Hybrid', 'Inbred GM/Biotech', 'Special Purpose'];

    /** Farm types a variety is recommended for: those of the planting plans (Irrigated, Rainfed). None = "Not specified". */
    public const FARM_TYPES = FarmerPlantingSchedule::FARM_TYPES;

    /** Farm type → its column. */
    public const FARM_TYPE_COLUMNS = ['Irrigated' => 'farm_irrigated', 'Rainfed' => 'farm_rainfed'];

    /** Reaction keys in the order of the NSIC list (labels: lang/{en,fil}/rice_varieties.php). */
    public const PESTS = ['glh', 'bph', 'stem_borer', 'whorl_maggot', 'rice_black_bug', 'whiteheads', 'deadhearts'];

    public const DISEASES = ['blast', 'blb', 'sheath_blight', 'tungro', 'hls', 'grassy_stunt', 'ragged_stunt'];

    public const REACTION_CODES = ['R', 'MR', 'I', 'MS', 'S'];

    public const SOURCE_IMPORT = 'nsic_import';

    public const SOURCE_MANUAL = 'manual';

    /** Maturity bands (days after sowing) of the catalog filter. */
    public const MATURITY_BANDS = ['early' => [null, 110], 'medium' => [111, 120], 'late' => [121, null]];

    protected $guarded = ['id', 'created_by', 'updated_by'];

    /** Every figure cast, so a re-import compares 96 with "96.0" (Postgres decimals) as equal and changes nothing. */
    protected function casts(): array
    {
        $casts = [
            'yield_values' => 'array',
            'reactions' => 'array',
            'amylose_content' => 'decimal:1',
            'is_active' => 'boolean',
            'farm_irrigated' => 'boolean',
            'farm_rainfed' => 'boolean',
            'year_approved' => 'integer',
            'source_no' => 'integer',
            'maturity_typical' => 'integer',
            'yield_typical' => 'integer',
        ];
        foreach (self::COMBOS as $combo) {
            $casts['yield_'.$combo] = 'integer';
            foreach (['', '_min', '_max'] as $part) {
                $casts['maturity_'.$combo.$part] = 'integer';
                $casts['height_'.$combo.$part] = 'decimal:1';
                $casts['tillers_'.$combo.$part] = 'decimal:1';
            }
        }
        foreach (['', '_min', '_max'] as $part) {
            $casts['maturity_seed_production'.$part] = 'integer';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::saving(function (self $variety): void {
            $variety->maturity_typical = $variety->firstKnown('maturity');
            $variety->yield_typical = $variety->firstKnown('yield');
        });
    }

    public function plantingSchedules(): HasMany
    {
        return $this->hasMany(FarmerPlantingSchedule::class);
    }

    // ------------------------------------------------------------------ figures

    /** The first figure of TYPICAL_ORDER that is known ("maturity" or "yield"), else the seed-production maturity. */
    public function firstKnown(string $measure): ?int
    {
        foreach (self::TYPICAL_ORDER as $combo) {
            if ($this->{$measure.'_'.$combo} !== null) {
                return (int) round((float) $this->{$measure.'_'.$combo});
            }
        }

        return $measure === 'maturity' && $this->maturity_seed_production !== null ? (int) $this->maturity_seed_production : null;
    }

    /** @return array{avg: float|null, min: float|null, max: float|null} */
    public function range(string $measure, string $combo): array
    {
        $value = fn (string $suffix): ?float => $this->{$measure.'_'.$combo.$suffix} !== null ? (float) $this->{$measure.'_'.$combo.$suffix} : null;

        return ['avg' => $value(''), 'min' => $value('_min'), 'max' => $value('_max')];
    }

    /** ["Irrigated"], ["Rainfed"], ["Irrigated", "Rainfed"] or [] (not specified). */
    public function farmTypes(): array
    {
        return array_keys(array_filter(self::FARM_TYPE_COLUMNS, fn (string $column): bool => (bool) $this->{$column}));
    }

    public function suitsFarmType(?string $farmType): bool
    {
        return isset(self::FARM_TYPE_COLUMNS[$farmType]) && (bool) $this->{self::FARM_TYPE_COLUMNS[$farmType]};
    }

    /** "NSIC 2016 Rc 480" → "NSIC Rc 480" (how farmers say it). */
    public function shortNsic(): ?string
    {
        if (! $this->nsic_reg_no || ! str_starts_with(strtoupper($this->nsic_reg_no), 'NSIC')) {
            return null;
        }

        return trim((string) preg_replace('/\b(19|20)\d{2}\s+/', '', $this->nsic_reg_no));
    }

    /** "NSIC Rc 480 (GSR 8)", or the name alone for the old Seed Board rows. */
    public function displayName(): string
    {
        $code = $this->shortNsic();

        return $code ? $code.' ('.$this->variety_name.')' : $this->variety_name;
    }

    public function hasSeason(string $season): bool
    {
        $suffix = strtolower($season) === 'dry' || strtolower($season) === 'ds' ? 'ds' : 'ws';
        foreach (['maturity', 'yield'] as $measure) {
            foreach (['tpr', 'dwsr'] as $method) {
                if ($this->{$measure.'_'.$method.'_'.$suffix} !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The planting form's variety picker: active varieties, newest first, plus the plan's own variety when it has since
     * been made inactive (it stays on the plans that use it).
     *
     * @return array<int, array{id: int, label: string, search: string, year: int|null, farm_types: array<int, string>}>
     */
    public static function pickerOptions(?int $keepId = null): array
    {
        if (! \App\Support\SchemaCache::hasTable('rice_varieties')) {
            return [];
        }

        return self::query()
            ->where(fn (Builder $q) => $q->where('is_active', true)->when($keepId, fn (Builder $k) => $k->orWhere('id', $keepId)))
            ->orderByRaw('CASE WHEN year_approved IS NULL THEN 1 ELSE 0 END')->orderByDesc('year_approved')->orderByDesc('source_no')->orderBy('variety_name')
            ->get(['id', 'variety_name', 'nsic_reg_no', 'line_name', 'owner_breeder', 'year_approved', 'farm_irrigated', 'farm_rainfed'])
            ->map(fn (self $v): array => [
                'id' => $v->id,
                'label' => $v->displayName(),
                'search' => mb_strtolower(implode(' ', array_filter([$v->variety_name, $v->nsic_reg_no, $v->shortNsic(), $v->line_name, $v->owner_breeder]))),
                'year' => $v->year_approved,
                'farm_types' => $v->farmTypes(),
            ])->all();
    }

    // ------------------------------------------------------------------ catalog

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Name, NSIC number ("Rc 480", "NSIC Rc 480" and "NSIC 2016 Rc 480" all match), line name or breeder. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) preg_replace('/^\s*nsic\s+/i', '', (string) $term));
        if ($term === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('LOWER(variety_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(nsic_reg_no) LIKE ?', [$like])
            ->orWhereRaw('LOWER(line_name) LIKE ?', [$like])
            ->orWhereRaw('LOWER(owner_breeder) LIKE ?', [$like]));
    }

    /**
     * The catalog's filter chips and sort: type, farm type (Irrigated / Rainfed; not-specified varieties only under All),
     * season (ds / ws), maturity band (early / medium / late) and
     * sort (newest, maturity, yield).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeCatalog(Builder $query, array $filters): Builder
    {
        $query->search($filters['q'] ?? null);

        if (! empty($filters['type'])) {
            $type = (string) $filters['type'];
            $query->where(fn (Builder $q) => $type === 'Inbred'
                ? $q->whereIn('variety_type', ['Inbred', 'Inbred GM/Biotech'])
                : $q->where('variety_type', $type));
        }
        if (isset(self::FARM_TYPE_COLUMNS[$filters['farm_type'] ?? ''])) {
            $query->where(self::FARM_TYPE_COLUMNS[$filters['farm_type']], true);
        }
        if (in_array($filters['season'] ?? null, ['ds', 'ws'], true)) {
            $suffix = $filters['season'];
            $query->where(fn (Builder $q) => $q->whereNotNull('maturity_tpr_'.$suffix)->orWhereNotNull('maturity_dwsr_'.$suffix)
                ->orWhereNotNull('yield_tpr_'.$suffix)->orWhereNotNull('yield_dwsr_'.$suffix));
        }
        if (isset(self::MATURITY_BANDS[$filters['maturity'] ?? ''])) {
            [$low, $high] = self::MATURITY_BANDS[$filters['maturity']];
            $query->whereNotNull('maturity_typical')
                ->when($low !== null, fn (Builder $q) => $q->where('maturity_typical', '>=', $low))
                ->when($high !== null, fn (Builder $q) => $q->where('maturity_typical', '<=', $high));
        }

        // Varieties without the sorted figure go last; newest releases first otherwise.
        return match ($filters['sort'] ?? 'newest') {
            'maturity' => $query->orderByRaw('CASE WHEN maturity_typical IS NULL THEN 1 ELSE 0 END')->orderBy('maturity_typical')->orderByDesc('year_approved'),
            'yield' => $query->orderByRaw('CASE WHEN yield_typical IS NULL THEN 1 ELSE 0 END')->orderByDesc('yield_typical')->orderByDesc('year_approved'),
            default => $query->orderByRaw('CASE WHEN year_approved IS NULL THEN 1 ELSE 0 END')->orderByDesc('year_approved')->orderByDesc('source_no')->orderByDesc('id'),
        };
    }
}
