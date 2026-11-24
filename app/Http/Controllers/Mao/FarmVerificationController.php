<?php

namespace App\Http\Controllers\Mao;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CrudController;
use App\Models\Farm;
use App\Models\User;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Farms\FarmService;
use App\Services\Farms\FarmVerificationNotifier;
use App\Services\SystemAuditLogger;
use App\Support\ActiveFarm;
use App\Support\CacheScope;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use App\Support\SchemaCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmVerificationController extends Controller
{
    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()?->isMao() && $request->user()->hasApplicationAccess(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeStaff($request);

        $show = in_array($request->query('show'), Farm::VERIFICATION_STATUSES, true)
            ? (string) $request->query('show')
            : Farm::VERIFICATION_PENDING;

        $scopedBarangays = MaoScope::barangays($request->user());
        $allowedBarangays = $scopedBarangays ?? LianBarangays::all();
        $barangay = in_array($request->query('barangay'), $allowedBarangays, true) ? (string) $request->query('barangay') : null;
        $search = mb_substr(trim((string) $request->query('search')), 0, 120);

        $baseQuery = Farm::query()
            ->active()
            ->when($scopedBarangays !== null, fn ($q) => $q->whereIn('farms.barangay', $scopedBarangays))
            ->when($barangay !== null, fn ($q) => $q->where('farms.barangay', $barangay))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('farms.farm_name', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            });

        $listQuery = (clone $baseQuery)
            ->with(['user.farmerProfile', 'organization', 'boundary', 'verifier:id,name']);

        if (SchemaCache::hasColumn('farms', 'verification_status')) {
            $listQuery = match ($show) {
                Farm::VERIFICATION_APPROVED => $listQuery->where('verification_status', Farm::VERIFICATION_APPROVED)->latest('verified_at'),
                Farm::VERIFICATION_REJECTED => $listQuery->where('verification_status', Farm::VERIFICATION_REJECTED)->latest('verified_at'),
                default => $listQuery->where('verification_status', Farm::VERIFICATION_PENDING)->oldest('created_at'),
            };
        }

        $pendingCount = SchemaCache::hasColumn('farms', 'verification_status')
            ? Farm::query()
                ->active()
                ->where('verification_status', Farm::VERIFICATION_PENDING)
                ->when($scopedBarangays !== null, fn ($q) => $q->whereIn('farms.barangay', $scopedBarangays))
                ->count()
            : 0;

        $farms = $listQuery->paginate(CrudController::PER_PAGE)->withQueryString();

        $userIds = $farms->getCollection()->pluck('user_id')->unique()->filter()->values()->all();
        $farmCountsByUser = $userIds !== []
            ? Farm::query()->active()->whereIn('user_id', $userIds)->selectRaw('user_id, count(*) as total')->groupBy('user_id')->pluck('total', 'user_id')
            : collect();

        return view('mao.farm-verification', [
            'show' => $show,
            'barangay' => $barangay,
            'search' => $search,
            'barangays' => $allowedBarangays,
            'farms' => $farms,
            'pendingCount' => $pendingCount,
            'farmCountsByUser' => $farmCountsByUser,
            'isFiltered' => $scopedBarangays !== null,
        ]);
    }

    public function approve(Request $request, Farm $farm, FarmService $farmService, FarmVerificationNotifier $notifier): RedirectResponse
    {
        $this->authorizeStaff($request);
        abort_unless($farm->archived_at === null, 404);
        abort_unless(MaoScope::canManageBarangay($farm->barangay, $request->user()), 403);

        $farm->forceFill([
            'verification_status' => Farm::VERIFICATION_APPROVED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        if ($farm->user instanceof User) {
            ActiveFarm::forget($farm->user);
            if ($farm->is_primary) {
                $farmService->mirrorToProfile($farm->user);
            }
            FarmerDashboardService::forgetCached($farm->user);
        }
        CacheScope::changed(Farm::class);

        rescue(fn () => $notifier->approved($farm), null, false);

        SystemAuditLogger::record('Approved Farm', $request, [
            'record_type' => 'Farm',
            'record_id' => $farm->id,
            'user_id' => $farm->user_id,
            'farm_name' => $farm->farm_name,
            'barangay' => $farm->barangay,
        ]);

        return back()->with('success', "Farm \"{$farm->farm_name}\" has been approved.");
    }

    public function reject(Request $request, Farm $farm, FarmVerificationNotifier $notifier): RedirectResponse
    {
        $this->authorizeStaff($request);
        abort_unless($farm->archived_at === null, 404);
        abort_unless(MaoScope::canManageBarangay($farm->barangay, $request->user()), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $reason = trim($validated['reason']);

        $farm->forceFill([
            'verification_status' => Farm::VERIFICATION_REJECTED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        if ($farm->user instanceof User) {
            ActiveFarm::forget($farm->user);
            FarmerDashboardService::forgetCached($farm->user);
        }
        CacheScope::changed(Farm::class);

        rescue(fn () => $notifier->rejected($farm, $reason), null, false);

        SystemAuditLogger::record('Rejected Farm', $request, [
            'record_type' => 'Farm',
            'record_id' => $farm->id,
            'user_id' => $farm->user_id,
            'farm_name' => $farm->farm_name,
            'barangay' => $farm->barangay,
            'reason' => $reason,
        ]);

        return back()->with('success', "Farm \"{$farm->farm_name}\" was rejected.");
    }
}
