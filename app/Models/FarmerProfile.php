<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerProfile extends Model
{
    use HasFactory;

    public const FARM_TYPE_RAINFED = 'Rainfed';

    public const FARM_TYPE_IRRIGATED = 'Irrigated';

    protected $fillable = [
        'user_id',
        'full_name',
        'contact_number',
        'address',
        'barangay',
        'farm_area',
        'farm_type',
    ];

    protected $casts = [
        'farm_area' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
