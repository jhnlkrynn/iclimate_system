<?php

namespace App\Http\Controllers;

use App\Models\AssociationReminderDelivery;
use App\Models\AssociationReportingPeriod;
use App\Models\AssociationYieldReport;
use App\Models\AssociationYieldReportReview;
use App\Models\FarmerOrganization;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MaoAssociationReportController extends Controller
{
    public function index(Request $request)
    {
        // Every period can be opened (?period=); the latest deadline is the default. Before this, only the latest period
        // was listed, so reports for an earlier or overdue period could not be reached from this page.
        $periods = AssociationReportingPeriod::query()->orderByDesc('due_at')->orderByDesc('id')->get(['id', 'label', 'due_at']);
        $period = $periods->firstWhere('id', $request->integer('period')) ?? $periods->first();
        $associations = FarmerOrganization::query()->with([
            'yieldReports' => fn ($q) => $period ? $q->where('reporting_period_id', $period->id) : $q->whereRaw('1 = 0'),
            'chairmanAssignments' => fn ($q) => $q->where('status', \App\Models\AssociationChairmanAssignment::STATUS_ACTIVE)->with('user:id,name'),
        ])->orderBy('barangay')->orderBy('name')->get();
        return view('mao.association-reports.index', compact('associations', 'period', 'periods'));
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate(['period_key' => ['required', 'string', 'max:80', 'unique:association_reporting_periods,period_key'], 'label' => ['required', 'string', 'max:160'], 'reporting_year' => ['required', 'integer', 'min:2000', 'max:2200'], 'season' => ['nullable', 'string', 'max:32'], 'opens_at' => ['nullable', 'date'], 'due_at' => ['required', 'date'], 'required' => ['boolean']]);
        $data['created_by'] = Auth::id();
        try {
            AssociationReportingPeriod::query()->create($data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            // A concurrent submission can win after the validation query.
            throw \Illuminate\Validation\ValidationException::withMessages(['period_key' => 'This reporting period already exists.']);
        }
        return back()->with('status', 'Reporting period created.');
    }

    public function invite(Request $request, FarmerOrganization $association)
    {
        abort_unless(\App\Support\MaoScope::canManageBarangay($association->barangay, $request->user()), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:255', 'unique:users,email']]);
        $user = User::query()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(Str::random(64)), 'role' => User::ROLE_FARMER, 'status' => User::STATUS_PENDING, 'must_change_password' => true]);
        $association->chairmanAssignments()->where('status', 'active')->update(['status' => 'revoked', 'reassigned_at' => now()]);
        $assignment = $association->chairmanAssignments()->create(['user_id' => $user->id, 'status' => 'pending', 'assigned_by' => Auth::id()]);
        $token = Str::random(64);
        \App\Models\AssociationChairmanInvitation::query()->create(['association_chairman_assignment_id' => $assignment->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'invited_by' => Auth::id()]);
        return back()->with('status', 'Invitation prepared. Share this one-time link only after contact verification: '.route('association-chairman.invitation.show', $token));
    }

    public function assign(Request $request, FarmerOrganization $association)
    {
        abort_unless(\App\Support\MaoScope::canManageBarangay($association->barangay, $request->user()), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $association, $request): void {
            $user = User::query()->lockForUpdate()->findOrFail($data['user_id']);
            abort_unless($user->role === User::ROLE_FARMER && $user->hasApplicationAccess(), 422);
            $association->newQuery()->whereKey($association->id)->lockForUpdate()->firstOrFail();
            \App\Models\AssociationChairmanAssignment::query()
                ->where(function ($query) use ($association, $user): void {
                    $query->where('farmer_organization_id', $association->id)->orWhere('user_id', $user->id);
                })->whereIn('status', ['active', 'pending'])
                ->update(['status' => 'revoked', 'reassigned_at' => now()]);
            $association->chairmanAssignments()->create([
                'user_id' => $user->id, 'status' => 'active', 'assigned_by' => $request->user()->id,
            ]);
            \App\Services\SystemAuditLogger::record('Assigned association chairman', $request, ['association_id' => $association->id, 'user_id' => $user->id]);
        });

        return back()->with('status', 'Farmer assigned as association chairman.');
    }

    public function show(AssociationYieldReport $report)
    {
        return view('mao.association-reports.show', ['report' => $report->load(['association', 'reportingPeriod', 'reviews.reviewer', 'submissions.submitter'])]);
    }

    public function review(Request $request, AssociationYieldReport $report)
    {
        abort_unless(\App\Support\MaoScope::canManageBarangay($report->association->barangay, $request->user()), 403);
        $data = $request->validate(['action' => ['required', 'in:under_review,accept,needs_revision'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $status = $data['action'] === 'accept' ? AssociationYieldReport::ACCEPTED : ($data['action'] === 'needs_revision' ? AssociationYieldReport::NEEDS_REVISION : AssociationYieldReport::UNDER_REVIEW);
        if ($status === AssociationYieldReport::NEEDS_REVISION && trim((string) ($data['notes'] ?? '')) === '') return back()->withErrors(['notes' => 'Revision notes are required.']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($report, $status, $data): void {
            $current = AssociationYieldReport::query()->lockForUpdate()->findOrFail($report->id);
            $last = $current->reviews()->latest('id')->first();
            if ($current->status === $status && $last?->action === $data['action']
                && (string) $last->notes === (string) ($data['notes'] ?? '')) {
                return; // A retried identical review creates no second review or notification.
            }
            abort_unless(in_array($current->status, [AssociationYieldReport::SUBMITTED, AssociationYieldReport::UNDER_REVIEW], true), 409, 'This report is not awaiting this review. Refresh its status.');
            $current->update(['status' => $status, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);
            $current->reviews()->create(['reviewer_id' => Auth::id(), 'action' => $data['action'], 'notes' => $data['notes'] ?? null]);
            if ($current->submitter) Notification::query()->create(['user_id' => $current->submitter->id, 'title' => 'Rice-yield report update', 'message' => 'Your '.$current->reportingPeriod->label.' report is '.$status.'.', 'type' => 'Announcement', 'category' => 'association_reporting']);
        });
        return back()->with('status', 'Review recorded.');
    }

    public function remind(Request $request, AssociationYieldReport $report)
    {
        abort_unless(\App\Support\MaoScope::canManageBarangay($report->association->barangay, $request->user()), 403);
        $assignment = $report->association->chairmanAssignments()->where('status', 'active')->whereHas('user', fn ($users) => $users->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE))->latest()->first();
        abort_unless($assignment, 422);
        $key = 'manual:'.$report->farmer_organization_id.':'.$report->reporting_period_id.':'.now()->toDateString();
        if (AssociationReminderDelivery::query()->firstOrCreate(['delivery_key' => $key], ['farmer_organization_id' => $report->farmer_organization_id, 'reporting_period_id' => $report->reporting_period_id, 'recipient_user_id' => $assignment->user_id, 'kind' => 'manual', 'sent_at' => now()])->wasRecentlyCreated) Notification::query()->create(['user_id' => $assignment->user_id, 'title' => 'Rice-yield report reminder', 'message' => 'Please complete the required report for '.$report->reportingPeriod->label.'.', 'type' => 'Warning', 'category' => 'association_reporting']);
        return back()->with('status', 'Reminder recorded.');
    }
}
