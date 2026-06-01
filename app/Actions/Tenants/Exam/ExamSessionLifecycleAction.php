<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamSessionEnded;
use App\Events\ExamSessionStarted;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\DB;

class ExamSessionLifecycleAction
{
    public function startSession(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Scheduled) {
            throw new \RuntimeException('Only scheduled exams can start a session.');
        }

        if ($exam->settings->requireAttendance) {
            $this->ensureAttendanceRecorded($exam);
        }

        return DB::transaction(function () use ($exam) {
            $exam->update([
                'status' => ExamStatus::Active->value,
                'session_started_at' => now(),
                'session_duration_minutes' => $exam->session_duration_minutes ?? ($exam->duration_minutes + 60),
            ]);

            $exam = $exam->fresh();

            broadcast(new ExamSessionStarted($exam));

            return $exam;
        });
    }

    public function endSession(Exam $exam, ExamSessionAction $sessionAction): Exam
    {
        if ($exam->status !== ExamStatus::Active) {
            throw new \RuntimeException('Only active exams can have their session ended.');
        }

        return DB::transaction(function () use ($exam, $sessionAction) {
            $this->forceSubmitInProgressAttempts($exam, $sessionAction);

            $needsGrading = $this->examNeedsGrading($exam);

            $exam->update([
                'status' => $needsGrading
                    ? ExamStatus::Grading->value
                    : ExamStatus::Completed->value,
            ]);

            $exam = $exam->fresh();

            broadcast(new ExamSessionEnded($exam));

            return $exam;
        });
    }

    private function ensureAttendanceRecorded(Exam $exam): void
    {
        $hasPresentStudents = $exam->attendanceRecords()
            ->where('status', 'present')
            ->exists();

        if (! $hasPresentStudents) {
            throw new \RuntimeException('Cannot start session: no attendance recorded. Mark students present first.');
        }
    }

    private function forceSubmitInProgressAttempts(Exam $exam, ExamSessionAction $sessionAction): void
    {
        $inProgressAttempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('status', ExamAttemptStatus::InProgress->value)
            ->get();

        foreach ($inProgressAttempts as $attempt) {
            try {
                $sessionAction->submit($attempt);
            } catch (\RuntimeException $e) {
                // Log but continue with other attempts
            }
        }
    }

    private function examNeedsGrading(Exam $exam): bool
    {
        return ExamAttempt::where('exam_id', $exam->id)
            ->whereIn('status', [
                ExamAttemptStatus::InProgress->value,
                ExamAttemptStatus::Submitted->value,
                ExamAttemptStatus::Grading->value,
            ])->exists();
    }
}
