<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociationMessage extends Model
{
    protected $fillable = ['farmer_organization_id', 'sender_id', 'recipient_id', 'subject', 'body', 'message_type', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];
    public function association(): BelongsTo { return $this->belongsTo(FarmerOrganization::class, 'farmer_organization_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
}
