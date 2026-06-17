<?php

declare(strict_types=1);

namespace App\Models\Tenant\Concerns;

use App\Enums\StatusType;
use App\Events\TenantReinstated;
use App\Events\TenantSuspended;
use Illuminate\Database\Eloquent\Model;

trait HasBroadcasting
{
    public static function bootHasBroadcasting(): void
    {
        static::saved(function (Model $model) {
            if (! $model->wasChanged('subscription_status')) {
                return;
            }

            match ($model->subscription_status) {
                StatusType::Suspended => event(new TenantSuspended($model)),
                StatusType::Active => event(new TenantReinstated($model)),
                default => null,
            };
        });
    }
}
