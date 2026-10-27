<?php

namespace App\Policies;

use App\Models\RiceVariety;
use App\Models\User;

/**
 * Rice Varieties: farmers, MAO personnel and IT Personnel read the catalog; only IT Personnel add, edit and switch a
 * variety on or off (no deleting: planting plans point to these rows).
 */
class RiceVarietyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT], true);
    }

    public function view(User $user, RiceVariety $variety): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        // An inactive variety: staff still see it; a farmer only when one of their plans uses it.
        return $variety->is_active || $user->role !== User::ROLE_FARMER || $this->plantedBy($user, $variety);
    }

    public function create(User $user): bool
    {
        return $user->role === User::ROLE_IT_EXPERT;
    }

    public function update(User $user, RiceVariety $variety): bool
    {
        return $user->role === User::ROLE_IT_EXPERT;
    }

    public function toggle(User $user, RiceVariety $variety): bool
    {
        return $user->role === User::ROLE_IT_EXPERT;
    }

    /** An inactive variety stays readable to the farmers whose plans use it. */
    private function plantedBy(User $user, RiceVariety $variety): bool
    {
        return $variety->plantingSchedules()->where('user_id', $user->id)->exists();
    }
}
