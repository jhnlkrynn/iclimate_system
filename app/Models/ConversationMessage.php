<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Messages the user has received and not opened yet: the one rule the Messages page and the Farmer dashboard count with. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->where('sender_id', '!=', $user->id)->whereNull('read_at');
    }

    /** Unread messages across every conversation the user is part of. */
    public static function unreadCountFor(User $user): int
    {
        return static::query()
            ->whereIn('conversation_id', Conversation::query()
                ->where('participant_one_id', $user->id)
                ->orWhere('participant_two_id', $user->id)
                ->select('id'))
            ->unreadFor($user)
            ->count();
    }

    public function humanTimestamp(): string
    {
        $date = $this->created_at;

        if (! $date) {
            return '';
        }

        if ($date->isToday()) {
            return $date->shortTime();
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        if ($date->isCurrentYear()) {
            return $date->format('M j');
        }

        return $date->format('M j, Y');
    }
}
