<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Models\Tenant\SchoolSetting;

final class DailySubjectCap
{
    public const KEY = 'max_subjects_per_day';

    public const DEFAULT = 3;

    /** Null means unlimited. */
    public static function limit(): ?int
    {
        $value = SchoolSetting::where('key', self::KEY)->value('value');

        if ($value === null || $value === '') {
            return self::DEFAULT;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }
}