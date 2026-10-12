<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One activity on the MAO Planner (mao_activities). barangay is null when it is for all barangays.
 */
class MaoActivity extends Model
{
    use \App\Models\Concerns\RecordsAuthor;   // panel revision 4.4: created_by / updated_by

    public const TYPES = ['Planting Event', 'Harvesting', 'Training', 'Seed Distribution', 'Field Visit', 'Meeting', 'Other'];

    protected $fillable = ['user_id', 'activity_type', 'barangay', 'starts_on', 'ends_on', 'title', 'notes'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Brgy. Balibago – Planting Event – Oct 15" / "All Barangays – Training – Oct 20–22". */
    public function summary(): string
    {
        $when = $this->starts_on->format('M j');
        if ($this->ends_on && ! $this->ends_on->isSameDay($this->starts_on)) {
            $when .= '–'.$this->ends_on->format($this->ends_on->isSameMonth($this->starts_on) ? 'j' : 'M j');
        }

        return ($this->barangay ? 'Brgy. '.$this->barangay : 'All Barangays').' – '.$this->activity_type.' – '.$when;
    }
}
