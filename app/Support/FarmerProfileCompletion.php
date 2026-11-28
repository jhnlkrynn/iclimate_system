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
            $farms = ActiveFarm::farms($user);
            if ($farms->isEmpty()) {
                $missing[] = 'farm';
                if ($user->farmerProfile?->farmer_organization_id === null) {
                    $missing[] = 'association';
                }
            } else {
                $hasAssociationChoice = $farms->contains(
                    fn ($farm): bool => $farm->farmer_organization_id !== null
                        || in_array($farm->association_status, ['member', 'not_member', 'pending_other'], true)
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

    /**
     * Human-readable labels for only the fields that are actually missing.
     *
     * @return list<string>
     */
    public static function missingLabels(User $user): array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return [];
        }

        $fil = app()->getLocale() === 'fil';
        $labels = [];

        if ($user->registeredBarangay() === null) {
            $labels[] = $fil ? 'Barangay: hindi pa nakatala' : 'Barangay: not set yet';
        }

        $contact = trim((string) ($user->contact_number ?: $user->farmerProfile?->contact_number));
        if ($contact === '') {
            $labels[] = $fil ? 'Numero ng telepono: hindi pa nakatala' : 'Contact number: not set yet';
        }

        if (ActiveFarm::available()) {
            $farms = ActiveFarm::farms($user);
            if ($farms->isEmpty()) {
                $labels[] = $fil ? 'Walang bukid na nakatala pa' : 'No farm added yet';
            } else {
                $unanswered = 0;
                foreach ($farms as $farm) {
                    $answered = $farm->farmer_organization_id !== null
                        || in_array($farm->association_status, ['member', 'not_member', 'pending_other'], true);
                    if (! $answered) {
                        $unanswered++;
                    }
                }
                if ($unanswered > 0) {
                    $labels[] = $fil
                        ? ($unanswered === 1 ? '1 bukid ang kulang sa impormasyon ng samahan' : "{$unanswered} bukid ang kulang sa impormasyon ng samahan")
                        : ($unanswered === 1 ? '1 farm needs association info' : "{$unanswered} farms need association info");
                }
            }
        } elseif ($user->farmerProfile?->farmer_organization_id === null) {
            $labels[] = $fil ? 'Samahan: hindi pa nasasagot' : 'Association: not answered yet';
        }

        return $labels;
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
