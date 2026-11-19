<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociationYieldReportReview extends Model
{
    protected $fillable = ['association_yield_report_id', 'reviewer_id', 'action', 'notes'];
    public function report(): BelongsTo { return $this->belongsTo(AssociationYieldReport::class, 'association_yield_report_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
