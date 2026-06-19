<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class DecodeResetToken
{
    public function execute(string $token): string
    {
        $this->validateTokenExists($token);

        return $this->getAndDeleteToken($token);
    }

    private function validateTokenExists(string $token): void
    {
        $key = "pwd_reset_token:{$token}";
        $cache = Cache::getFacadeRoot();

        if ($cache->has($key)) {
            return;
        }

        throw ValidationException::withMessages([
            'reset_token' => 'Your reset session has expired. Please start again.',
        ]);
    }

    private function getAndDeleteToken(string $token): string
    {
        $key = "pwd_reset_token:{$token}";

        $cache = Cache::getFacadeRoot();
    
        $email = $cache->get($key);
        
        $cache->forget($key);

        return $email;
    }
}
