<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tenants\Exam\Attempts\GradeExamAttempt as GradeExamAttemptAction;
use App\Actions\Tenants\Exam\ExamAttemptGuards;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Queue transport for grading. Owns exactly two responsibilities the
 * synchronous Action must not know about:
 *
 *   1. Claiming the row — Submitted → Grading — under a row lock, so two
 *      queue workers racing on the same attempt can't both grade it.
 *   2. Recovering from a permanent failure — failed() only fires for a row
 *      genuinely stuck mid-grade, because step 1 is what makes "stuck
 *      in Grading" a real, reachable state instead of a state nothing
 *      ever writes.
 *
 * The scoring logic itself (QuestionGrader, CalculateScore, ResolveGrade)
 * stays entirely inside Actions\Tenants\Exam\GradeExamAttempt — this class
 * never touches a mark or a percentage.
 */
class GradeExamAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $attemptId,
        public readonly string $tenantId,
    ) {}

    public function handle(GradeExamAttemptAction $gradeAttempt): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning("GradeExamAttemptJob: tenant not found", [
                "tenant_id" => $this->tenantId,
                "attempt_id" => $this->attemptId,
            ]);

            return;
        }

        $tenant->run(function () use ($gradeAttempt) {
            // Claim the row first — this is the missing half of the pipeline.
            // Runs in its own short transaction so the lock is released before
            // the (potentially slower) grading transaction begins.
            $claimed = DB::transaction(function () {
                $attempt = ExamAttempt::where("id", $this->attemptId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null) {
                    return null;
                }

                $currentStatus = ExamAttemptStatus::tryFrom(
                    $attempt->status,
                );

                if (
                    $currentStatus !== ExamAttemptStatus::Submitted
                    && $currentStatus !== ExamAttemptStatus::Grading
                ) {
                    Log::debug("GradeExamAttemptJob: skipping attempt {$this->attemptId} — unexpected status", [
                        "status" => $attempt->status,
                    ]);

                    return null;
                }

                if ($currentStatus === ExamAttemptStatus::Submitted) {
                    $attempt->status = ExamAttemptStatus::Grading->value;
                    $attempt->save();
                }

                return $attempt;
            });

            if ($claimed === null) {
                return;
            }

            $gradeAttempt->execute($claimed->fresh());
        });
    }

    /**
     * Fires only after all retries are exhausted. Because handle() now
     * actually writes Grading before scoring begins, a row can genuinely
     * be found sitting in Grading here — meaning the process died mid-score
     * (DB timeout, OOM, etc.) rather than never having started.
     */
    public function failed(\Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($e) {
            DB::transaction(function () use ($e) {
                $attempt = ExamAttempt::where("id", $this->attemptId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null) {
                    return;
                }

                if ($attempt->status === ExamAttemptStatus::Grading->value) {
                    $attempt->status = ExamAttemptStatus::Failed->value;
                    $attempt->save();
                }

                Log::error("GradeExamAttemptJob failed permanently", [
                    "attempt_id" => $this->attemptId,
                    "tenant_id" => $this->tenantId,
                    "error" => $e->getMessage(),
                ]);
            });
        });
    }
}
