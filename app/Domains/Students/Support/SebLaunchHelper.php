<?php

declare(strict_types=1);

namespace App\Domains\Students\Support;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;

class SebLaunchHelper
{
    public function generateLaunchUrl(ExamAttempt $attempt, User $student): string
    {
        // 1. Revoke pending SEB tokens
        $student->tokens()->where('name', 'seb-launch-token')->delete();

        // 2. Create 5-minute single-use token
        $token = $student->createToken(
            name: 'seb-launch-token',
            abilities: ['exam:take'],
            expiresAt: now()->addMinutes(5)
        )->plainTextToken;

        // 3. Resolve trusted host from the tenant model or environment config
        // This completely bypasses the untrusted Request::getHost()
        $trustedHost = tenant('domain') ?? parse_url(config('app.url'), PHP_URL_HOST);
        $frontendRoute = "/seb-entry";

        return sprintf('sebs://%s%s?attempt_id=%s&token=%s',
            $trustedHost,
            $frontendRoute,
            $attempt->id,
            $token
        );
    }
}
