<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ResetUserPassword
{
    public function execute(Authenticatable $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);

        $user->tokens()->delete();

        /** @var User|\App\Models\Tenant\User $user */
        Mail::to($user->email)->send(new PasswordChangedMail(
            firstName: $user->first_name,
            schoolName: tenant('name') ?? 'EduCBT',
        ));
    }
}
