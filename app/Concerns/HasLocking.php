<?php

namespace App\Concerns;

use App\Exceptions\TenantSlugAlreadyTakenException;
use Illuminate\Support\Facades\Cache;

trait HasLocking
{
    /**
     * @param  callable(): mixed  $callback
     */
    protected function withSlugLock(string $slug, callable $callback): mixed
    {
        $lock = Cache::lock("tenant_slug:{$slug}", 10);

        if (! $lock->get()) {
            throw new TenantSlugAlreadyTakenException($slug);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
