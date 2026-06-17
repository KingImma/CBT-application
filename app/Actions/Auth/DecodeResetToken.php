<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DecodeResetToken
{
    public function execute(string $token): string
    {
        $key = "pwd_reset_token:{$token}";
        $email = Cache::get($key);

        if (! $email) {
            throw ValidationException::withMessages([
                'reset_token' => 'Your reset session has expired. Please start again.',
            ]);
        }

        Cache::forget($key);

        return $email;
    }
}
