<?php

namespace App\Services\Accounts;

use App\Models\FarmerAccountReview;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mao\FarmerApprovalNotifier;
use App\Services\SystemAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FarmerAccountReviewService
{
    /** Shown instead of a server error while the review-history migration has not run: nothing was changed. */
    public const NOT_READY_MESSAGE = 'Account review is being updated. Nothing was changed. Please try again later.';

    /** The review-history tables and columns exist (2026_11_17_000002). Cached; refreshed when migrations run. */
    public static function available(): bool
    {
        return \App\Support\SchemaCache::hasTable('farmer_account_reviews')
            && \App\Support\SchemaCache::hasColumn('users', 'registration_revision')
            && \App\Support\SchemaCache::hasColumn('notifications', 'deduplication_key');
    }

    public function decide(Request $request, int $userId, string $decision, int $revision, ?string $reasonKey = null, ?string $note = null): User
    {
        abort_unless(in_array($decision, [User::STATUS_APPROVED, User::STATUS_REJECTED], true), 422);
        abort_unless($request->user()?->isMao() && $request->user()->hasApplicationAccess(), 403);

        return $this->transaction(function () use ($request, $userId, $decision, $revision, $reasonKey, $note): User {
            $farmer = User::query()->lockForUpdate()->findOrFail($userId);
            abort_unless($farmer->role === User::ROLE_FARMER, 404);
            abort_unless($farmer->status === User::STATUS_PENDING && (int) $farmer->registration_revision === $revision, 409,
                'This application has already changed. Refresh Account Requests before reviewing it.');
            $reason = $reasonKey === null ? null : trans('user_rejection.reasons.'.$reasonKey, [], 'en');
            if ($reason !== null && filled($note)) {
                $reason .= ': '.trim($note);
            }
            $review = FarmerAccountReview::query()->create([
                'user_id' => $farmer->id, 'reviewer_id' => $request->user()->id, 'revision' => $revision,
                'decision' => $decision, 'reason_key' => $reasonKey, 'reason' => $reason,
                'application' => $farmer->only(['name', 'email', 'barangay', 'contact_number', 'resubmission_note']),
                'created_at' => now(),
            ]);
            $farmer->forceFill(['status' => $decision, 'reviewed_at' => $review->created_at, 'reviewed_by' => $request->user()->id]);
            if ($decision === User::STATUS_APPROVED) {
                $farmer->approved_at ??= now();
                $farmer->approved_by ??= $request->user()->id;
            } else {
                $farmer->rejection_reason = $reason;
            }
            $farmer->save();
            Notification::query()->firstOrCreate(['deduplication_key' => 'farmer-account-review:'.$review->id], [
                'user_id' => $farmer->id, 'title' => $decision === User::STATUS_APPROVED ? 'Account approved' : 'Account rejected',
                'message' => $decision === User::STATUS_APPROVED
                    ? 'The Municipal Agriculture Office approved your account. You may now log in.'
                    : 'Your application was rejected. '.$reason.' You can submit corrections at '.route('registration.resubmit'),
                'type' => 'Announcement', 'category' => 'account', 'is_read' => false,
            ]);
            SystemAuditLogger::record($decision === User::STATUS_APPROVED ? 'Approved Farmer Account' : 'Rejected Farmer Account', $request,
                ['record_type' => 'Farmer account', 'record_id' => $farmer->id, 'review_id' => $review->id, 'revision' => $revision, 'reason' => $reason]);
            DB::afterCommit(function () use ($farmer, $reasonKey, $note): void {
                if ($reasonKey !== null) {
                    rescue(fn () => AccountEmails::rejected($farmer, $reasonKey, $note), null, true);
                }
                rescue(fn () => app(FarmerApprovalNotifier::class)->sync(), null, true);
            });

            return $farmer;
        });
    }

    public function resubmit(Request $request, ?int $userId = null): User
    {
        return $this->transaction(function () use ($request, $userId): User {
            $query = User::query()->lockForUpdate();
            $farmer = $userId === null ? $query->where('email', strtolower(trim((string) $request->input('email'))))->first() : $query->find($userId);
            if ($userId === null) {
                $matches = $farmer && rescue(fn () => Hash::check((string) $request->input('password'), $farmer->password), false, fn (\Throwable $e): bool => \App\Support\SilentFailure::log($e, 'FarmerAccountReviewService::resubmit'));
                if (! $matches) {
                    throw ValidationException::withMessages(['email' => trans('auth.failed')]);
                }
            } else {
                abort_unless($request->user()?->isMao() && $request->user()->hasApplicationAccess(), 403);
            }
            abort_unless($farmer && $farmer->role === User::ROLE_FARMER && $farmer->status === User::STATUS_REJECTED, 409,
                'Only a rejected Farmer application can be returned for review.');
            if ($userId !== null) {
                abort_unless((int) $request->input('revision', 0) === (int) $farmer->registration_revision, 409, 'This application has changed. Refresh Account Requests.');
            }
            $farmer->forceFill([
                'status' => User::STATUS_PENDING, 'registration_revision' => (int) $farmer->registration_revision + 1,
                'resubmission_note' => trim((string) $request->input('resubmission_note')),
            ])->save();
            SystemAuditLogger::record('Resubmitted Farmer Application', $request,
                ['record_type' => 'Farmer account', 'record_id' => $farmer->id, 'revision' => $farmer->registration_revision]);
            DB::afterCommit(fn () => rescue(fn () => app(FarmerApprovalNotifier::class)->sync(), null, true));

            return $farmer;
        });
    }
    private function transaction(\Closure $operation): User
    {
        return retry(5, fn () => DB::transaction($operation, 3), 50, fn ($error) =>
            DB::connection()->getDriverName() === 'sqlite'
            && $error instanceof \Illuminate\Database\QueryException
            && in_array((int) ($error->errorInfo[1] ?? 0), [5, 6], true));
    }

}
