<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Seb;

use App\Domains\Exams\Exceptions\SebAttemptsExhaustedException;
use App\Domains\Exams\Exceptions\SebExamNotLiveException;
use App\Domains\Exams\Exceptions\SebNotEnrolledException;
use App\Domains\Exams\Support\SebLaunchTokenStore;
use App\Domains\Exams\Support\SebPendingSessionStore;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;

final class StartSebSession
{
    public function __construct(
        private SebPendingSessionStore $sessionStore,
        private SebLaunchTokenStore $tokenStore,
    ) {}

    /** @return array{examId: string, launchToken: string} */
    public function execute(Exam $exam, User $student): array
    {
        $this->assertLive($exam);
        $this->assertEnrolled($exam, $student);
        $this->assertAttemptsRemaining($exam, $student);

        $this->sessionStore->write($student->id, $exam->id);
        $launchToken = $this->tokenStore->issue($student->id, tenant('id'));

        return [
            'examId' => $exam->id,
            'launchToken' => $launchToken,
        ];
    }

    private function assertLive(Exam $exam): void
    {
        $isLive = $exam->status === ExamStatus::Active
            && $exam->scheduled_start !== null
            && now()->gte($exam->scheduled_start)
            && ($exam->window_end === null || now()->lt($exam->window_end));

        throw_unless($isLive, SebExamNotLiveException::class);
    }

    private function assertEnrolled(Exam $exam, User $student): void
    {
        $profile = $student->studentProfile;

        $enrolled = $profile !== null
            && $profile->class_level_id === $exam->class_level_id
            && ($exam->class_arm_id === null || $exam->class_arm_id === $profile->class_arm_id);

        throw_unless($enrolled, SebNotEnrolledException::class);
    }

    private function assertAttemptsRemaining(Exam $exam, User $student): void
    {
        $lastAttemptNumber = $exam->attempts()->forStudent($student->id)->max('attempt_number') ?? 0;

        throw_if(
            $lastAttemptNumber >= ($exam->max_attempts ?? 1),
            SebAttemptsExhaustedException::class,
        );
    }
}
