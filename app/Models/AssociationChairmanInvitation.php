<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssociationChairmanInvitation extends Model
{
    protected $fillable = ['association_chairman_assignment_id', 'token_hash', 'expires_at', 'accepted_at', 'invited_by'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    public function assignment() { return $this->belongsTo(AssociationChairmanAssignment::class, 'association_chairman_assignment_id'); }
}
