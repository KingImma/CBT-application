<?php

declare(strict_types=1);

namespace App\Models\Tenant\Student\Concerns;

use App\Domains\Exams\Actions\RecalculateExpectedAttempts;
use App\Models\Tenant\StudentProfile;

trait RecalculatesExamAttendance
{
    public static function bootRecalculatesExamAttendance(): void
    {
        static::saved(function (StudentProfile $profile) {
            if ($profile->wasRecentlyCreated || $profile->wasChanged(['class_level_id', 'class_arm_id'])) {
                self::recalculateAffectedExams($profile);
            }
        });

        static::deleted(function (StudentProfile $profile) {
            self::recalculateAffectedExams($profile);
        });
    }

    private static function recalculateAffectedExams(StudentProfile $profile): void
    {
        app(RecalculateExpectedAttempts::class)->forClassArms([
            [
                $profile->class_level_id !== null ? (string) $profile->class_level_id : null,
                $profile->class_arm_id !== null ? (string) $profile->class_arm_id : null,
            ],
        ]);
    }
}
