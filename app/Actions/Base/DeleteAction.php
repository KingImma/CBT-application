<?php

declare(strict_types=1);

namespace App\Actions\Base;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DeleteAction
{
    /**
     * @param  Closure(Model):void  $guard  throw to abort
     * @param  Closure(Model):void|null  $cascade  child cleanup before delete
     * @param  Closure(Model):void|null  $after  post-delete side effects
     */
    public function execute(Model $model, Closure $guard, ?Closure $cascade = null, ?Closure $after = null): Model
    {
        return DB::transaction(function () use ($model, $guard, $cascade, $after) {
            $guard($model);
            $cascade?->__invoke($model);
            $model->delete();
            $after?->__invoke($model);

            return $model;
        });
    }
}
