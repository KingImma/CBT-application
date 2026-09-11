<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use RuntimeException;

/**
 * Read-only view over the active tenant for shared infrastructure.
 *
 * Domains should resolve tenant *business* concerns through the Tenancy
 * domain; this module only exposes the ambient identity that framework-level
 * services (cache keys, background jobs, reports) need. Centralising it stops
 * the `tenant('id')` string-casting and ad-hoc cache-key prefixes from being
 * re-derived in every domain.
 */
final class TenantContext
{
    public function id(): ?string
    {
        $id = tenant('id');

        return $id === null ? null : (string) $id;
    }

    /** Fails loudly when a tenant-scoped operation is attempted from central context. */
    public function requireId(): string
    {
        return $this->id() ?? throw new RuntimeException(
            'This operation requires an active tenant.'
        );
    }

    public function name(): ?string
    {
        $name = tenant('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function isCentral(): bool
    {
        return $this->id() === null;
    }

    /**
     * Namespaces a cache key under the active tenant so tenants can never read
     * each other's cached values, and central context gets its own bucket.
     */
    public function cacheKey(string $suffix): string
    {
        return sprintf('%s:%s', $this->id() ?? 'central', $suffix);
    }
}
