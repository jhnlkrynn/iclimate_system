<?php

namespace App\Services\Accounts;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * IT deleting a user account, with a stated reason, and restoring it within config user_deletion.restore_days.
 *
 * Deleting closes the account; it does not erase it. The row keeps its email so the login page can tell the person
 * why (AccountDeletion::panelFor) and the restore can bring it back. Once closed: the account is hidden from every user
 * query (Scopes\HideDeletedUsers), its sessions end, its farms are archived and its open planting plans are closed as
 * "Account deleted"; its history (harvests, reports, messages, logs) stays.
 *
 * Nothing is purged automatically. The Data Privacy Act may require removing personal data after a retention period;
 * that would be a separate scheduled purge of accounts deleted more than N days ago (see config/user_deletion.php).
 */
final class AccountDeletion
{
    /** Progress status of a planting plan that was still open when its farmer's account was deleted. */
    public const PLAN_STATUS = 'Account deleted';

    /** @return array<string, array{show_panel: bool, send_email: bool, requires_text: bool}> */
    public static function reasons(): array
    {
        return (array) config('user_deletion.reasons', []);
    }

    public static function reasonLabel(?string $key, ?string $locale = null): string
    {
        return (string) trans('user_deletion.reasons.'.$key.'.label', [], $locale);
    }

    /** What the person is told (the login panel and the email), in their language. */
    public static function reasonMessage(?string $key, ?string $locale = null): string
    {
        return (string) trans('user_deletion.reasons.'.$key.'.message', [], $locale);
    }

    public static function showsPanel(?string $key): bool
    {
        return (bool) (self::reasons()[$key]['show_panel'] ?? false);
    }

    public static function sendsEmail(?string $key): bool
    {
        return (bool) (self::reasons()[$key]['send_email'] ?? false);
    }

    /** Why this IT user may not delete this account, or null when they may. */
    public static function refusal(User $target, User $actor): ?string
    {
        return match (true) {
            $actor->role !== User::ROLE_IT_EXPERT => 'Only IT Personnel can delete accounts.',
            $target->is($actor) => 'You cannot delete your own account. Ask another IT Personnel to do it.',
            $target->isDeleted() => 'This account is already deleted.',
            \App\Http\Controllers\UserManagementController::isLastActiveItExpert($target) => 'You cannot delete the last active IT Personnel account. Create another IT Personnel account first.',
            default => null,
        };
    }

    /**
     * @param  array{reason: string, reason_other?: ?string, note_internal?: ?string}  $data  validated
     */
    public function delete(User $target, User $actor, array $data, ?Request $request = null): void
    {
        if (($message = self::refusal($target, $actor)) !== null) {
            throw ValidationException::withMessages(['reason' => $message]);
        }

        $reason = $data['reason'];
        DB::transaction(function () use ($target, $actor, $data, $reason): void {
            $target->forceFill([
                'status_before_delete' => $target->status,
                'status' => User::STATUS_DELETED,
                'deleted_reason' => $reason,
                'deleted_reason_other' => $reason === 'other' ? trim((string) ($data['reason_other'] ?? '')) : null,
                'deleted_note_internal' => trim((string) ($data['note_internal'] ?? '')) ?: null,
                'deleted_at' => now(),
                'deleted_by' => $actor->getKey(),
                'remember_token' => null,
            ])->save();

            // Their farms leave the lists and maps (archived; the farmer or MAO can bring one back after a restore).
            if (SchemaCache::hasTable('farms')) {
                Farm::query()->ownedBy($target)->active()->update(['archived_at' => now(), 'is_primary' => false, 'updated_at' => now()]);
            }
            // Plans still running are closed; harvested ones stay as they are (history for reports).
            if (SchemaCache::hasTable('farmer_planting_schedules')) {
                FarmerPlantingSchedule::query()->withoutGlobalScope('hide_closed_by_account_deletion')->where('user_id', $target->getKey())->whereNull('harvested_on')
                    ->where(fn ($q) => $q->whereNull('progress_status')->orWhereNotIn('progress_status', ['Harvested', self::PLAN_STATUS]))
                    ->update(['progress_status' => self::PLAN_STATUS, 'updated_at' => now()]);
            }
            // Signed in elsewhere: those sessions end now (database sessions). Any other session ends on its next
            // request, since the signed-in user lookup no longer finds the account.
            if (config('session.driver') === 'database' && SchemaCache::hasTable((string) config('session.table', 'sessions'))) {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->getKey())->delete();
            }
        });
        ActiveFarm::forget($target);

        SystemAuditLogger::record('Deleted User Account', $request, [
            'record_type' => 'User', 'record_id' => $target->getKey(), 'record_label' => $target->name,
            'role' => $target->role, 'reason' => $reason, 'deleted_at' => $target->deleted_at?->toIso8601String(),
        ], $actor);

        if (self::sendsEmail($reason)) {
            AccountEmails::deleted($target);
        }
    }

    /** Brings the account back as it was (its farms stay archived until the farmer or MAO restores them). */
    public function restore(User $target, User $actor, ?Request $request = null): void
    {
        if ($actor->role !== User::ROLE_IT_EXPERT) {
            abort(403);
        }
        if (! $target->canBeRestored()) {
            throw ValidationException::withMessages(['restore' => 'This account can no longer be restored: it was deleted more than '.(int) config('user_deletion.restore_days', 30).' days ago.']);
        }

        $reason = $target->deleted_reason;
        $target->forceFill([
            'status' => $target->status_before_delete && $target->status_before_delete !== User::STATUS_DELETED ? $target->status_before_delete : User::STATUS_ACTIVE,
            'status_before_delete' => null,
            'deleted_reason' => null,
            'deleted_reason_other' => null,
            'deleted_note_internal' => null,
            'deleted_at' => null,
            'deleted_by' => null,
        ])->save();

        SystemAuditLogger::record('Restored User Account', $request, [
            'record_type' => 'User', 'record_id' => $target->getKey(), 'record_label' => $target->name, 'deleted_reason' => $reason,
        ], $actor);

        if ($reason !== 'deceased') {
            AccountEmails::restored($target);
        }
    }
}
