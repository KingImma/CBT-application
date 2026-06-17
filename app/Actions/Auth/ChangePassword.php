<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class ChangePassword
{
    public function execute(Authenticatable $user, string $currentPassword, string $newPassword): void
    {
        $user->forceFill(['password' => Hash::make($newPassword)])->save();
    }
}
