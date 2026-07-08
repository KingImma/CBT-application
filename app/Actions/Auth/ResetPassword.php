<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ResetPassword
{
    public function __construct(private DecodeResetToken $decodeResetToken) {}

    public function execute(string $resetToken, string $newPassword): void
    {
        $email = $this->decodeResetToken->execute($resetToken);

        $userModel = tenant() ? \App\Models\Tenant\User::class : User::class;
        $user = $userModel::where('email', $email)->firstOrFail();

        $user->update(['password' => Hash::make($newPassword)]);

        $user->tokens()->delete();

        $recipient = config('mail.override_address') ?: $user->email;
        Mail::to($recipient)->queue(
            new PasswordChangedMail(
                firstName: $user->first_name,
                schoolName: tenant('name') ?? 'EduCBT',
            ),
        );
    }
}
