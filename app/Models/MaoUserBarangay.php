<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One barangay (or one sitio of it) that an MAO user covers (panel revision 4.4). */
class MaoUserBarangay extends Model
{
    protected $fillable = ['user_id', 'barangay_profile_id', 'barangay', 'sitio'];

    protected static function booted(): void
    {
        // A changed assignment refreshes every cached coverage and contact list (User::maoAssignments, maoContactsFor).
        static::saved(fn () => User::forgetMaoPersonnel());
        static::deleted(fn () => User::forgetMaoPersonnel());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function barangayProfile(): BelongsTo
    {
        return $this->belongsTo(BarangayProfile::class);
    }

    /** "San Diego" or "Tan-ag (San Diego)". */
    public function label(): string
    {
        return $this->sitio ? $this->sitio.' ('.$this->barangay.')' : $this->barangay;
    }
}
