<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Domains\Exams\Events\ExamActivated;
use App\Domains\Exams\Events\ExamCompleted;
use App\Enums\ExamStatus;
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
