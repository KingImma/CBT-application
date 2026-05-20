<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

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
            || $user->hasAnyRole(['admin', 'school_admin']);
    }

    public function update(User $user, ExamAnswer $answer): bool
    {
        $attempt = $answer->attempt;

        return $user->id === $attempt->exam->created_by && in_array($attempt->status, ['submitted', 'grading']);
    }
}
