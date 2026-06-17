<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAnswer\Concerns;

trait HasBroadcasting
{
    public static function bootHasBroadcasting(): void
    {
        // No broadcasting needed for ExamAnswer at this time
    }
}
