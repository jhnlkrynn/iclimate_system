<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The farmer dashboard's "Add Farm Size" and "Update Profile" pop-ups. Both change the signed-in farmer's own records
 * (never an id sent by the browser) and answer in JSON so the dashboard stays on screen.
 */
class FarmerDashboardSetupController extends Controller
{
    /**
     * Farm size lives in one place: farm_area on the farmer's profile. Opened as "Farm Information" the pop-up also
     * sends the farm type, kept on the same profile record; left unchosen, the saved one stays.
     */
    public function farmSize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'farm_area' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,2'],
            'farm_type' => ['nullable', 'string', Rule::in(FarmerPlantingSchedule::FARM_TYPES)],
        ], [
            'farm_area.decimal' => __('farmer.popup.size.decimals'),
            'farm_area.*' => __('farmer.popup.size.invalid'),
            'farm_type.*' => __('farmer.popup.size.farm_type_invalid'),
        ]);

        try {
            DB::transaction(function () use ($request, $data) {
                $this->profileFor($request->user())->update(array_filter(['farm_area' => $data['farm_area'], 'farm_type' => $data['farm_type'] ?? null]));

                // A current plan that has no size yet takes this one; a size the farmer set on a plan is left alone.
                $plan = FarmerPlantingScheduleController::currentSchedule($request->user());
                if ($plan instanceof FarmerPlantingSchedule && $plan->farm_area_hectares === null) {
                    $plan->update(['farm_area_hectares' => $data['farm_area']]);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('farmer.popup.failed')], 500);
        }

        return response()->json([
            // Sent with a farm type field, it was the "Farm Information" form.
            'message' => $request->has('farm_type') ? __('farmer.popup.size.saved_info') : __('farmer.popup.size.saved'),
            'values' => ['farm_area' => number_format((float) $data['farm_area'], 2, '.', ''), 'farm_type' => (string) $request->user()->farmerProfile()->value('farm_type')],
        ]);
    }

    /**
     * The account details (the same fields the Profile page accepts) plus the farm type, which the dashboard asks for.
     * The farmer's profile record is kept in step, because the dashboard reads the barangay and farm type from it.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $t = fn (string $key) => __('farmer.popup.profile.'.$key);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s\-()]{6,19}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'barangay' => ['nullable', 'string', Rule::in(LianBarangays::all())],
            'farm_type' => ['nullable', 'string', Rule::in(FarmerPlantingSchedule::FARM_TYPES)],
        ], [
            'name.*' => $t('name_required'),
            'email.unique' => $t('email_taken'),
            'email.*' => $t('email_invalid'),
            'contact_number.*' => $t('contact_invalid'),
            'address.*' => $t('address_long'),
            'barangay.*' => $t('barangay_invalid'),
            'farm_type.*' => $t('farm_type_invalid'),
        ]);

        try {
            DB::transaction(function () use ($user, $data) {
                $user->fill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'contact_number' => $data['contact_number'] ?? null,
                    'address' => $data['address'] ?? null,
                    'barangay' => $data['barangay'] ?? null,
                ]);
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;                    // as on the Profile page
                }
                $user->save();

                $profile = $user->farmerProfile;
                $details = array_filter([
                    'full_name' => $data['name'],
                    'barangay' => $data['barangay'] ?? null,             // required on the profile record: an empty choice keeps the saved one
                    'farm_type' => $data['farm_type'] ?? null,           // likewise
                ]) + ['contact_number' => $data['contact_number'] ?? null, 'address' => $data['address'] ?? null];

                if ($profile) {
                    $profile->update($details);
                } elseif (! empty($data['barangay'])) {
                    $user->farmerProfile()->create($details + ['farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED]);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('farmer.popup.failed')], 500);
        }

        $user->refresh();
        $profile = $user->farmerProfile()->first();

        return response()->json([
            'message' => $t('saved'),
            'values' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => (string) $user->contact_number,
                'address' => (string) $user->address,
                'barangay' => (string) ($profile?->barangay ?? $user->barangay),
                'farm_type' => (string) $profile?->farm_type,
            ],
        ]);
    }

    /** The farmer's one profile record, started from the account details when there is none yet (as the farm map page does). */
    private function profileFor(User $user): FarmerProfile
    {
        return $user->farmerProfile()->firstOrCreate([], [
            'full_name' => $user->name,
            'contact_number' => $user->contact_number,
            'address' => $user->address,
            'barangay' => $user->barangay ?: 'Poblacion',
            'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
        ]);
    }
}
