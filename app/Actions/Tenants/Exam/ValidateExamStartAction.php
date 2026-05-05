<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Enums\ExamAttemptStatus;
use Illuminate\Support\Facades\DB;

class ValidateExamStartAction
{
    public function execute(Exam $exam, User $student): void
    {
        if ($exam->status !== 'active') {
            throw new \RuntimeException('Exam is not active.');
        }

        if ($exam->session_started_at === null) {
            throw new \RuntimeException('Exam session has not started.');
        }

        $sessionDeadline = $exam->session_started_at->copy()->addMinutes($exam->session_duration_minutes ?? 120);
        if (now() > $sessionDeadline) {
            throw new \RuntimeException('Exam session has ended.');
        }

        if ($exam->settings['require_attendance'] ?? true) {
            $attendance = $exam->attendanceRecords()->where('student_id', $student->id)->first();
            if (! $attendance || $attendance->status !== 'present') {
                throw new \RuntimeException('Attendance not marked as present.');
            }
        }

        $maxAttempts = $exam->max_attempts ?? 1;
        $lastAttempt = $exam->attempts()->forStudent($student->id)->max('attempt_number');
        if ($lastAttempt !== null && $lastAttempt >= $maxAttempts) {
            throw new \RuntimeException('Maximum attempts exceeded.');
        }

        if (! $student->is_active) {
            throw new \RuntimeException('Student account is not active.');
        }
    }
}
