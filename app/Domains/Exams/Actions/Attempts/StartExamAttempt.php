<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Events\ExamSessionStateUpdated;
use App\Domains\Exams\Support\ExamAttemptLifecycleRules;
use App\Domains\Exams\Support\ExamSessionState;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class StartExamAttempt
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
    ) {}

    public function execute(Exam $exam, User $student): ExamAttempt
    {
        (ExamAttemptLifecycleRules::canStart($student))($exam);

        $lastAttemptNumber = $exam->attempts()->forStudent($student->id)->max('attempt_number') ?? 0;

        $attempt = DB::transaction(function () use ($exam, $student, $lastAttemptNumber) {
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'attempt_number' => $lastAttemptNumber + 1,
                'status' => ExamAttemptStatus::InProgress->value,
                'started_at' => now(),
            ]);

            $this->seedSessionState($attempt);

            return $attempt;
        });

        return $attempt;
    }

    /** Write initial Redis state + broadcast so frontend clock starts immediately */
    public function seedSessionState(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant('id');
        $remaining = $attempt->getTimeRemainingSeconds();
        $ttl = $attempt->exam->duration_minutes * 60;

        $this->stateStore->write(
            new ExamSessionState(
                tenantId: $tenantId,
                attemptId: $attempt->id,
                timeRemainingSeconds: $remaining,
                connectionAlive: true,
            ),
            $ttl,
        );

        event(new ExamSessionStateUpdated(
            attemptId: $attempt->id,
            tenantId: $tenantId,
            timeRemainingSeconds: $remaining,
            connectionAlive: true,
        ));
    }
}
