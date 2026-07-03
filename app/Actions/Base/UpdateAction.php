<?php

declare(strict_types=1);

namespace App\Actions\Base;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * @param  Closure(Model,array):void  $guard  throw to abort
     * @param  Closure(Model,array):array  $prepare  data → columns
     * @param  Closure(Model,array):void|null  $after  side effects
     */
    public function execute(Model $model, array $data, Closure $guard, Closure $prepare, ?Closure $after = null): Model
    {
        return DB::transaction(function () use ($model, $data, $guard, $prepare, $after) {
            $guard($model, $data);
            $model->update($prepare($model, $data));
            $fresh = $model->fresh();
            $after?->__invoke($fresh, $data);

            return $fresh;
        });
    }
}
