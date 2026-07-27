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

        // FIX: tenant('domain') is not a real attribute — Tenant has no
        // `domain` column, only a domains() relation. It always resolved to
        // null, silently falling back to the CENTRAL app URL instead of the
        // school's subdomain. SEB would then load a host with no tenant
        // context and hang indefinitely on "connecting."
        // Use the same {handle}.{central_domain} pattern used elsewhere
        // (CreateTenant, SendSchoolWelcomeEmail) for consistency.
        $trustedHost = $this->resolveTenantHost();

        // Prepend the sebs:// protocol so the OS opens the SEB client,
        // which will then download the XML from this URL.
        return "sebs://{$trustedHost}{$signedPath}";
    }

    private function resolveTenantHost(): string
    {
        $handle = tenant('handle');

        if ($handle === null) {
            // Defensive fallback — should never happen inside tenant context,
            // but prevents a hard crash if this is ever called outside one.
            return parse_url(config('app.url'), PHP_URL_HOST);
        }

        $centralDomain = config('app.central_domain')
            ?? collect(config('tenancy.central_domains', []))
                ->reject(fn ($d) => in_array($d, ['127.0.0.1', 'localhost'], true))
                ->first()
            ?? 'localhost';

        return "{$handle}.{$centralDomain}";
    }
}
