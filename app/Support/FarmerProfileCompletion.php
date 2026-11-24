<?php

namespace App\Support;

use App\Models\User;

final class FarmerProfileCompletion
{
    /**
     * Missing setup items for a farmer's post-login profile completion.
     *
     * @return list<string>
     */
    public static function missingItems(User $user): array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return [];
        }

        $missing = [];
        if ($user->registeredBarangay() === null) {
            $missing[] = 'barangay';
        }

        $contact = trim((string) ($user->contact_number ?: $user->farmerProfile?->contact_number));
        if ($contact === '') {
            $missing[] = 'contact_number';
        }

        if (ActiveFarm::available()) {
            $farms = $user->farms()->active()->get(['id', 'farmer_organization_id', 'association_status']);
            if ($farms->isEmpty()) {
                $missing[] = 'farm';
                if ($user->farmerProfile?->farmer_organization_id === null) {
                    $missing[] = 'association';
                }
            } else {
                $hasAssociationChoice = $farms->contains(
                    fn ($farm): bool => $farm->farmer_organization_id !== null
                        || in_array($farm->association_status, ['member', 'not_member'], true)
                ) || $user->farmerProfile?->farmer_organization_id !== null;
                if (! $hasAssociationChoice) {
                    $missing[] = 'association';
                }
            }
        } elseif ($user->farmerProfile?->farmer_organization_id === null) {
            $missing[] = 'association';
        }

        return $missing;
    }

    public static function isIncomplete(User $user): bool
    {
        return $user->role === User::ROLE_FARMER && self::missingItems($user) !== [];
    }

    public static function shouldShowBanner(User $user): bool
    {
        return self::isIncomplete($user) && ! session('complete_profile_banner_dismissed', false);
    }
}
