<?php

namespace App\Models;

use App\Support\JsonColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantingAdvisory extends Model
{
    use HasFactory, \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    public const TYPE_CLIMATE = 'climate';

    public const TYPE_PLANTING = 'planting';

    public const TYPE_HARVESTING = 'harvesting';

    public const TYPE_IRRIGATION = 'irrigation';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    /** Manuscript – Naicel (2026-10-08): an MAO alert waiting for its scheduled_for time (iclimate:publish-scheduled-alerts). */
    public const STATUS_SCHEDULED = 'scheduled';

    public const SEVERITY_INFORMATION = 'information';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MODERATE = 'moderate';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SOURCE_PAGASA_LEGACY = 'PAGASA';

    public const SOURCE_PAGASA_ONLINE_ADVISORY = 'PAGASA Online Advisory Page';

    public const SOURCE_PAGASA_WEEKLY_OUTLOOK = 'PAGASA Online Weekly Weather Outlook';

    protected $fillable = [
        'title',
        'content',
        'type',
        'advisory_type',
        'summary',
        'message',
        'recommended_action',
        'severity',
        'priority',
        'target_barangay',
        'target_barangay_id',
        'target_scope',
        'source',
        'source_url',
        'weather_data_id',
        'advisory_rule_id',
        'generation_key',
        'generated_automatically',
        'requires_review',
        'valid_from',
        'valid_until',
        'published_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'metadata',
        'posted_by',
        'status',
        'scheduled_for',   // Manuscript – Naicel (2026-10-08)
    ];

    protected $casts = [
        'metadata' => 'array',
        'generated_automatically' => 'boolean',
        'requires_review' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
        'scheduled_for' => 'datetime',   // Manuscript – Naicel
    ];

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);   // history keeps a deleted user's name
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);   // history keeps a deleted user's name
    }

    public function weatherData(): BelongsTo
    {
        return $this->belongsTo(ExternalWeatherData::class, 'weather_data_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AdvisoryRule::class, 'advisory_rule_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(AdvisoryFeedback::class, 'planting_advisory_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->published()->notExpired();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeForBarangay(Builder $query, ?string $barangay): Builder
    {
        return $query->where(function (Builder $builder) use ($barangay) {
            // metadata is text on the live database: JSON reads go through JsonColumn (a raw ->> is a PostgreSQL error).
            $builder->where(function (Builder $wide) {
                $wide->where(fn ($q) => $q->whereNull('target_barangay')->orWhere('target_barangay', '')->orWhere('target_scope', 'municipality'));
                JsonColumn::whereKeyMissingOrNot($wide, 'metadata', 'scope_type', 'barangay');
            });

            if ($barangay) {
                $builder->orWhere('target_barangay', $barangay);
                JsonColumn::orWhereContains($builder, 'metadata', 'pagasa_barangay_matches', $barangay);
            }
        });
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('advisory_type', $type) : $query;
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('valid_until')->orWhere('valid_until', '>=', now());
        })->where(function (Builder $builder) {
            $builder->whereNull('valid_from')->orWhere('valid_from', '<=', now());
        });
    }

    /**
     * Each forecast run of a rule creates its own advisory (same title, new weather basis and validity), so an
     * older run stays active next to the newer one that replaces it. Keep only the newest active run per rule,
     * title and target; nothing is changed in the table. PAGASA and hand-written advisories have no rule and are never hidden.
     */
    public function scopeLatestRuns(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->whereNotExists(function ($newer) use ($table) {
            $newer->selectRaw('1')
                ->from($table.' as newer')
                ->whereColumn('newer.advisory_rule_id', $table.'.advisory_rule_id')
                ->whereColumn('newer.title', $table.'.title')
                ->whereRaw("coalesce(newer.target_barangay, '') = coalesce({$table}.target_barangay, '')")
                ->whereColumn('newer.created_at', '>', $table.'.created_at')
                ->where('newer.status', self::STATUS_PUBLISHED)
                ->where(fn ($q) => $q->whereNull('newer.valid_until')->orWhere('newer.valid_until', '>=', now()));
        });
    }

    public function scopeFromPagasaOnline(Builder $query): Builder
    {
        return $query->whereIn('source', self::pagasaOnlineSources());
    }

    public static function pagasaOnlineSources(): array
    {
        return [
            self::SOURCE_PAGASA_LEGACY,
            self::SOURCE_PAGASA_ONLINE_ADVISORY,
            self::SOURCE_PAGASA_WEEKLY_OUTLOOK,
        ];
    }

    public function isActive(): bool
    {
        $now = now();

        return $this->status === self::STATUS_PUBLISHED
            && (! $this->valid_from || $this->valid_from->lte($now))
            && (! $this->valid_until || $this->valid_until->gte($now));
    }

    public function isOfficialPagasa(): bool
    {
        $url = $this->source_url ?: data_get($this->metadata, 'pagasa_source_url');

        return in_array($this->source, self::pagasaOnlineSources(), true)
            && (bool) preg_match('#^https://(?:[a-z0-9-]+\.)?pagasa\.dost\.gov\.ph/#i', (string) $url);
    }

    public function typeLabel(): string
    {
        return str((string) ($this->advisory_type ?: $this->type))->replace('_', ' ')->headline()->toString();
    }

    public function severityLabel(): string
    {
        return str((string) $this->severity)->replace('_', ' ')->headline()->toString();
    }

    public function statusLabel(): string
    {
        return str((string) $this->status)->replace('_', ' ')->headline()->toString();
    }

    public function sourceLabel(): string
    {
        if ($this->source === self::SOURCE_PAGASA_LEGACY) {
            return 'PAGASA Online Advisory Page';
        }

        return $this->source ?: 'Open-Meteo Forecast API + iClimate Decision Rules';
    }

    /**
     * Plain-language source for Farmer/MAO screens. The raw provider name from
     * sourceLabel() stays available for IT (advisory detail page).
     */
    public function plainSourceLabel(): string
    {
        $label = $this->sourceLabel();

        if (stripos($label, 'pagasa') !== false) {
            return 'PAGASA';
        }

        if (stripos($label, 'open-meteo') !== false) {
            return 'Live weather and iClimate guidance';
        }

        return $label;
    }

    public function horizonLabel(): string
    {
        return (string) data_get($this->metadata, 'advisory_horizon_label', 'Forecast');
    }

    public function targetLabel(): string
    {
        return $this->target_barangay ?: 'All Barangays';
    }

    public function typeBadgeClass(): string
    {
        return [
            self::TYPE_CLIMATE => 'text-bg-primary',
            self::TYPE_PLANTING => 'text-bg-success',
            self::TYPE_HARVESTING => 'text-bg-warning',
            self::TYPE_IRRIGATION => 'text-bg-info',
        ][$this->advisory_type] ?? 'text-bg-light';
    }

    public function severityBadgeClass(): string
    {
        return [
            self::SEVERITY_INFORMATION => 'text-bg-light',
            self::SEVERITY_LOW => 'text-bg-success',
            self::SEVERITY_MODERATE => 'text-bg-warning',
            self::SEVERITY_HIGH => 'text-bg-danger',
            self::SEVERITY_CRITICAL => 'text-bg-dark',
        ][$this->severity] ?? 'text-bg-light';
    }

    public function statusBadgeClass(): string
    {
        return [
            self::STATUS_PENDING_REVIEW => 'text-bg-warning',
            self::STATUS_PUBLISHED => 'text-bg-success',
            self::STATUS_EXPIRED => 'text-bg-secondary',
            self::STATUS_REJECTED => 'text-bg-danger',
            self::STATUS_ARCHIVED => 'text-bg-dark',
        ][$this->status] ?? 'text-bg-light';
    }

    public function displaySummary(): string
    {
        $raw = (string) $this->summary;
        if (str_contains($raw, 'Toggle navigation') || str_contains($raw, 'GOVPH') || str_contains($raw, 'Details from PAGASA online page:')) {
            $location = $this->target_barangay ?: 'Lian and Batangas';

            return match ($this->severity) {
                self::SEVERITY_CRITICAL => "PAGASA Alert: Heavy rainfall and flood risk in {$location}. Secure crops and pause field work.",
                self::SEVERITY_HIGH => "PAGASA Warning: Moderate to heavy rain expected in {$location}. Delay spraying and check drainage.",
                self::SEVERITY_MODERATE => "PAGASA Notice: Rainshowers and thunderstorms over {$location}. Check field water before irrigating.",
                default => "PAGASA Update: Light to moderate rains expected in {$location}. Check field moisture before irrigating or applying fertilizer.",
            };
        }

        return $raw ?: (string) str($this->content)->limit(160);
    }

    public function displayMessage(): string
    {
        $raw = (string) ($this->message ?: $this->content ?: '');
        if (str_contains($raw, 'Toggle navigation') || str_contains($raw, 'GOVPH') || str_contains($raw, 'METAR') || str_contains($raw, 'Details from PAGASA online page:')) {
            $location = $this->target_barangay ? "{$this->target_barangay}, Lian, Batangas" : 'Lian and Batangas';
            $severity = $this->severity ?: self::SEVERITY_INFORMATION;

            $situation = match ($severity) {
                self::SEVERITY_CRITICAL => 'Heavy rainfall and emergency flood threats are expected across the area.',
                self::SEVERITY_HIGH => 'Moderate to heavy rains and possible localized flooding are expected.',
                self::SEVERITY_MODERATE => 'Scattered rainshowers and localized thunderstorms are expected.',
                default => 'Light to moderate rainfall and cloudy skies are expected.',
            };

            $advice = match ($severity) {
                self::SEVERITY_CRITICAL => 'Stop all field activities immediately. Move farm tools, fertilizer, and animals to higher ground. Keep drainage canals clear.',
                self::SEVERITY_HIGH => 'Pause pesticide and fertilizer spraying. Check farm dikes and clear drainage pathways to avoid standing water.',
                self::SEVERITY_MODERATE => 'Inspect field water levels before irrigating. Postpone chemical spraying if rain is approaching.',
                default => 'Inspect field moisture before watering or fertilizing. Normal farm activities may proceed while monitoring the weather.',
            };

            return "Official PAGASA Weather Advisory for {$location}.\n\n"
                ."• Weather Situation: {$situation}\n"
                ."• Farm Guidance: {$advice}\n"
                .'• Source: PAGASA Online Weather Bulletin';
        }

        return $raw;
    }
}
