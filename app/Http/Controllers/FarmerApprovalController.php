<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 1: Farmer Approvals (MAO). Farmer sign-ups wait as Pending until the MAO
 * approves them (they can then log in) or rejects them with a reason. Each decision is in the System Logs.
 */
class FarmerApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), ['pending', 'approved', 'rejected'], true) ? $request->query('show') : 'pending';
        $barangay = in_array($request->query('barangay'), LianBarangays::all(), true) ? $request->query('barangay') : null;

        $query = User::query()
            ->where('role', User::ROLE_FARMER)
            ->with('farmerProfile:id,user_id,barangay,farm_type,farm_area,full_name,contact_number')
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay));

        $query = match ($show) {
            'approved' => $query->where('status', User::STATUS_ACTIVE)->whereNotNull('approved_at')->latest('approved_at'),
            'rejected' => $query->where('status', User::STATUS_REJECTED)->latest('updated_at'),
            default => $query->where('status', User::STATUS_PENDING)->oldest('created_at'),
        };

        return view('mao.farmer-approvals', [
            'show' => $show,
            'barangay' => $barangay,
            'barangays' => LianBarangays::all(),
            'users' => $query->paginate(CrudController::PER_PAGE)->withQueryString(),
            'pendingCount' => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_PENDING)->count(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->ensureFarmerSignUp($user);

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'approved_at' => now(),
            'approved_by' => $request->user()->getKey(),
            'rejection_reason' => null,
        ])->save();

        Notification::query()->create([
            'user_id' => $user->getKey(),
            'title' => 'Account approved',
            'message' => 'Welcome to iClimate! The Municipal Agriculture Office approved your account.',
            'type' => 'Announcement',
            'category' => 'account',
            'is_read' => false,
        ]);
        SystemAuditLogger::record('Approved Farmer Account', $request, ['record_type' => 'User', 'record_id' => $user->getKey()]);

        return back()->with('success', $user->name.' can now log in.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->ensureFarmerSignUp($user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $user->forceFill([
            'status' => User::STATUS_REJECTED,
            'approved_at' => null,
            'approved_by' => $request->user()->getKey(),
            'rejection_reason' => $data['reason'],
        ])->save();
        SystemAuditLogger::record('Rejected Farmer Account', $request, ['record_type' => 'User', 'record_id' => $user->getKey(), 'reason' => $data['reason']]);

        return back()->with('success', $user->name.'\'s registration was rejected.');
    }

    /** Only a farmer's own sign-up is decided here; staff accounts are IT's (User Management). */
    private function ensureFarmerSignUp(User $user): void
    {
        abort_unless($user->role === User::ROLE_FARMER && in_array($user->status, [User::STATUS_PENDING, User::STATUS_REJECTED], true), 404);
    }
}
