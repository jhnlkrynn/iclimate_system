<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Signing up (web and mobile): the email must not belong to any account. One that belongs to a deleted account is
 * told so, and to ask the MAO to restore it, without saying why it was closed; any other taken email gets the usual
 * "already taken". Deleted accounts keep their email so they can be restored (App\Services\Accounts\AccountDeletion).
 */
class RegistrationEmailAvailable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = mb_strtolower(trim((string) $value));
        $owner = User::withDeleted()->whereRaw('LOWER(email) = ?', [$email])->first(['id', 'status']);
        if ($owner === null) {
            return;
        }

        $owner->isDeleted()
            ? $fail(__('user_deletion.email_closed'))
            : $fail(__('validation.unique', ['attribute' => 'email']));
    }
}
