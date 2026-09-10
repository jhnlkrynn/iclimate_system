<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotResponse extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    protected $fillable = [
        'category',
        'question',
        'answer',
        'language',
        'keywords',
        'synonyms',
        'visible_roles',
        'status',
        'times_used',
        'last_used_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'synonyms' => 'array',
        'visible_roles' => 'array',
        'times_used' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getKeywordsTextAttribute(): string
    {
        return implode(', ', $this->keywords ?? []);
    }

    public function getSynonymsTextAttribute(): string
    {
        return implode(', ', $this->synonyms ?? []);
    }

    public function getVisibleRolesTextAttribute(): string
    {
        $roles = $this->visible_roles ?? [];

        return $roles === [] ? 'All roles' : implode(', ', $roles);
    }
}
