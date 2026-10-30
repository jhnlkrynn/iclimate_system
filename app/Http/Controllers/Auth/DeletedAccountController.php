<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\AccountDeletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * "Your iClimate account was deleted": shown once, right after the person signed in with the correct email and
 * password of a deleted account (LoginRequest flashes the account id). They are not signed in. The reason is in their
 * saved language; IT's internal note and the name of who deleted it are never shown. Opened any other way, or for a
 * reason without a panel (Deceased), it goes back to the home page.
 */
class DeletedAccountController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $id = $request->session()->get('deleted_account_id');
        $user = is_int($id) ? User::withDeleted()->find($id) : null;
        if ($user === null || ! $user->isDeleted() || ! AccountDeletion::showsPanel($user->deleted_reason)) {
            return redirect('/');
        }

        $locale = $user->mailLocale();
        App::setLocale($locale);

        return view('auth.account-deleted', [
            'locale' => $locale,
            'reason' => AccountDeletion::reasonMessage($user->deleted_reason, $locale),
            'other' => $user->deleted_reason === 'other' ? $user->deleted_reason_other : null,
            'date' => $user->deleted_at?->timezone(\App\Support\BusinessClock::timezone())->locale($locale)->translatedFormat('F j, Y'),
        ]);
    }
}
