<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only snapshots of formal submissions. No historical content is fabricated/backfilled. */
class AssociationYieldReportSubmission extends Model
{
    public $timestamps = false;
    protected $fillable = ['association_yield_report_id', 'submitted_by', 'version', 'snapshot', 'submitted_at'];
    protected $casts = ['snapshot' => 'array', 'submitted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Submission history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Submission history is immutable.'));
    }

    public function report(): BelongsTo { return $this->belongsTo(AssociationYieldReport::class, 'association_yield_report_id'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
}
