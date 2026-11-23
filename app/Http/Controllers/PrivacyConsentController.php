<?php

namespace App\Http\Controllers;

use App\Services\SystemAuditLogger;
use App\Support\SchemaCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyConsentController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->privacy_agreed_at !== null) {
            return redirect()->route($user->dashboardRoute());
        }

        return view('privacy-consent');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'privacy_agreed' => ['required', 'accepted'],
        ], [
            'privacy_agreed.required' => __('privacy.agree_required'),
            'privacy_agreed.accepted' => __('privacy.agree_required'),
        ]);

        $user = $request->user();
        if (SchemaCache::hasColumn('users', 'privacy_agreed_at')) {
            $user->forceFill(['privacy_agreed_at' => now()])->save();
        }
        SystemAuditLogger::record('Agreed to Data Privacy Notice', $request);

        return redirect()->intended(route($user->dashboardRoute()));
    }
}
