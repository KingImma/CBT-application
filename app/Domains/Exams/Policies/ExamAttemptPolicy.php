<?php

declare(strict_types=1);

namespace App\Domains\Exams\Policies;

use App\Enums\ExamAttemptStatus;
use App\Enums\RoleType;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamAttemptPolicy
{
    use HandlesAuthorization;

    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id
            || $user->id === $attempt->exam->created_by
            || $user->hasAnyRole(['admin', RoleType::SchoolAdmin->value]);
    }

    public function start(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id && $attempt->status === ExamAttemptStatus::InProgress->value;
    }

    public function submit(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id;
    }

    public function saveAnswer(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id && $attempt->status === ExamAttemptStatus::InProgress->value;
    }

    public function grade(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->exam->created_by || $user->hasAnyRole(['admin', RoleType::SchoolAdmin->value]);
    }
}
