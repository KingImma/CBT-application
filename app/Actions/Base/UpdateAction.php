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
     * @param  Closure(Model,array):array  $prepare  data → columns (respects $fillable)
     * @param  Closure(Model,array):array|null  $force  data → guarded columns (via forceFill)
     * @param  Closure(Model,array):void|null  $after  side effects
     */
    public function execute(Model $model, array $data, Closure $guard, Closure $prepare, ?Closure $after = null, ?Closure $force = null): Model
    {
        return DB::transaction(function () use ($model, $data, $guard, $prepare, $after, $force) {
            $guard($model, $data);
            $model->update($prepare($model, $data));

            if ($force !== null) {
                $model->forceFill($force($model, $data))->save();
            }

            $fresh = $model->fresh();

            if ($fresh === null) {
                throw new \RuntimeException('Model no longer exists after update.');
            }

            $after?->__invoke($fresh, $data);

            return $fresh;
        });
    }
}
