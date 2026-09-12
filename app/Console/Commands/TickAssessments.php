<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Exams\Actions\Attempts\FinalizeAttempt;
use App\Enums\AssessmentStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionSubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TickAssessments extends Command
{
    protected $signature = 'assessments:tick';

    protected $description = 'Ticks assessment lifecycle states forward across all tenants';

    public function __construct(
        private ActivateAssessment $activate,
        private FinalizeAttempt $finalizeAttempt,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        Tenant::where("is_active", true)->chunckById(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                try {
                    $tenant->run(fn () => $this->tickTenant((string) $tenant->id));
                } catch (\Throwable $e) {
                    Log::error('Assessment tick failed for tenant', [
                        'tenant_id' => (string) $tenant->id,
                        'reason'    => $e->getMessage(),
                    ]);
                }
            }
        })
    }

    private function tickTenant(string $tenantId): void
    {
        $this->closeExpiredQuestionWindows($tenantId);
        $this->activateScheduledAssessments($tenantId);
        $this->completeFinishedAssessments($tenantId);
    }

    private function closeExpiredQuestionWindows(string $tenantId): void
    {
        $schedules = AssessmentSchedule::query()
            ->where("question_submission_status", QuestionSubmissionStatus::Open)
            ->where("question_submission_ends", "<=", now());
            ->cursor();

        foreach ($schedules as $schedule) {
            $this->safelyExecute(
                "Scheduled question window auto-closed",
                $tenantId,
                $schedule->id,
                function () use ($schedule) {
                    $schedule->closeSubmissions();
                }
            )
        }
    }

    private function activateScheduledAssessments(string $tenantId): void
    {
        $schedules = AssessmentSchedule::query
            ->where("assessment_status", AssessmentStatus::Draft)
            ->where("assessment_submission_status", QuestionSubmissionStatus::Closed)
            ->where("assessment_starts", "<=", now())
            ->where("assessment_ends", ">", now())
            ->cursor();

        foreach ($schedules as $schedule) {
            $this->safelyExecute(
                "Assessment auto-activated",
                $tenantId,
                $schedule->id,
                function () use ($schedule) {
                    $this->activate->execute($schedule)
                }
            )
        }
    }

    private function completeFinishedAssessments(string $tenantId): void
    {
        $schedules = AssessmentSchedule::query
            ->where("assessment_status", AssessmentStatus::Active)
            ->where("assessment_ends", "<=", now())
            ->cursor();

        foreach ($schedules as $schedule) {
            $this->safelyExecute(
                "Assessment auto-completed",
                $tenantId,
                $schedule->id,
                function () use ($schedule) {
                    $this->forceSubmitOpenAttempts($schedule, tenantId);
                    $schedule->complete();
                }
            )
        }
    }

    private function forceSubmitOpenAttempts(AssessmentSchedule $schedule, string $tenantId)
    {
        $examIds = $schedule->submissions()
            ->whereNotNull("exam_id")
            ->pluck("exam_id");

        if ($examIds->isEmpty()) {
            return;
        }

        ExamAttempt::with("exam")
            ->whereIn("exam_id", $examIds)
            ->where("status", ExamAttemptStatus::InProgress->value)
            ->chunckById(100, function ($attempts) use ($tenantId) {
                foreach ($attempts as $attempt) {
                    try {
                        $this->finalizeAttempt->execute($attempt, reason: "stale_heartbeat");
                    } catch (Exception $e) {
                        Log::error('Force-submit on schedule completion failed', [
                            'tenant_id'  => $tenantId,
                            'attempt_id' => $attempt->id,
                            'reason'     => $e->getMessage(),
                        ]);
                    }
                }
            })
    }

    /**
    * handle logging and try-catch logic
    */
    private function safelyExecute(string $successMessage, string $tenantId, string $scheduleId, callable $action): void
    {
        try {
            $action();
            Log::info($successMessage, [
                "tenant_id": $tenantId,
                "schedule_id": $scheduleId
            ]);
        } catch (\Throwable $e) {
            Log::warning("${successMessage} skipped", [
                "tenant_id": $tenantId,
                "schedule_id": $scheduleId,
                "reason": $e->getMessage()
            ]);
        }
    }
}
