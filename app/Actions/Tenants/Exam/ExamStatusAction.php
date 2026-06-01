<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ExamStatusAction
{
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

    public function activate(Exam $exam, string $approvedBy): Exam
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

        return $this->transition($exam, [
            'status' => ExamStatus::Scheduled->value,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    public function reject(Exam $exam, ?string $rejectionReason = null): Exam
    {
        if ($exam->status !== ExamStatus::Submitted) {
            throw new \RuntimeException('Only submitted exams can be rejected.');
        }

        return $this->transition($exam, [
            'status' => ExamStatus::Draft->value,
            'rejection_reason' => $rejectionReason,
        ]);
    }

    public function recall(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Scheduled) {
            throw new \RuntimeException('Only scheduled exams can be recalled to draft.');
        }

        return $this->transition($exam, ['status' => ExamStatus::Draft->value]);
    }

    public function emergencyRevert(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Grading) {
            throw new \RuntimeException('Only grading exams can be emergency-reverted to draft.');
        }

        return $this->transition($exam, ['status' => ExamStatus::Draft->value]);
    }

    public function lock(Exam $exam): Exam
    {
        if (! $exam->canBeLocked()) {
            throw new \RuntimeException('Only active or submitted exams can be locked.');
        }

        return $this->transition($exam, ['status' => ExamStatus::Locked->value]);
    }

    public function unlock(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Locked) {
            throw new \RuntimeException('Only locked exams can be unlocked.');
        }

        return $this->transition($exam, ['status' => ExamStatus::Draft->value]);
    }

    private function transition(Exam $exam, array $data): Exam
    {
        return DB::transaction(function () use ($exam, $data) {
            $exam->update($data);

            return $exam->fresh();
        });
    }
}
