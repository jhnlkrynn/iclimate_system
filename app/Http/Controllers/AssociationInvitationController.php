<?php

namespace App\Http\Controllers;

use App\Models\AssociationChairmanAssignment;
use App\Models\AssociationChairmanInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AssociationInvitationController extends Controller
{
    private function invitation(string $token): AssociationChairmanInvitation
    {
        $invitation = AssociationChairmanInvitation::query()->where('token_hash', hash('sha256', $token))->with('assignment.association')->firstOrFail();
        abort_if($invitation->accepted_at || $invitation->expires_at->isPast()
            || $invitation->assignment->status !== AssociationChairmanAssignment::STATUS_PENDING, 410);

        return $invitation;
    }

    public function show(string $token)
    {
        return view('association-chairman.invitation', ['token' => $token, 'invitation' => $this->invitation($token)]);
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed', new \App\Rules\NotKnownDefaultPassword]]);
        DB::transaction(function () use ($token, $data): void {
            $invitation = AssociationChairmanInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            $assignment = $invitation->assignment()->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at || $invitation->expires_at->isPast()
                || $assignment->status !== AssociationChairmanAssignment::STATUS_PENDING, 410);
            $user = $assignment->user()->lockForUpdate()->firstOrFail();
            abort_unless($user->role === User::ROLE_FARMER && in_array($user->status, [User::STATUS_PENDING, User::STATUS_ACTIVE, User::STATUS_APPROVED], true), 403);
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(), 'must_change_password' => false,
            ])->save();
            $invitation->update(['accepted_at' => now()]);
            $assignment->update(['contact_verified_at' => now()]);
        });

        return redirect()->route('login')->with('status', 'Your password is set. MAO Staff must approve your Farmer account and activate your association assignment before reporting access.');
    }
}
