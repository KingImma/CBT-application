<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by || $user->hasRole('school_admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('teacher') || $user->hasRole('school_admin');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by && $exam->status === 'draft';
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by && $exam->status === 'draft';
    }

    public function publish(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by && $exam->status === 'draft';
    }

    public function startSession(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by && $exam->status === 'scheduled';
    }

    public function viewMonitoring(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by || $user->hasRole('school_admin');
    }

    public function manageQuestions(User $user, Exam $exam): bool
    {
        return $user->id === $exam->created_by && in_array($exam->status, ['draft', 'scheduled']);
    }
}
