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
use App\Models\Tenant\Topic;
use Illuminate\Support\Facades\DB;

class ExamLifecycleAction
{
    public function submitForReview(Exam $exam): Exam
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be submitted for review.');
        }

        if ($exam->examQuestions()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one question.');
        }

        if ((float) $exam->total_marks <= 0) {
            throw new \RuntimeException('Exam total marks must be greater than 0.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => 'submitted']);

            return $exam->fresh();
        });
    }

    public function activate(Exam $exam): Exam
    {
        if ($exam->status !== 'submitted') {
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

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => 'active']);

            return $exam->fresh();
        });
    }

    public function lock(Exam $exam): Exam
    {
        if (! in_array($exam->status, ['active', 'submitted'])) {
            throw new \RuntimeException('Only active or submitted exams can be locked.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update(['status' => 'locked']);

            return $exam->fresh();
        });
    }

    public function publish(Exam $exam): Exam
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be published.');
        }

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

        if ($exam->topics()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one topic in the pool.');
        }

        return DB::transaction(function () use ($exam) {
            $settings = $exam->settings;
            if ($exam->type === ExamType::Exam->value) {
                $settings = new ExamSettings(
                    randomizeQuestions: $settings->randomizeQuestions,
                    showResultImmediately: false,
                    resultsReleaseDate: $settings->resultsReleaseDate,
                    requireAttendance: $settings->requireAttendance,
                    distribution: $settings->distribution,
                    topicWeights: $settings->topicWeights,
                );
            }

            $exam->update([
                'status' => 'published',
                'settings' => $settings,
            ]);

            return $exam->fresh();
        });
    }

    public function startSession(Exam $exam): Exam
    {
        if ($exam->status !== 'scheduled') {
            throw new \RuntimeException('Only scheduled exams can start a session.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update([
                'status' => 'active',
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
        if ($exam->status !== ExamStatus::Active->value) {
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

            $exam->update([
                'status' => ExamStatus::Grading->value,
            ]);

            $exam = $exam->fresh();

            broadcast(new ExamSessionEnded($exam));

            return $exam;
        });
    }

    public function syncTopics(Exam $exam, array $topicIds, array $weights = []): void
    {
        DB::transaction(function () use ($exam, $topicIds, $weights) {
            $exam->topics()->detach();

            foreach ($topicIds as $topicId) {
                $topic = Topic::findOrFail($topicId);
                $exam->topics()->attach($topic->id, [
                    'weight' => $weights[$topicId] ?? null,
                ]);
            }
        });
    }
}
