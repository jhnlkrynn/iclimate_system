<?php

namespace App\Http\Controllers;

use App\Models\BarangayProfile;
use App\Models\MaoUserBarangay;
use App\Models\User;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Panel revision 4.4: MAO personnel accounts. Every MAO user can see who covers which barangay; only the Municipal
 * Agriculturist (head) adds accounts, edits positions, area covered and barangay assignments.
 * Also the assigned / municipal view switch of the field personnel (App\Support\MaoScope).
 */
class MaoPersonnelController extends Controller
{
    public function index(Request $request): View
    {
        $personnel = User::query()->where('role', User::ROLE_MAO)->with('maoBarangays')
            ->orderByDesc('is_mao_head')->orderBy('name')->get();

        $assigned = $personnel->flatMap(fn (User $u) => $u->maoBarangays->pluck('barangay'))->unique();

        return view('mao.personnel', [
            'personnel' => $personnel,
            'canManage' => $request->user()->isMaoHead(),
            'barangays' => LianBarangays::all(),
            'unassigned' => collect(LianBarangays::all())->diff($assigned)->values(),
            'stationTypes' => User::STATION_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHead($request);
        $data = $request->validate($this->rules() + [
            // Optional: left blank, it is made from the name (firstnamelastname@gmail.com, with 2, 3 … when taken).
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            // Optional: left blank, the account gets the default MAO password and must change it at the first sign-in.
            'password' => ['nullable', 'string', Password::defaults()],
        ]);
        $data['email'] = filled($data['email'] ?? null) ? strtolower($data['email']) : User::suggestMaoEmail($data['name']);
        $usesDefault = blank($data['password'] ?? null);
        $data['password'] = $usesDefault ? (string) config('iclimate.mao_default_password', 'Password@123') : $data['password'];

        $user = DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => User::ROLE_MAO,
                'position' => $data['position'],
                'station_type' => $data['station_type'],
                'area_covered_ha' => $data['area_covered_ha'] ?? null,
                'is_mao_head' => false,
                'address' => 'Municipal Agriculture Office, Lian, Batangas',
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make($data['password']),
                'must_change_password' => true,   // the person chooses their own password at the first sign-in
            ]);
            $user->email_verified_at = now();
            $user->save();
            $this->syncBarangays($user, $data['barangays'] ?? [], $data['sitios'] ?? []);

            return $user;
        });

        SystemAuditLogger::record('Created MAO account', $request, ['record_type' => 'MAO Personnel', 'record_id' => $user->id, 'record_label' => $user->maoTitle()]);

        return redirect()->route('mao.personnel.index')->with('success', $user->name.' can now sign in with '.$user->email
            .($usesDefault ? ' and the default password' : '').'. They will be asked to change the password at the first sign-in.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeHead($request);
        abort_unless($user->isMao(), 404);
        $data = $request->validate($this->rules() + [
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);
        // The head cannot lock themself out by deactivating their own account here.
        if ($user->is($request->user())) {
            $data['status'] = User::STATUS_ACTIVE;
        }

        $before = $user->coverageLabel();
        DB::transaction(function () use ($user, $data): void {
            $user->fill([
                'name' => $data['name'],
                'position' => $data['position'],
                'station_type' => $data['station_type'],
                'area_covered_ha' => $data['area_covered_ha'] ?? null,
                'status' => $data['status'],
            ])->save();
            if (! $user->isMaoHead()) {
                $this->syncBarangays($user, $data['barangays'] ?? [], $data['sitios'] ?? []);
            }
        });
        $user->load('maoBarangays');

        $action = $before !== $user->coverageLabel() ? 'Reassigned barangays of' : 'Updated MAO account of';
        SystemAuditLogger::record($action.' '.$user->name, $request, ['record_type' => 'MAO Personnel', 'record_id' => $user->id, 'record_label' => $user->maoTitle().' · '.$user->coverageLabel()]);

        return redirect()->to(route('mao.personnel.index').'#mao-'.$user->id)->with('success', $user->name.' saved.');
    }

    /** The Municipal Agriculturist puts an MAO account back on the default password (to be changed at the next sign-in). */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeHead($request);
        abort_unless($user->isMao(), 404);

        $user->forceFill([
            'password' => Hash::make((string) config('iclimate.mao_default_password', 'Password@123')),
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();
        SystemAuditLogger::record('Reset password of '.$user->name.' to the default', $request, ['record_type' => 'MAO Personnel', 'record_id' => $user->id, 'record_label' => $user->maoTitle()]);

        return redirect()->to(route('mao.personnel.index').'#mao-'.$user->id)
            ->with('success', $user->name."'s password is back to the default. They must change it at the next sign-in.");
    }

    /** Field personnel: switch between "My barangays" and the read-only municipal view. */
    public function switchView(Request $request): RedirectResponse
    {
        abort_unless(MaoScope::canSwitch($request->user()), 403);
        $view = $request->validate(['view' => ['required', Rule::in([MaoScope::VIEW_ASSIGNED, MaoScope::VIEW_MUNICIPAL])]])['view'];
        $request->session()->put(MaoScope::SESSION_KEY, $view);

        return redirect()->back(302, [], route('mao.dashboard'));
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:160'],
            'station_type' => ['required', Rule::in(User::STATION_TYPES)],
            'area_covered_ha' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'barangays' => ['nullable', 'array'],
            'barangays.*' => ['string', Rule::in(LianBarangays::all())],
            'sitios' => ['nullable', 'array'],
            'sitios.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @param  array<int, string>  $barangays
     * @param  array<string, ?string>  $sitios  barangay => sitio (blank = the whole barangay)
     */
    private function syncBarangays(User $user, array $barangays, array $sitios): void
    {
        MaoUserBarangay::query()->where('user_id', $user->id)->delete();
        foreach (array_unique($barangays) as $barangay) {
            $sitio = trim((string) ($sitios[$barangay] ?? '')) ?: null;
            MaoUserBarangay::query()->create([
                'user_id' => $user->id,
                'barangay' => $barangay,
                'sitio' => $sitio,
                'barangay_profile_id' => BarangayProfile::query()->where('barangay', $barangay)->value('id'),
            ]);
        }
        // The signed-in user is cached between requests: their next request must see the new coverage.
        \App\Auth\CachedEloquentUserProvider::forget($user->id);
        User::forgetMaoPersonnel();
    }

    private function authorizeHead(Request $request): void
    {
        abort_unless($request->user()->isMaoHead(), 403, 'Only the Municipal Agriculturist can manage MAO accounts.');
    }
}
