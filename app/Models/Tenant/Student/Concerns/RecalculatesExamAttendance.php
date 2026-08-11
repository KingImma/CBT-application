<?php

declare(strict_types=1);

namespace App\Models\Tenant\Student\Concerns;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
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
        Exam::where('status', ExamStatus::Active->value)
            ->where('class_level_id', $profile->class_level_id)
            ->where(fn ($q) => $q->whereNull('class_arm_id')->orWhere('class_arm_id', $profile->class_arm_id))
            ->each(fn (Exam $exam) => $exam->update(['expected_attempts' => $exam->expectedAttempts()]));
    }
}
