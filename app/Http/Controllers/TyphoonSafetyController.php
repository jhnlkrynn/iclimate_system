<?php

namespace App\Http\Controllers;

use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use App\Services\TyphoonSafetyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TyphoonSafetyController extends Controller
{
    public function store(Request $request, TyphoonSafetyService $safetyService): RedirectResponse
    {
        abort_unless($request->user()->role === User::ROLE_FARMER, 403);

        $event = $safetyService->activeEvent();

        if (! $event || $request->input('event_key') !== $event['key']) {
            return back()->with('error', 'No active typhoon safety check is available right now.');
        }

        if (! Schema::hasTable('typhoon_safety_responses')) {
            return back()->with('error', 'Safety check responses are temporarily unavailable. Please try again later.');
        }

        $data = $request->validate([
            'event_key' => ['required', 'string'],
            'status' => ['required', 'in:'.TyphoonSafetyResponse::STATUS_SAFE.','.TyphoonSafetyResponse::STATUS_NEEDS_HELP],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        TyphoonSafetyResponse::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'event_key' => $event['key'],
            ],
            [
                'event_title' => $event['title'],
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'responded_at' => now(),
            ]
        );

        return back()->with('success', $data['status'] === TyphoonSafetyResponse::STATUS_SAFE
            ? 'Safety status sent. MAO can now see that you are safe.'
            : 'Help request sent. MAO can now see that you may need assistance.');
    }
}
