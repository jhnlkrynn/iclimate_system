<?php

namespace App\Services\Mao;

use App\Models\MaoActivityLog;
use App\Models\User;
use App\Support\LianBarangays;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Panel revision 4.4 D: writes the MAO Activity Log. Called by App\Services\SystemAuditLogger for every audited action,
 * so every page that already records an audit entry (create / edit / delete in the record lists, record validation,
 * farmer approvals, advisories, imports, barangay profiles …) also lands here when the actor is an MAO user.
 */
final class MaoActivityLogger
{
    /**
     * @param  array<string, mixed>  $context  record_type, record_id, record_label, barangay (all optional)
     */
    public static function log(?User $actor, string $action, array $context = [], ?Model $model = null): void
    {
        if (! $actor instanceof User || ! $actor->isMao() || ! SchemaCache::hasTable('mao_activity_logs')) {
            return;
        }
        // Signing in and out are account events, not work on records (they stay in the System Logs).
        if (preg_match('/\b(log ?in|log ?out|sign(ed)? ?(in|out)|login attempt)\b/i', $action) === 1) {
            return;
        }

        try {
            MaoActivityLog::query()->create([
                'user_id' => $actor->id,
                'user_name' => $actor->name,
                'user_position' => $actor->positionLabel(),
                'action' => self::verb($action),
                'description' => mb_substr($action, 0, 250),
                'record_type' => self::recordType($context['record_type'] ?? ($model ? class_basename($model) : ($context['module'] ?? null))),
                'record_id' => isset($context['record_id']) && is_numeric($context['record_id']) ? (int) $context['record_id'] : $model?->getKey(),
                'record_label' => self::label($context, $model),
                'barangay' => LianBarangays::canonicalName($context['barangay'] ?? $model?->getAttribute('barangay')) ?? ($context['barangay'] ?? null),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** "Created Farmer Profile" → created; "Validated record" → verified; "Approved farmer" → approved. */
    public static function verb(string $action): string
    {
        $text = strtolower($action);

        return match (true) {
            str_contains($text, 'delet') || str_contains($text, 'remov') || str_contains($text, 'archiv') => 'deleted',
            str_contains($text, 'unvalidat') => 'edited',
            str_contains($text, 'verif') || str_contains($text, 'validat') => 'verified',
            str_contains($text, 'reject') => 'rejected',
            str_contains($text, 'approv') || str_contains($text, 'publish') => 'approved',
            str_contains($text, 'creat') || str_contains($text, 'import') || str_contains($text, 'added') || str_contains($text, 'sent') || str_contains($text, 'post') => 'created',
            str_contains($text, 'updat') || str_contains($text, 'edit') || str_contains($text, 'chang') || str_contains($text, 'reassign') => 'edited',
            default => 'other',
        };
    }

    private static function recordType(?string $type): ?string
    {
        return $type === null ? null : (string) str($type)->headline();
    }

    /** A short name for the record ("Juan Dela Cruz", "Rice production 2025 – Balibago"). */
    private static function label(array $context, ?Model $model): ?string
    {
        if (! empty($context['record_label'])) {
            return mb_substr((string) $context['record_label'], 0, 250);
        }
        if (! $model) {
            return null;
        }
        foreach (['full_name', 'name', 'title', 'farm_name'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return mb_substr((string) $model->getAttribute($attribute), 0, 250);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
