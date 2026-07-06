<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ChangePassword
{
    public function execute(
        Authenticatable $user,
        string $currentPassword,
        string $newPassword,
    ): void {
        $user->forceFill(["password" => Hash::make($newPassword)])->save();

        /** @var User|\App\Models\Tenant\User $user */
        $recipient = config("mail.override_address") ?: $user->email;
        Mail::to($recipient)->queue(
            new PasswordChangedMail(
                firstName: $user->first_name,
                schoolName: tenant("name") ?? "EduCBT",
            ),
        );
    }
}
