<?php

namespace Tests\Unit;

use App\Domains\Exams\Exceptions\AttemptCannotBeSubmittedException;
use App\Domains\Exams\Support\ExamAttemptLifecycleRules;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAttempt;
use Tests\TestCase;

class ExamAttemptGuardsTest extends TestCase
{
    public function test_can_submit_guard_fails_with_intended_exception_when_actor_is_null(): void
    {
        $attempt = new ExamAttempt([
            'student_id' => 'student-1',
        ]);
        $attempt->status = ExamAttemptStatus::InProgress;

        $guard = ExamAttemptLifecycleRules::canSubmit(null);

        $this->expectException(AttemptCannotBeSubmittedException::class);
        $this->expectExceptionMessage('Exam time has expired.');

        $guard($attempt);
    }
}
