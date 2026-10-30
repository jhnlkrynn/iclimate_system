<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldReport extends Model
{
    use HasFactory;

    public const ISSUE_TYPES = ['Pest/Disease', 'Flooding', 'Drought/Water Shortage', 'Damaged Crops', 'Other'];

    public const STATUS_PENDING = 'Pending';

    public const STATUS_VERIFIED = 'Verified';

    public const STATUS_RESOLVED = 'Resolved';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_RESOLVED];

    protected $fillable = [
        'title',
        'barangay',
        'issue_type',
        'description',
        'status',
        'reported_by',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);   // history keeps a deleted user's name
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);   // history keeps a deleted user's name
    }
}
