<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssociationReminderDelivery extends Model
{
    public $timestamps = false;
    protected $fillable = ['delivery_key', 'farmer_organization_id', 'reporting_period_id', 'recipient_user_id', 'kind', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
