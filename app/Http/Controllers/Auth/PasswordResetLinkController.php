<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password', ['emailAvailable' => $this->emailCanBeSent()]);
    }

    /**
     * With the "log" mailer a reset link is only written to the server log and never reaches anyone, so the
     * page must not promise an email. Staff can set a new password for the account instead (Users > Edit).
     */
    private function emailCanBeSent(): bool
    {
        return config('mail.default') !== 'log';
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (! $this->emailCanBeSent()) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Password reset by email is not available yet. Please ask the Municipal Agriculture Office or IT personnel to set a new password for your account.',
            ]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
