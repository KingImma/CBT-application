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

/**
 * Drives the schedule clock across every active tenant. Three idempotent,
 * time-driven flips:
 *   question window open  -> closed   once question_submission_ends passes
 *   draft                 -> active   once assessment_starts opens the window
 *   active                -> completed once assessment_ends passes
 * The queries run in order so a just-closed schedule whose start time has
 * already arrived can activate in the same tick. Guard failures (e.g. a
 * window that closed with zero approved submissions) are logged and skipped,
 * never fatal — the run must survive per-tenant and per-row hiccups.
 */
class TickAssessments extends Command
{
    protected $signature = 'assessments:tick';

    protected $description = 'Advance assessment schedule lifecycles (close questions, activate, complete) across all tenants.';

    public function __construct(
        private ActivateAssessment $activate,
        private FinalizeAttempt $finalizeAttempt,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(fn () => $this->tickTenant((string) $tenant->id));
            } catch (\Throwable $e) {
                // One unreachable/broken tenant DB must not sink the fleet tick.
                Log::error('Assessment tick failed for tenant', [
                    'tenant_id' => (string) $tenant->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    private function tickTenant(string $tenantId): void
    {
        $this->closeExpiredQuestionWindows($tenantId);
        $this->activateScheduledSchedules($tenantId);
        $this->completeFinishedSchedules($tenantId);
    }

    /** open -> closed once the teacher question deadline has passed. */
    private function closeExpiredQuestionWindows(string $tenantId): void
    {
        AssessmentSchedule::query()
            ->where('question_submission_status', QuestionSubmissionStatus::Open)
            ->where('question_submission_ends', '<=', now())
            ->get()
            ->each(function (AssessmentSchedule $schedule) use ($tenantId): void {
                $schedule->closeSubmissions();

                Log::info('Schedule question window auto-closed', [
                    'tenant_id' => $tenantId,
                    'schedule_id' => $schedule->id,
                ]);
            });
    }

    /**
     * draft -> active once the master student window opens. Activation
     * materialises the exams; a guard rejection (no approved submissions, no
     * slots, invalid window) leaves the schedule draft for an admin to fix.
     */
    private function activateScheduledSchedules(string $tenantId): void
    {
        AssessmentSchedule::query()
            ->where('assessment_status', AssessmentStatus::Draft)
            ->where('question_submission_status', QuestionSubmissionStatus::Closed)
            ->where('assessment_starts', '<=', now())
            ->where('assessment_ends', '>', now())
            ->get()
            ->each(function (AssessmentSchedule $schedule) use ($tenantId): void {
                try {
                    $this->activate->execute($schedule);

                    Log::info('Assessment auto-activated', [
                        'tenant_id' => $tenantId,
                        'schedule_id' => $schedule->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Assessment auto-activation skipped', [
                        'tenant_id' => $tenantId,
                        'schedule_id' => $schedule->id,
                        'reason' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * active -> completed once the master student window has passed. Any
     * attempt still in progress on a materialised paper is force-finalised
     * through the existing timeout path.
     */
    private function completeFinishedSchedules(string $tenantId): void
    {
        AssessmentSchedule::query()
            ->where('assessment_status', AssessmentStatus::Active)
            ->where('assessment_ends', '<=', now())
            ->get()
            ->each(function (AssessmentSchedule $schedule) use ($tenantId): void {
                $this->forceSubmitOpenAttempts($schedule, $tenantId);

                $schedule->complete();

                Log::info('Assessment auto-completed', [
                    'tenant_id' => $tenantId,
                    'schedule_id' => $schedule->id,
                ]);
            });
    }

    private function forceSubmitOpenAttempts(AssessmentSchedule $schedule, string $tenantId): void
    {
        $examIds = $schedule->submissions()
            ->whereNotNull('exam_id')
            ->pluck('exam_id');

        if ($examIds->isEmpty()) {
            return;
        }

        ExamAttempt::with('exam')
            ->whereIn('exam_id', $examIds)
            ->where('status', ExamAttemptStatus::InProgress->value)
            ->chunkById(100, function ($attempts) use ($tenantId): void {
                foreach ($attempts as $attempt) {
                    try {
                        $this->finalizeAttempt->execute($attempt, reason: 'stale_heartbeat');
                    } catch (\Throwable $e) {
                        Log::error('Force-submit on schedule completion failed', [
                            'tenant_id' => $tenantId,
                            'attempt_id' => $attempt->id,
                            'reason' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
