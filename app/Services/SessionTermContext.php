<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Support\Facades\Cache;

class SessionTermContext
{
    private const CACHE_KEY = 'session_term_context';
    private const CACHE_TTL = 60;

    public function currentSession(): ?AcademicSession
    {
        return Cache::remember($this->cacheKey('session'), self::CACHE_TTL, function () {
            return AcademicSession::where('is_current', true)->first();
        });
    }

    public function currentTerm(): ?Term
    {
        $session = $this->currentSession();
        if (! $session) {
            return null;
        }

        return Cache::remember($this->cacheKey('term:'.$session->id), self::CACHE_TTL, function () use ($session) {
            return $session->currentTerm;
        });
    }

    public function currentSessionId(): ?string
    {
        return $this->currentSession()?->id;
    }

    public function currentTermId(): ?string
    {
        return $this->currentTerm()?->id;
    }

    public function flush(): void
    {
        $session = $this->currentSession();

        Cache::forget($this->cacheKey('session'));

        if ($session) {
            Cache::forget($this->cacheKey('term:'.$session->id));
        }
    }

    private function cacheKey(string $suffix): string
    {
        return self::CACHE_KEY.':'.(tenant('id') ?? 'central').':'.$suffix;
    }
}
