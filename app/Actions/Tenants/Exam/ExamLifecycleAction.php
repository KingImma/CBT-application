<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Values\ExamSettings;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Events\ExamSessionEnded;
use App\Events\ExamSessionStarted;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\DB;

class ExamLifecycleAction
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

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => ExamStatus::Submitted->value]);

            return $exam->fresh();
        });
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

        return DB::transaction(function () use ($exam, $approvedBy) {
            $exam->update([
                'status' => ExamStatus::Scheduled->value,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            return $exam->fresh();
        });
    }

    public function reject(Exam $exam, ?string $rejectionReason = null): Exam
    {
        if ($exam->status !== ExamStatus::Submitted) {
            throw new \RuntimeException('Only submitted exams can be rejected.');
        }

        return DB::transaction(function () use ($exam, $rejectionReason) {
            $exam->update([
                'status' => ExamStatus::Draft->value,
                'rejection_reason' => $rejectionReason,
            ]);

            return $exam->fresh();
        });
    }

    public function recall(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Scheduled) {
            throw new \RuntimeException('Only scheduled exams can be recalled to draft.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => ExamStatus::Draft->value]);

            return $exam->fresh();
        });
    }

    public function emergencyRevert(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Grading) {
            throw new \RuntimeException('Only grading exams can be emergency-reverted to draft.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => ExamStatus::Draft->value]);

            return $exam->fresh();
        });
    }

    public function lock(Exam $exam): Exam
    {
        if (! $exam->canBeLocked()) {
            throw new \RuntimeException('Only active or submitted exams can be locked.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => ExamStatus::Locked->value]);

            return $exam->fresh();
        });
    }

    public function unlock(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Locked) {
            throw new \RuntimeException('Only locked exams can be unlocked.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => ExamStatus::Draft->value]);

            return $exam->fresh();
        });
    }

    public function publish(Exam $exam): Exam
    {
        $canPublish = $exam->status === ExamStatus::Draft
            || $exam->status === ExamStatus::Grading
            || $exam->status === ExamStatus::Completed;

        if (! $canPublish) {
            throw new \RuntimeException('Only draft, grading, or completed exams can be published.');
        }

        if ($exam->status === ExamStatus::Draft) {
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

    public function startSession(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Scheduled) {
            throw new \RuntimeException('Only scheduled exams can start a session.');
        }

        if ($exam->settings->requireAttendance) {
            $hasPresentStudents = $exam->attendanceRecords()
                ->where('status', 'present')
                ->exists();

            if (! $hasPresentStudents) {
                throw new \RuntimeException('Cannot start session: no attendance recorded. Mark students present first.');
            }
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

            $needsGrading = ExamAttempt::where('exam_id', $exam->id)
                ->whereIn('status', [
                    ExamAttemptStatus::InProgress->value,
                    ExamAttemptStatus::Submitted->value,
                    ExamAttemptStatus::Grading->value,
                ])->exists();

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
}
