<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Events\ExamSessionStateUpdated;
use App\Domains\Exams\Support\ExamSessionState;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StartExamAttempt
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
    ) {}

    public function execute(Exam $exam, User $student): ExamAttempt
    {
        $this->assertCanStart($exam, $student);

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

    /**
     * Exam-eligibility guard: the exam is active and within its window, the
     * student has no in-progress attempt and has not exceeded max attempts,
     * and the student account is active. This guards the Exam/student, not an
     * attempt's own status, so it lives here rather than in the state machine.
     */
    private function assertCanStart(Exam $exam, User $student): void
    {
        throw_unless($exam->status === ExamStatus::Active, new RuntimeException('Exam is not active'));
        throw_unless($exam->scheduled_start !== null, new RuntimeException('Exam has no scheduled start time'));
        throw_if(now()->lt($exam->scheduled_start), new RuntimeException(
            'Exam has not started yet. Available at '.$exam->scheduled_start->toIso8601String()
        ));
        throw_if(
            $exam->window_end !== null && now()->gte($exam->window_end),
            new RuntimeException('The exam window has closed.')
        );
        throw_if(
            $exam->attempts()->forStudent($student->id)->inProgress()->exists(),
            new RuntimeException('You already have an active exam attempt.')
        );

        $last = $exam->attempts()->forStudent($student->id)->max('attempt_number');
        throw_if(
            $last !== null && $last >= ($exam->max_attempts ?? 1),
            new RuntimeException('Maximum attempts exceeded')
        );
        throw_unless($student->is_active, new RuntimeException('Student account is not active.'));
    }
}
