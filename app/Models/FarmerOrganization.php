<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmerOrganization extends Model
{
    use \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    protected $fillable = [
        'name',
        'barangay',
        'farm_type',
        'area_hectares',
        'farmer_count',
    ];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'farmer_count' => 'integer',
    ];

    /** Farmers whose PRIMARY organization this is. */
    public function farmerProfiles(): HasMany
    {
        return $this->hasMany(FarmerProfile::class);
    }

    /** Panel revision 1.1: every member farmer (a farmer in two organizations is a member of both). */
    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(FarmerProfile::class, 'farmer_organization_members')->withPivot('date_joined')->withTimestamps();
    }
}
