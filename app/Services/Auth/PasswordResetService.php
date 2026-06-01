<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    public function resetPassword(string $resetToken, string $newPassword): void
    {
        $email = $this->otpService->decodeResetToken($resetToken);

        $userModel = tenant() ? \App\Models\Tenant\User::class : User::class;
        $user = $userModel::where('email', $email)->firstOrFail();

        $user->update(['password' => Hash::make($newPassword)]);

        $user->tokens()->delete();
    }

    public function resetPasswordForUser(Authenticatable $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);

        $user->tokens()->delete();
    }

    public function changePassword(Authenticatable $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }
}
