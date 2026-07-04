<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Actions\Base\CreateAction;
use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Events\ExamSessionStateUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\Exam\ExamSessionState;
use App\Support\Exam\ExamSessionStateStore;

final class StartExamAttempt
{
  public function __construct(
    private CreateAction          $action,
    private ExamSessionStateStore $stateStore,
  ){}

  public function execute(Exam $exam, User $student): ExamAttempt
  {
    (ExamAttemptGuards::canStart($student))($exam);

    $lastAttemptNumber = $exam->attempts()->forStudent($student->id)->max('attempt_number') ?? 0;

    /** @var ExamAttempt $attempt */
    $attempt = $this->action->execute(
      ExamAttempt::class,
      ['exam' => $exam, 'student' => $student, 'last' => $lastAttemptNumber],
      prepare: fn (array $d) => [
        'exam_id'        => $d['exam']->id,
        'student_id'     => $d['student']->id,
        'attempt_number' => $d['last'] + 1,
        'status'         => ExamAttemptStatus::InProgress->value,
        'started_at'     => now(),
      ],
      after: fn (ExamAttempt $attempt, array $d) => $this->seedSessionState($attempt),
    );

    return $attempt;
  }


  /** Write initial Redis state + broadcast so frontend clock starts immediately */
  public function seedSessionState(ExamAttempt $attempt): void
  {
    $tenantId  = (string) tenant('id');
    $remaining = $attempt->getTimeRemainingSeconds();
    $ttl       = $attempt->exam->duration_minutes * 60;

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
