<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    use HasFactory;

    protected $table = 'knowledge_base';

    protected $fillable = [
        'question',
        'answer',
        'category',
        'keywords',
        'source_type',
        'source_name',
        'source_url',
        'verified',
        'times_used',
        'confidence',
    ];

    protected $casts = [
        'keywords' => 'array',
        'verified' => 'boolean',
        'times_used' => 'integer',
        'confidence' => 'decimal:2',
    ];
}
