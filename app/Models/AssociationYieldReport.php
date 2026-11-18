<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssociationYieldReport extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const UNDER_REVIEW = 'under_review';
    public const NEEDS_REVISION = 'needs_revision';
    public const ACCEPTED = 'accepted';

    public const STATUSES = [self::DRAFT, self::SUBMITTED, self::UNDER_REVIEW, self::NEEDS_REVISION, self::ACCEPTED];

    protected $fillable = [
        'farmer_organization_id', 'reporting_period_id', 'submitted_by', 'status', 'planting_date', 'harvest_date',
        'rice_variety', 'irrigation_type', 'area_planted_hectares', 'area_harvested_hectares', 'production_tons',
        'average_yield_t_per_hectare', 'participating_farmers', 'crop_losses', 'remarks', 'supporting_document_path',
        'submitted_at', 'reviewed_by', 'reviewed_at', 'research_provenance', 'research_verified_at', 'research_verified_by',
    ];

    protected $casts = [
        'planting_date' => 'date', 'harvest_date' => 'date', 'area_planted_hectares' => 'decimal:2',
        'area_harvested_hectares' => 'decimal:2', 'production_tons' => 'decimal:2',
        'average_yield_t_per_hectare' => 'decimal:4', 'participating_farmers' => 'integer',
        'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'research_verified_at' => 'datetime',
    ];

    public function association(): BelongsTo { return $this->belongsTo(FarmerOrganization::class, 'farmer_organization_id'); }
    public function reportingPeriod(): BelongsTo { return $this->belongsTo(AssociationReportingPeriod::class, 'reporting_period_id'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function reviews(): HasMany { return $this->hasMany(AssociationYieldReportReview::class, 'association_yield_report_id'); }
    public function isFormal(): bool { return $this->status !== self::DRAFT; }
}
