<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\SuperAdmin;
use App\Models\Tenant\User;
use App\Models\Tenant\ExamAttempt;

// Super admin activity feed
Broadcast::channel('super-admin.activity', function ($user) {
     return $user instanceof SuperAdmin && $user->is_active;
});

// School admin activity feed
Broadcast::channel('school-admin.{tenantId}.activity', function ($user, string $tenantId) {
    return $user instanceof User
        && $user->hasRole('school_admin')
        && (string) tenant('id') === $tenantId;
});

// Teacher activity feed
Broadcast::channel('teacher.{teacherId}.activity', function ($user, $teacherId) {
    return $user instanceof User
        && $user->hasRole('teacher')
        && (string) $user->id === (string) $teacherId;
});

// Exam session channel — single channel per exam, all subscribed students receive session.started / session.ended
// Auth: must be a student with an in-progress attempt for this exam
Broadcast::channel('school.{tenantId}.exam.{examId}', function ($user, $tenantId, $examId) {
    if ((string) tenant('id') !== (string) $tenantId) {
        return false;
    }

    if (! ($user instanceof User) || ! $user->hasRole('student')) {
        return false;
    }

    return ExamAttempt::where('exam_id', $examId)
        ->where('student_id', $user->id)
        ->where('status', 'in_progress')
        ->exists();
});
