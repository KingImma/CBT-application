<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword
{
    public function execute(Authenticatable $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);

        $user->tokens()->delete();
    }
}
