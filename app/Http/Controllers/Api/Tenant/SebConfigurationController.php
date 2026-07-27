<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SebConfigurationController extends Controller
{
    public function download(Request $request): Response
    {
        // 1. Validate the signature to ensure this wasn't forged
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired configuration link.');
        }

        $attemptId = $request->query('attempt_id');
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            abort(403, 'Exam session is expired or invalid.');
        }

        // 2. Generate the durable session token
        $durationMinutes = $attempt->exam->duration_minutes ?? 120;
        $expiration = now()->addMinutes((int) $durationMinutes + 15);

        $token = DB::transaction(function () use ($attempt, $expiration) {
            $student = User::query()
                ->lockForUpdate()
                ->findOrFail($attempt->student_id);

            $student->tokens()->where('name', 'seb-active-session')->delete();

            return $student->createToken(
                name: 'seb-active-session',
                abilities: ['exam:take'],
                expiresAt: $expiration,
            )->plainTextToken;
        });

        // 3. Build the Start URL with the URL-encoded token in the fragment
        // FIX: tenant('domain') is not a real Tenant attribute (no `domain`
        // column — only a domains() relation). It always resolved to null,
        // silently falling back to the CENTRAL app host instead of the
        // school's subdomain. SEB would launch against the wrong host,
        // fail to establish tenant context, and sit stuck "connecting"
        // forever. Use the {handle}.{central_domain} pattern used
        // consistently elsewhere in the codebase.
        $frontendHost = $this->resolveTenantHost();
        $frontendBaseUrl = "https://{$frontendHost}";
        $encodedToken = urlencode($token);

        $startUrl = "{$frontendBaseUrl}/seb-entry?attempt_id={$attempt->id}#token={$encodedToken}";

        // 4. Inject into the XML template
        $templatePath = 'seb/base_config.seb';
        if (! Storage::exists($templatePath)) {
            abort(500, 'SEB base configuration not found.');
        }

        $xml = Storage::get($templatePath);

        // Escape the URL to prevent XML injection
        $safeStartUrl = htmlspecialchars($startUrl, ENT_XML1, 'UTF-8');
        $payload = str_replace('{{START_URL}}', $safeStartUrl, $xml);

        // 5. Serve as an SEB configuration file
        return response($payload, 200, [
            'Content-Type' => 'application/seb',
            'Content-Disposition' => 'attachment; filename="exam-config.seb"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    private function resolveTenantHost(): string
    {
        $handle = tenant('handle');

        if ($handle === null) {
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
