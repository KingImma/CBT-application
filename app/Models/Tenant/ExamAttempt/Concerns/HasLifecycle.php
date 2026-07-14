<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Domains\Exams\Data\ExamAttemptSettings;
use App\Domains\Exams\Exceptions\AttemptCannotBeSubmittedException;
use App\Enums\ExamAttemptStatus;
use App\Enums\SuspiciousEventType;
use Illuminate\Support\Facades\DB;

trait HasLifecycle
{
    public function start(?ExamAttemptSettings $settings = null): self
    {
        throw_unless($this->status === ExamAttemptStatus::InProgress, AttemptCannotBeSubmittedException::class);

        $this->status = ExamAttemptStatus::InProgress;
        $this->started_at = now();

        if ($settings) {
            $this->settings = $settings;
        }

        return $this;
    }

    public function submit(array $answers): self
    {
        throw_unless($this->canSubmit(), AttemptCannotBeSubmittedException::class);

        DB::transaction(function () use ($answers) {
            $this->gradeAnswers($answers);
            $scores = $this->calculateScore();

            $this->status = ExamAttemptStatus::Graded;
            $this->total_score = $scores['total_score'];
            $this->percentage_score = $scores['percentage_score'];
            $this->grade = $scores['grade'];
            $this->submitted_at = now();

            // Update exam completed attempts
            $this->exam->completed_attempts = $this->exam->actualAttempts();

            if ($this->exam->completed_attempts >= $this->exam->expected_attempts) {
                $this->exam->complete();
            }

            $this->exam->save();
        });

        return $this;
    }

    public function finalizeExpired(): self
    {
        if (! $this->isExpired()) {
            return $this;
        }

        return $this->submit([]);
    }

    public function logSuspiciousEvent(SuspiciousEventType $type, array $metadata = []): void
    {
        $events = $this->suspicious_events ?? [];
        $events[] = [
            'type' => $type->value,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];
        $this->suspicious_events = $events;
    }
}
