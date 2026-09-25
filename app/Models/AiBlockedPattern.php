<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use Illuminate\Database\Eloquent\Model;

class AiBlockedPattern extends Model
{
    use FlushesAiCache;

    protected $fillable = ['pattern', 'description', 'language', 'category', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function aiCacheGroup(): string
    {
        return 'moderation';
    }

    /**
     * Why a pattern is unacceptable, or null when it is safe to store. Admin
     * patterns run against every chat message, so they must compile and must
     * not be able to backtrack catastrophically.
     */
    public static function problemWith(string $pattern): ?string
    {
        if ($pattern === '' || mb_strlen($pattern) > 200) {
            return 'The pattern must be between 1 and 200 characters.';
        }

        if (str_contains($pattern, '~')) {
            return 'The pattern cannot contain the "~" character.';
        }

        // Nested quantifiers such as (a+)+ or (a*)* can take exponential time.
        if (preg_match('/\((?:[^()\\\\]|\\\\.)*[+*](?:[^()\\\\]|\\\\.)*\)\s*[+*{]/', $pattern) === 1) {
            return 'The pattern repeats a repeating group, which is not allowed.';
        }

        if (@preg_match('~'.$pattern.'~iu', '') === false) {
            return 'The pattern is not a valid regular expression.';
        }

        return null;
    }
}
