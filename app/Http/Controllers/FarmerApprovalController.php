<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Accounts\FarmerAccountReviewService;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerApprovalController extends Controller
{
    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()?->isMao() && $request->user()->hasApplicationAccess(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeStaff($request);
        $show = in_array($request->query('show'), ['pending', 'approved', 'rejected'], true) ? $request->query('show') : 'pending';
        $barangay = in_array($request->query('barangay'), LianBarangays::all(), true) ? $request->query('barangay') : null;
        $search = mb_substr(trim((string) $request->query('search')), 0, 120);
        $query = User::query()->where('role', User::ROLE_FARMER)
            ->with(['farmerProfile', 'accountReviewer:id,name', 'approver:id,name'])
            ->when($barangay, fn ($q) => $q->where('barangay', $barangay))
            ->when($search !== '', fn ($q) => $q->where(fn ($terms) => $terms->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
        $query = match ($show) {
            'approved' => $query->where('status', User::STATUS_ACTIVE)->latest('approved_at'),
            'rejected' => $query->where('status', User::STATUS_REJECTED)->latest(FarmerAccountReviewService::available() ? 'reviewed_at' : 'updated_at'),
            default => $query->where('status', User::STATUS_PENDING)->oldest('created_at'),
        };

        return view('mao.farmer-approvals', [
            'show' => $show, 'barangay' => $barangay, 'search' => $search, 'barangays' => LianBarangays::all(),
            'users' => $query->paginate(CrudController::PER_PAGE)->withQueryString(),
            'pendingCount' => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_PENDING)->count(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->authorizeStaff($request);
        abort_unless($user->role === User::ROLE_FARMER, 404);
        $user->load(['farmerProfile', 'accountReviewer', 'approver']);
        // Before the review-history migration there is no history to show (the page still opens).
        FarmerAccountReviewService::available() ? $user->load('accountReviews.reviewer') : $user->setRelation('accountReviews', collect());

        return view('mao.farmer-account-request', ['farmer' => $user]);
    }

    public function approve(Request $request, User $user, FarmerAccountReviewService $reviews): RedirectResponse
    {
        $this->authorizeStaff($request);
        if (! FarmerAccountReviewService::available()) {
            return back()->with('error', FarmerAccountReviewService::NOT_READY_MESSAGE);
        }
        $data = $request->validate(['revision' => ['nullable', 'integer', 'min:0']]);
        $reviews->decide($request, $user->id, User::STATUS_APPROVED, (int) ($data['revision'] ?? 0));

        return back()->with('success', $user->name.' can now log in.');
    }

    public function reject(Request $request, User $user, FarmerAccountReviewService $reviews): RedirectResponse
    {
        $this->authorizeStaff($request);
        if (! FarmerAccountReviewService::available()) {
            return back()->with('error', FarmerAccountReviewService::NOT_READY_MESSAGE);
        }
        $data = $request->validate([
            'revision' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', Rule::in(array_keys((array) config('user_rejection.reasons', [])))],
            'note' => ['nullable', 'required_if:reason,other', 'string', 'max:400', ...($request->input('reason') === 'other' ? ['min:'.(int) config('user_rejection.other_min_length', 10)] : [])],
        ]);
        $reviews->decide($request, $user->id, User::STATUS_REJECTED, (int) ($data['revision'] ?? 0), $data['reason'], $data['note'] ?? null);

        return back()->with('success', $user->name.'\'s registration was rejected.');
    }

    /**
     * MAO-assisted account recovery: after checking the person, any active MAO Staff member gives them a one-time code
     * (AccountRecovery). The code is shown here once; the audit log records who, how they checked, and when.
     */
    public function recoveryCode(Request $request, User $user): RedirectResponse
    {
        $this->authorizeStaff($request);
        abort_unless($user->role === User::ROLE_FARMER && $user->hasApplicationAccess(), 404);
        $data = $request->validate([
            'verification_method' => ['required', Rule::in(array_keys(\App\Services\Accounts\AccountRecovery::VERIFICATION_METHODS))],
            'identity_confirmed' => ['accepted'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $code = \App\Services\Accounts\AccountRecovery::issueCode($user, $request->user(), $data['verification_method'], $data['note'] ?? null, $request);

        return back()->with('recovery_code', $code)
            ->with('success', 'Give this code only to '.$user->name.'. It works once, for '.(int) config('auth.passwords.users.expire', 60).' minutes, at '.route('password.reset', ['token' => 'code']).'.');
    }

    public function reconsider(Request $request, User $user, FarmerAccountReviewService $reviews): RedirectResponse
    {
        $this->authorizeStaff($request);
        if (! FarmerAccountReviewService::available()) {
            return back()->with('error', FarmerAccountReviewService::NOT_READY_MESSAGE);
        }
        $request->validate(['revision' => ['nullable', 'integer', 'min:0'], 'resubmission_note' => ['required', 'string', 'min:10', 'max:2000']]);
        $reviews->resubmit($request, $user->id);

        return back()->with('success', 'Application returned to Pending for a new review.');
    }
}
