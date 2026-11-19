<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociationChairmanAssignment extends Model
{
    protected static function booted(): void
    {
        $forget = fn (self $assignment) => \Illuminate\Support\Facades\Cache::forget('navigation:association-chairman:'.$assignment->user_id);
        static::saved($forget);
        static::deleted($forget);
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = ['farmer_organization_id', 'user_id', 'status', 'assigned_by', 'reassigned_at', 'contact_verified_at'];

    protected $casts = ['reassigned_at' => 'datetime', 'contact_verified_at' => 'datetime'];

    public function association(): BelongsTo { return $this->belongsTo(FarmerOrganization::class, 'farmer_organization_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
