<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Values\ExamSettings;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ExamPublishingAction
{
    public function publish(Exam $exam): Exam
    {
        $canPublish = $exam->status === ExamStatus::Draft
            || $exam->status === ExamStatus::Grading
            || $exam->status === ExamStatus::Completed;

        if (! $canPublish) {
            throw new \RuntimeException('Only draft, grading, or completed exams can be published.');
        }

        if ($exam->status === ExamStatus::Draft) {
            $this->validateDraftForPublishing($exam);
        }

        return DB::transaction(function () use ($exam) {
            $settings = $exam->settings;
            if ($exam->type === ExamType::Exam->value) {
                $settings = new ExamSettings(
                    randomizeQuestions: $settings->randomizeQuestions,
                    showResultImmediately: false,
                    resultsReleaseDate: $settings->resultsReleaseDate,
                    requireAttendance: $settings->requireAttendance,
                    maxSuspiciousEvents: $settings->maxSuspiciousEvents,
                );
            }

            $exam->update([
                'status' => ExamStatus::Published->value,
                'settings' => $settings,
            ]);

            return $exam->fresh();
        });
    }

    private function validateDraftForPublishing(Exam $exam): void
    {
        if ($exam->examQuestions()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one question.');
        }

        if ((float) $exam->total_marks <= 0) {
            throw new \RuntimeException('Exam total marks must be greater than 0.');
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
    }
}
