<?php

declare(strict_types=1);

namespace App\Domains\Exams\Policies;

use App\Enums\ExamAttemptStatus;
use App\Enums\RoleType;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamAnswerPolicy
{
    use HandlesAuthorization;

    public function view(User $user, ExamAnswer $answer): bool
    {
        $attempt = $answer->attempt;

        return $user->id === $attempt->student_id
            || $user->id === $attempt->exam->created_by
            || $user->hasAnyRole(['admin', RoleType::SchoolAdmin->value]);
    }

    public function update(User $user, ExamAnswer $answer): bool
    {
        $attempt = $answer->attempt;

        return $user->id === $attempt->exam->created_by && in_array($attempt->status, [ExamAttemptStatus::Submitted->value, ExamAttemptStatus::Grading->value]);
    }
}
