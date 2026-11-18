<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssociationReportingPeriod extends Model
{
    protected $fillable = ['period_key', 'label', 'reporting_year', 'season', 'opens_at', 'due_at', 'required', 'field_requirements', 'created_by', 'updated_by'];

    protected $casts = ['opens_at' => 'datetime', 'due_at' => 'datetime', 'required' => 'boolean', 'field_requirements' => 'array'];

    public function reports(): HasMany { return $this->hasMany(AssociationYieldReport::class, 'reporting_period_id'); }
    public function isPastDue(): bool { return $this->required && now()->greaterThan($this->due_at); }
}
