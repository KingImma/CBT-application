<?php

declare(strict_types=1);

namespace App\Domains\Students\Actions;

use App\Domains\Exams\Actions\RecalculateExpectedAttempts;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\TermResult;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently remove a student and every record that references them.
 *
 * Student exam records are protected by ON DELETE RESTRICT foreign keys, so
 * they have to be removed before the user row or PostgreSQL aborts the delete.
 */
final class DeleteStudent
{
    public function __construct(
        private RecalculateExpectedAttempts $recalculateExpectedAttempts,
    ) {
    }

    public function execute(User $student): void
    {
        $profile = $student->studentProfile;

        $classArms = [[
            $profile?->class_level_id !== null ? (string) $profile->class_level_id : null,
            $profile?->class_arm_id !== null ? (string) $profile->class_arm_id : null,
        ]];

        DB::transaction(function () use ($student): void {
            $student->tokens()->delete();

            ExamResult::where('student_id', $student->id)->delete();
            ExamAttempt::where('student_id', $student->id)->delete();

            DB::table('exam_attendance')->where('student_id', $student->id)->delete();

            TermResult::where('student_id', $student->id)->delete();

            $student->studentProfile()->delete();
            $student->syncRoles([]);
            $student->forceDelete();
        });

        $this->recalculateExpectedAttempts->forClassArms($classArms);
    }
}
