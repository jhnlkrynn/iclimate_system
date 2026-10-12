<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Services\SystemAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Panel revision 4.4 A: choose your own password at the first sign-in (or after the default was restored). */
class FirstPasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route($request->user()->dashboardRoute());
        }

        return view('auth.first-password', ['canSkip' => (bool) config('iclimate.mao_allow_skip_password_change', true)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user = $request->user();
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'Choose a password different from the default password.']);
        }

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        $request->session()->forget(EnsurePasswordChanged::SKIP_KEY);
        SystemAuditLogger::record('Changed own password (first sign-in)', $request, ['record_type' => 'MAO account', 'record_id' => $user->id, 'record_label' => $user->name]);

        return redirect()->route($user->dashboardRoute())->with('success', 'Your new password is saved.');
    }

    /** Testing / defense only: continue with the default password for this session. */
    public function skip(Request $request): RedirectResponse
    {
        abort_unless((bool) config('iclimate.mao_allow_skip_password_change', true), 403);
        $request->session()->put(EnsurePasswordChanged::SKIP_KEY, true);

        return redirect()->route($request->user()->dashboardRoute());
    }
}
