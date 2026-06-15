<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class ExamStatusAction
{
    /**
     * The exam window extends to N× the exam duration to allow late starters.
     */
    private const WINDOW_DURATION_MULTIPLIER = 2;

    public function submitForReview(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Draft) {
            throw new \RuntimeException('Only draft exams can be submitted for review.');
        }

        if ($exam->examQuestions()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one question.');
        }

        if ((float) $exam->total_marks <= 0) {
            throw new \RuntimeException('Exam total marks must be greater than 0.');
        }

        return $this->transition($exam, ['status' => ExamStatus::Submitted->value]);
    }

    public function activate(Exam $exam, string $activatedBy): Exam
    {
        if ($exam->status !== ExamStatus::Submitted) {
            throw new \RuntimeException('Only submitted exams can be activated.');
        }

        if ($exam->duration_minutes <= 0) {
            throw new \RuntimeException('Exam duration must be greater than 0.');
        }

        if ($exam->pass_mark === null) {
            throw new \RuntimeException('Exam pass mark must be set.');
        }

        if ((float) $exam->pass_mark > (float) $exam->total_marks) {
            throw new \RuntimeException('Pass mark cannot exceed total marks.');
        }

        if ($exam->scheduled_start === null) {
            throw new \RuntimeException('Scheduled start time must be set before activation.');
        }

        $windowEnd = $exam->scheduled_start->copy()->addMinutes(
            $exam->duration_minutes * self::WINDOW_DURATION_MULTIPLIER
        );

        $expectedAttempts = User::role('student')
            ->whereHas('studentProfile', function ($q) use ($exam) {
                $q->where('class_level_id', $exam->class_level_id);
                if ($exam->class_arm_id) {
                    $q->where('class_arm_id', $exam->class_arm_id);
                }
            })
            ->count();

        return $this->transition($exam, [
            'status' => ExamStatus::Active->value,
            'approved_by' => $activatedBy,
            'approved_at' => now(),
            'window_end' => $windowEnd,
            'expected_attempts' => $expectedAttempts,
        ]);
    }

    /**
     * Dormant — reserved for future use. No route/UI exposed.
     */
    public function revertToDraft(Exam $exam, ?string $reason = null): Exam
    {
        if (! in_array($exam->status, [ExamStatus::Submitted, ExamStatus::Active])) {
            throw new \RuntimeException('Only submitted or active exams can be reverted to draft.');
        }

        return $this->transition($exam, [
            'status' => ExamStatus::Draft->value,
            'rejection_reason' => $reason,
        ]);
    }

    private function transition(Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data) {
            $exam->update($data);

            return $exam->fresh();
        });
    }

    public function forceComplete(Exam $exam): Exam
    {
        if ($exam->status === ExamStatus::Completed || $exam->status === ExamStatus::Published) {
            throw new \RuntimeException('Exam is already completed or published.');
        }

        if ($exam->status === ExamStatus::Draft || $exam->status === ExamStatus::Submitted) {
            throw new \RuntimeException('Only active exams can be force-completed.');
        }

        return $this->transition($exam, [
            'status' => ExamStatus::Completed->value,
            'window_end' => now(), // close the window immediately
        ]);
    }
}
