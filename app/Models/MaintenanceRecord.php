<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manuscript – Naicel (2026-10-08), Phase 8: one entry of IT's maintenance log. */
class MaintenanceRecord extends Model
{
    public const CATEGORIES = ['update' => 'System update', 'fix' => 'Bug fix', 'backup' => 'Backup / restore', 'security' => 'Security', 'data' => 'Data correction', 'other' => 'Other'];

    public const STATUSES = ['planned' => 'Planned', 'in_progress' => 'In progress', 'done' => 'Done'];

    protected $fillable = ['title', 'category', 'description', 'status', 'performed_at', 'performed_by'];

    protected $casts = ['performed_at' => 'datetime'];

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
