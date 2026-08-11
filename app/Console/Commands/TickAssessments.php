<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Domains\Exams\Actions\Attempts\FinalizeAttempt;
use App\Enums\AssessmentStatus;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Drives the assessment clock across every active tenant. Three idempotent,
 * time-driven flips (decision #6 — scheduled activation):
 *   open              -> submissions_closed   once submission_closes_at passes
 *   submissions_closed -> active              once student_starts_at opens the window
 *   active            -> completed            once student_ends_at passes
 * The queries run in order so a just-closed assessment whose start time has
 * already arrived can activate in the same tick. Guard failures (e.g. a window
 * that closed with zero approved submissions) are logged and skipped, never
 * fatal — the run must survive per-tenant and per-row hiccups.
 */
class TickAssessments extends Command
{
    protected $signature = 'assessments:tick';

    protected $description = 'Advance assessment lifecycles (close submissions, activate, complete) across all tenants.';

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
        $this->closeExpiredSubmissionWindows($tenantId);
        $this->activateScheduledAssessments($tenantId);
        $this->completeFinishedAssessments($tenantId);
    }

    /** open -> submissions_closed once the teacher window has passed. */
    private function closeExpiredSubmissionWindows(string $tenantId): void
    {
        Assessment::query()
            ->where('status', AssessmentStatus::Open)
            ->where('submission_closes_at', '<=', now())
            ->get()
            ->each(function (Assessment $assessment) use ($tenantId): void {
                $assessment->closeSubmissions();

                Log::info('Assessment submissions auto-closed', [
                    'tenant_id' => $tenantId,
                    'assessment_id' => $assessment->id,
                ]);
            });
    }

    /**
     * submissions_closed -> active once the student window opens. Activation
     * materialises the exams; a guard rejection (no approved submissions, invalid
     * window) leaves the assessment closed for an admin to reopen or fix.
     */
    private function activateScheduledAssessments(string $tenantId): void
    {
        Assessment::query()
            ->where('status', AssessmentStatus::SubmissionsClosed)
            ->where('student_starts_at', '<=', now())
            ->where('student_ends_at', '>', now())
            ->get()
            ->each(function (Assessment $assessment) use ($tenantId): void {
                try {
                    $this->activate->execute($assessment);

                    Log::info('Assessment auto-activated', [
                        'tenant_id' => $tenantId,
                        'assessment_id' => $assessment->id,
                    ]);
                } catch (AssessmentStateTransitionException $e) {
                    Log::warning('Assessment auto-activation skipped', [
                        'tenant_id' => $tenantId,
                        'assessment_id' => $assessment->id,
                        'reason' => $e->getMessage(),
                    ]);
                }
            });
    }

    /**
     * active -> completed once the student window has passed. Any attempt still
     * in progress on a materialised paper is force-finalised through the existing
     * timeout path (no actor, no re-grade of a partial paper).
     */
    private function completeFinishedAssessments(string $tenantId): void
    {
        Assessment::query()
            ->where('status', AssessmentStatus::Active)
            ->where('student_ends_at', '<=', now())
            ->get()
            ->each(function (Assessment $assessment) use ($tenantId): void {
                $this->forceSubmitOpenAttempts($assessment, $tenantId);

                $assessment->complete();

                Log::info('Assessment auto-completed', [
                    'tenant_id' => $tenantId,
                    'assessment_id' => $assessment->id,
                ]);
            });
    }

    private function forceSubmitOpenAttempts(Assessment $assessment, string $tenantId): void
    {
        $examIds = $assessment->submissions()
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
                        Log::error('Force-submit on assessment completion failed', [
                            'tenant_id' => $tenantId,
                            'attempt_id' => $attempt->id,
                            'reason' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
