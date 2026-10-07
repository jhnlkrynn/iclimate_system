<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SystemAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manuscript – Naicel (2026-10-08), Phase 1: IT gives a user a new temporary password (for a farmer without email,
 * who cannot use Forgot Password). The password is shown once on the user's page and never stored in plain text.
 */
class UserPasswordResetController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Use Profile to change your own password.');

        // Letters, digits and a symbol, so it passes the password rules the user will meet when they change it.
        $temporary = Str::password(12, symbols: false).'#'.random_int(1, 9);
        $user->forceFill(['password' => $temporary, 'remember_token' => null])->save();
        SystemAuditLogger::record('Reset User Password', $request, ['record_type' => 'User', 'record_id' => $user->getKey()]);

        return redirect()->route('users.show', $user)->with('temporary_password', $temporary)->with('success', 'Password reset.');
    }
}
