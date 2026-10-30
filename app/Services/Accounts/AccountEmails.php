<?php

namespace App\Services\Accounts;

use App\Mail\AccountStatusMail;
use App\Models\AccountEmail;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails a person about their own account: approved, not approved, deleted, restored. Always to that account's own
 * registered address (users.email, as it is now; a deleted account keeps the one it had), never to the staff member
 * who acted or a fixed address. From is MAIL_FROM_ADDRESS. A placeholder or invalid address gets nothing and the
 * user's page says "No valid email". Each email is recorded in account_emails for that page's Last email line.
 *
 * Sending: queued when config user_emails.queue is on (a worker runs), otherwise at once; a queue that cannot take it
 * falls back to sending at once. A failed send never stops the approval, deletion or restore itself.
 */
final class AccountEmails
{
    public const TYPES = ['approved', 'rejected', 'deleted', 'restored'];

    public static function approved(User $user): ?AccountEmail
    {
        return self::send($user, 'approved', ['date' => self::day(now(), $user)]);
    }

    /** $reason: what the MAO wrote when rejecting the sign-up. */
    public static function rejected(User $user, string $reason): ?AccountEmail
    {
        return self::send($user, 'rejected', ['reason' => $reason, 'date' => self::day(now(), $user), 'url' => url('/').'#register']);
    }

    /** The same reason words as the login panel (lang user_deletion.reasons.<key>.message), in the person's language. */
    public static function deleted(User $user): ?AccountEmail
    {
        return self::send($user, 'deleted', [
            'reason' => AccountDeletion::reasonMessage($user->deleted_reason, $user->mailLocale()),
            'other' => $user->deleted_reason === 'other' ? (string) $user->deleted_reason_other : null,
            'date' => self::day($user->deleted_at ?? now(), $user),
            'days' => (int) config('user_deletion.restore_days', 30),
        ]);
    }

    public static function restored(User $user): ?AccountEmail
    {
        return self::send($user, 'restored', ['date' => self::day(now(), $user)]);
    }

    /** The address to write to, or null when there is none worth writing to (empty, invalid, a placeholder domain). */
    public static function recipient(User $user): ?string
    {
        $email = mb_strtolower(trim((string) $user->email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        $domain = substr(strrchr($email, '@'), 1);
        foreach ((array) config('user_emails.placeholder_domains', []) as $placeholder) {
            $placeholder = mb_strtolower(ltrim((string) $placeholder, '.@'));
            if ($domain === $placeholder || str_ends_with($domain, '.'.$placeholder)) {
                return null;
            }
        }

        return $email;
    }

    /** The account's latest email, for the Last email line (null: none yet). */
    public static function last(User $user): ?AccountEmail
    {
        return SchemaCache::hasTable('account_emails')
            ? AccountEmail::query()->where('user_id', $user->getKey())->latest('id')->first()
            : null;
    }

    /** @param  array<string, string|int|null>  $words */
    public static function send(User $user, string $type, array $words): ?AccountEmail
    {
        $recipient = self::recipient($user);
        $log = SchemaCache::hasTable('account_emails')
            ? AccountEmail::query()->create([
                'user_id' => $user->getKey(), 'type' => $type, 'recipient' => $recipient ?? ($user->email ?: null),
                'status' => $recipient === null ? AccountEmail::STATUS_NO_VALID_EMAIL : AccountEmail::STATUS_QUEUED,
            ])
            : null;
        if ($recipient === null) {
            return $log;
        }

        $mail = new AccountStatusMail($type, ['name' => $user->name] + $words + ['url' => url('/').'#login'], $log?->getKey(), $user->mailLocale());

        if (config('user_emails.queue') && config('queue.default') !== 'sync') {
            try {
                Mail::to($recipient)->queue($mail);

                return $log;
            } catch (Throwable $e) {
                report($e);   // no queue to take it: send it now instead
            }
        }

        try {
            Mail::to($recipient)->sendNow($mail);
            $log?->forceFill(['status' => AccountEmail::STATUS_SENT, 'sent_at' => now(), 'error' => null])->save();
        } catch (Throwable $e) {
            report($e);
            $log?->forceFill(['status' => AccountEmail::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
        }

        return $log;
    }

    /** "October 31, 2026" / "Oktubre 31, 2026" in the person's language and Lian time. */
    private static function day(\DateTimeInterface $at, User $user): string
    {
        return Carbon::instance($at)->timezone(\App\Support\BusinessClock::timezone())->locale($user->mailLocale() === 'fil' ? 'fil' : 'en')->translatedFormat('F j, Y');
    }
}
