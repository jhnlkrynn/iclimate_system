<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantingAdvisory extends Model
{
    use HasFactory;

    public const TYPE_CLIMATE = 'climate';

    public const TYPE_PLANTING = 'planting';

    public const TYPE_HARVESTING = 'harvesting';

    public const TYPE_IRRIGATION = 'irrigation';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    public const SEVERITY_INFORMATION = 'information';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MODERATE = 'moderate';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

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
    ];

    protected $casts = [
        'metadata' => 'array',
        'generated_automatically' => 'boolean',
        'requires_review' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function weatherData(): BelongsTo
    {
        return $this->belongsTo(ExternalWeatherData::class, 'weather_data_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AdvisoryRule::class, 'advisory_rule_id');
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
            $builder->whereNull('target_barangay')
                ->orWhere('target_barangay', '')
                ->orWhere('target_scope', 'municipality');

            if ($barangay) {
                $builder->orWhere('target_barangay', $barangay);
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
        });
    }

    public function isActive(): bool
    {
        $now = now();

        return $this->status === self::STATUS_PUBLISHED
            && (! $this->valid_from || $this->valid_from->lte($now))
            && (! $this->valid_until || $this->valid_until->gte($now));
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
}
