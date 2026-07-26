<?php

declare(strict_types=1);

namespace App\Domains\Students\Support;

use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\URL;

class SebLaunchHelper
{
    public function generateLaunchUrl(ExamAttempt $attempt): string
    {
        $signedURL = URL::temporarySignedRoute(
            'api.tenant.seb.authenticate',
            now()->addMinutes(5),
            ['attempt_id' => $attempt->id,]
        );

        return str_replace(['http://', 'https://'], 'sebs://', $signedURL);
    }
}
