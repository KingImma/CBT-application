<?php

declare(strict_types=1);

namespace App\Domains\Students\Support;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Request;

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

        // 3. Construct sebs:// deep link pointing to frontend SPA
        $frontendHost = Request::getHost();
        $frontendRoute = "/seb-entry";

        return sprintf('sebs://%s%s?attempt_id=%s&token=%s',
            $frontendHost,
            $frontendRoute,
            $attempt->id,
            $token
        );
    }
}
