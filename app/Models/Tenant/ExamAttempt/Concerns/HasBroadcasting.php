<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Enums\ExamAttemptStatus;
use App\Events\AttemptGraded;
use Illuminate\Database\Eloquent\Model;

trait HasBroadcasting
{
    public static function bootHasBroadcasting(): void
    {
        static::saved(function (Model $model) {
            if (! $model->wasChanged('status')) {
                return;
            }

            if ($model->status === ExamAttemptStatus::Graded) {
                event(new AttemptGraded($model));
            }
        });
    }
}
