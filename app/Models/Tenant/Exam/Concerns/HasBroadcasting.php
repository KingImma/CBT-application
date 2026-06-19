<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Enums\ExamStatus;
use App\Events\{ExamActivated, ExamCompleted};
use Illuminate\Database\Eloquent\Model;

trait HasBroadcasting
{
    public static function bootHasBroadcasting(): void
    {
        static::saved(function (Model $model) {
            if (! $model->wasChanged('status')) {
                return;
            }

            match ($model->status) {
                ExamStatus::Active => event(new ExamActivated($model)),
                ExamStatus::Completed => event(new ExamCompleted($model)),
                default => null,
            };
        });
    }
}
