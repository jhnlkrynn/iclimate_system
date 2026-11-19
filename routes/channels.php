<?php

use App\Models\Conversation;
use App\Models\Farm;
use App\Models\User;
use App\Support\LianBarangays;
use App\Support\MaoScope;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, int|string $userId): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    return (int) $user->getKey() === (int) $userId;
});

Broadcast::channel('farm.{farmId}', function (User $user, int|string $farmId): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    $farm = Farm::query()->find($farmId);

    if ($farm === null) {
        return false;
    }

    return (int) $farm->user_id === (int) $user->getKey()
        || ($user->isMao() && MaoScope::canManageBarangay($farm->barangay, $user));
});

Broadcast::channel('conversation.{conversationId}', function (User $user, int|string $conversationId): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    $conversation = Conversation::query()->find($conversationId);

    return $conversation !== null
        && in_array((int) $user->getKey(), [
            (int) $conversation->participant_one_id,
            (int) $conversation->participant_two_id,
        ], true);
});

Broadcast::channel('mao', function (User $user): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    return $user->role === User::ROLE_MAO;
});


Broadcast::channel('dashboard.{role}', function (User $user, string $role): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    $roles = [
        'farmer' => User::ROLE_FARMER,
        'mao' => User::ROLE_MAO,
    ];

    return isset($roles[$role]) && $user->role === $roles[$role];
});

Broadcast::channel('barangay.{barangay}', function (User $user, string $barangay): bool {
    if (! $user->hasApplicationAccess() || $user->must_change_password) {
        return false;
    }

    $requested = LianBarangays::canonicalName($barangay);

    if ($requested === null) {
        return false;
    }

    if ($user->isMao()) {
        $covered = MaoScope::barangays($user);

        return $covered === null || in_array($requested, $covered, true);
    }

    return $user->role === User::ROLE_FARMER
        && $user->registeredBarangay() === $requested;
});
