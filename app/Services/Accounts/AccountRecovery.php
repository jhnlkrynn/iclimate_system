<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Recovery for an account whose password is shared (a published default or a copied hash). Knowing that password
 * proves nothing, so it never signs in or sets a new password. Ownership is shown one of two ways:
 *
 *  - email: a standard reset link (expires after config auth.passwords.users.expire minutes) to the account's own
 *    address, only when that address can receive mail (AccountEmails::recipient: no placeholder domains);
 *  - MAO-assisted: MAO Staff check the person (in person, a call back to the registered number, or an ID) and issue a
 *    one-time code. It is stored hashed in password_reset_tokens, so it works on the standard reset form, expires the
 *    same way, and replaces any earlier link or code.
 */
final class AccountRecovery
{
    public const METHOD_EMAIL = 'email';

    public const METHOD_MAO = 'mao';

    public const VERIFICATION_METHODS = ['in_person' => 'Checked in person (valid ID)', 'phone_callback' => 'Called back the registered contact number', 'association_vouched' => 'Confirmed by the association chairman, in person'];

    /** Unambiguous letters and digits (no 0/O, 1/I/L): read aloud or written down without mistakes. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function method(User $user): string
    {
        return AccountEmails::recipient($user) !== null ? self::METHOD_EMAIL : self::METHOD_MAO;
    }

    /** Sends the reset link when the account has a working address. True when one was sent (or already pending). */
    public static function sendEmailLink(User $user): bool
    {
        if (self::method($user) !== self::METHOD_EMAIL) {
            return false;
        }
        $status = rescue(fn () => Password::sendResetLink(['email' => $user->email]), null, true);

        return in_array($status, [Password::RESET_LINK_SENT, Password::RESET_THROTTLED], true);
    }

    /**
     * MAO-assisted recovery: returns the one-time code to give to the person (shown once, never stored in plain text).
     * The account must choose a new password with it; every open session of the account is ended now.
     */
    public static function issueCode(User $account, User $staff, string $verification, ?string $note, ?Request $request = null): string
    {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        DB::transaction(function () use ($account, $code): void {
            DB::table((string) config('auth.passwords.users.table', 'password_reset_tokens'))->updateOrInsert(
                ['email' => $account->email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );
            $account->forceFill(['must_change_password' => true])->saveQuietly();
        });
        \App\Auth\CachedEloquentUserProvider::forget($account->getKey());
        PasswordChange::endOtherSessions($account);
        // The owner is told who issued a code and when, so a code nobody asked for is noticed and reported.
        \App\Models\Notification::query()->create([
            'user_id' => $account->id, 'title' => 'Account recovery code issued',
            'message' => 'MAO staff member '.$staff->name.' gave out a code to set a new password for your account on '
                .now('Asia/Manila')->format('M j, Y g:i A').'. If you did not ask for this, tell the Municipal Agriculture Office right away.',
            'type' => 'Warning', 'category' => 'account', 'is_read' => false,
        ]);
        SystemAuditLogger::record('Issued account recovery code', $request, [
            'record_type' => 'Farmer account', 'record_id' => $account->id, 'verification' => $verification,
            'note' => $note !== null ? mb_substr($note, 0, 300) : null, 'expires_minutes' => (int) config('auth.passwords.users.expire', 60),
        ], $staff);

        return implode('-', str_split($code, 5));
    }
}
