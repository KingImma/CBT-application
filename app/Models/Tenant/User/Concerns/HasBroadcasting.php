<?php

declare(strict_types=1);

namespace App\Models\Tenant\User\Concerns;

use App\Events\UserActivated;
use App\Events\UserDeactivated;
use Illuminate\Database\Eloquent\Model;

trait HasBroadcasting
{
    public static function bootHasBroadcasting(): void
    {
        static::saved(function (Model $model) {
            if (! $model->wasChanged('is_active')) {
                return;
            }

            if ($model->is_active) {
                event(new UserActivated($model));
            } else {
                event(new UserDeactivated($model));
            }
        });
    }
}
