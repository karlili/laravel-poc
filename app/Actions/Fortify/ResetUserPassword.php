<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($user) {
            if ($user->isLinkedToEntra() && config('crm.sso.enforce_for_linked_users')) {
                $validator->errors()->add('email', __('This account signs in with Microsoft. Reset your password with your organisation instead.'));
            }
        })->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
