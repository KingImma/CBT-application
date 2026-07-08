<?php

declare(strict_types=1);

namespace App\Models\Tenant\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait HasCacheInvalidation
{
    public static function bootHasCacheInvalidation(): void
    {
        static::saved(function (Model $tenant) {
            if ($tenant->wasChanged(['handle', 'is_active', 'subscription_status'])) {
                Cache::forget("tenant:handle:{$tenant->getOriginal('handle')}");

                if ($tenant->wasChanged('handle')) {
                    Cache::forget("tenant:handle:{$tenant->handle}");
                }
            }
        });

        static::deleted(function (Model $tenant) {
            Cache::forget("tenant:handle:{$tenant->handle}");
        });
    }
}
