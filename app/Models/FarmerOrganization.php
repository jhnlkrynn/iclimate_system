<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmerOrganization extends Model
{
    use \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    protected static function booted(): void
    {
        static::deleting(function (self $record): void {
            if (\App\Support\SchemaCache::hasTable('association_yield_report_submissions')
                && $record->yieldReports()->whereHas('submissions')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['association' => 'This record has formal submission history and must be retained.']);
            }
        });
    }

    protected $fillable = [
        'name',
        'barangay',
        'farm_type',
        'farm_type_source',
        'farm_type_confirmed_at',
        'farm_type_confirmed_by',
        'registration_no',
        'date_registered',
        'area_hectares',
        'farmer_count',
        'directory_key',
        'directory_status',
        'chairman_name',
        'directory_source',
        'contact_verified_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'farmer_count' => 'integer',
        'date_registered' => 'date',
        'farm_type_confirmed_at' => 'datetime',
        'contact_verified_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    /** Farmers whose PRIMARY organization this is. */
    public function farmerProfiles(): HasMany
    {
        return $this->hasMany(FarmerProfile::class);
    }

    /** Farms linked to this association (farms.farmer_organization_id): count these where the farm or its area matters. */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /** Panel revision 1.1: every member farmer (a farmer in two organizations is a member of both). */
    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(FarmerProfile::class, 'farmer_organization_members')->withPivot('date_joined')->withTimestamps();
    }

    public function chairmanAssignments(): HasMany
    {
        return $this->hasMany(AssociationChairmanAssignment::class);
    }

    public function reportingPeriods(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AssociationReportingPeriod::class, 'association_yield_reports', 'farmer_organization_id', 'reporting_period_id');
    }

    public function yieldReports(): HasMany
    {
        return $this->hasMany(AssociationYieldReport::class);
    }
}
