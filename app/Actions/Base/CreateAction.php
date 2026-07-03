<?php

declare(strict_types=1);

namespace App\Actions\Base;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CreateAction
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(array):array  $prepare  data → columns
     * @param  Closure(Model,array):void|null  $after  side effects
     */
    public function execute(string $modelClass, array $data, Closure $prepare, ?Closure $after = null): Model
    {
        return DB::transaction(function () use ($modelClass, $data, $prepare, $after) {
            $model = $modelClass::create($prepare($data));
            $after?->__invoke($model, $data);

            return $model;
        });
    }
}
