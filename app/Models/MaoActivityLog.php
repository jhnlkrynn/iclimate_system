<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry of the MAO Activity Log (panel revision 4.4 D). Written by App\Services\Mao\MaoActivityLogger. */
class MaoActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['created', 'edited', 'verified', 'approved', 'rejected', 'deleted', 'other'];

    protected $fillable = [
        'user_id', 'user_name', 'user_position', 'action', 'description', 'record_type', 'record_id', 'record_label', 'barangay', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
