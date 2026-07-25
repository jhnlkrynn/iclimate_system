<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvisoryRule extends Model
{
    public const TYPE_CLIMATE = 'climate';

    public const TYPE_PLANTING = 'planting';

    public const TYPE_HARVESTING = 'harvesting';

    public const TYPE_IRRIGATION = 'irrigation';

    public const SEVERITY_INFORMATION = 'information';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MODERATE = 'moderate';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'name',
        'advisory_type',
        'description',
        'severity',
        'priority',
        'conditions',
        'recommendation',
        'source_name',
        'source_reference',
        'is_active',
        'requires_crop_data',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'requires_crop_data' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function advisories(): HasMany
    {
        return $this->hasMany(PlantingAdvisory::class, 'advisory_rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
