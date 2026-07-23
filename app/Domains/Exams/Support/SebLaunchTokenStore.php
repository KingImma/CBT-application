<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

final class SebLaunchTokenStore
{
    private function key(string $hashedToken): string
    {
        return "seb_launch_token:{$hashedToken}";
    }

    /** @return string the RAW token — only returned once, never stored raw. */
    public function issue(string $studentId, string $tenantId): string
    {
        $raw = Str::random(40);
        $hashed = hash('sha256', $raw);
        $ttlSeconds = (int) config('exams.seb.launch_token_ttl_seconds');

        // Value carries BOTH ids — exchange runs before any tenant middleware
        // fires, so it can't rely on X-Tenant header or subdomain to know
        // which tenant DB to look the student up in. Token is the only
        // source of truth for tenant context at that point.
        Redis::set(
            $this->key($hashed),
            json_encode(['student_id' => $studentId, 'tenant_id' => $tenantId]),
            'EX',
            $ttlSeconds,
        );

        return $raw;
    }

    /** @return array{student_id: string, tenant_id: string}|null */
    public function consume(string $rawToken): ?array
    {
        $hashed = hash('sha256', $rawToken);

        $raw = Redis::eval(
            "local v = redis.call('GET', KEYS[1]); if v then redis.call('DEL', KEYS[1]) end; return v",
            1,
            $this->key($hashed),
        );

        if ($raw === false || $raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
