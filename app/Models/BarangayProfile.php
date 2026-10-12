<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manuscript – Naicel (2026-10-08), Phase 3: the MAO's facts about one barangay (Barangays page). The heat map's
 * Flood-prone, Drought-prone and MAO classification layers read these rows.
 */
class BarangayProfile extends Model
{
    use \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    public const CLASSIFICATIONS = ['Low', 'Moderate', 'High'];

    protected $fillable = [
        'barangay',
        'latitude',
        'longitude',
        'farm_area_hectares',
        'risk_classification',
        'flood_prone',
        'drought_prone',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'farm_area_hectares' => 'decimal:2',
        'flood_prone' => 'boolean',
        'drought_prone' => 'boolean',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
