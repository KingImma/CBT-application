<?php

use App\Models\SuperAdmin;
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
