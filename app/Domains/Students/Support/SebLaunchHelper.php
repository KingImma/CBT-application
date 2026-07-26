<?php

declare(strict_types=1);

namespace App\Domains\Students\Support;

use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\URL;

class SebLaunchHelper
{
    public function generateLaunchUrl(ExamAttempt $attempt): string
    {
        // Generate a relative signed path
        $signedPath = URL::temporarySignedRoute(
            'api.tenant.seb.config.download',
            now()->addMinutes(5),
            ['attempt_id' => $attempt->id],
            false
        );

        $trustedHost = tenant('domain') ?? parse_url(config('app.url'), PHP_URL_HOST);

        // Prepend the sebs:// protocol so the OS opens the SEB client, 
        // which will then download the XML from this URL.
        return "sebs://{$trustedHost}{$signedPath}";
    }
}