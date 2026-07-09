<?php

use App\Models\SuperAdmin;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Broadcast;

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

// Real-time exam attempt count updates
Broadcast::channel('school-admin.{tenantId}.exam.{examId}', function ($user, string $tenantId, string $examId) {
    return $user instanceof User
        && $user->hasRole('school_admin')
        && (string) tenant('id') === $tenantId;
});

// Live exam session state for students
Broadcast::channel('exam-session.{attemptId}', function ($user, string $attemptId) {
    if (! $user instanceof User) {
        return false;
    }

    $attempt = ExamAttempt::find($attemptId);

    return $attempt !== null
        && (string) $user->id === (string) $attempt->student_id;
});

// Tenant Users (Admin, Teacher, Student)
Broadcast::channel('tenant.{tenantId}.users.{userId}', function (User $user, string $tenantId, string $userId) {
    return (string) tenant('id') === $tenantId && (string) $user->id === $userId;
});

// Super Admins
Broadcast::channel('superadmin.{adminId}', function (SuperAdmin $admin, string $adminId) {
    return (string) $admin->id === $adminId;
});
