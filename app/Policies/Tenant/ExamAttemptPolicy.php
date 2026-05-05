<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamAttemptPolicy
{
    use HandlesAuthorization;

    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id || $user->id === $attempt->exam->created_by || $user->hasRole('admin');
    }

    public function start(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id && $attempt->status === 'in_progress';
    }

    public function submit(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id && $attempt->status === 'in_progress';
    }

    public function saveAnswer(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id && $attempt->status === 'in_progress';
    }

    public function grade(User $user, ExamAttempt $attempt): bool
    {
        return $user->id === $attempt->exam->created_by || $user->hasRole('admin');
    }
}
