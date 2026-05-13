<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\SuperAdmin;
use App\Models\Tenant\User;
use App\Models\Tenant\Exam;

Broadcast::channel('super-admin.activity', function($user) {
     return $user instanceof SuperAdmin && $user->is_active;
}); 

Broadcast::channel('school-admin.{tenantId}.activity', function ($user, string $tenantId) {
    return $user instanceof User
        && $user->hasRole('school_admin')
        && (string) tenant('id') === $tenantId;
});

// FIX: Changed {teacher.id} to {teacherId}
Broadcast::channel('teacher.{teacherId}.activity', function ($user, $teacherId) {
    return $user instanceof User
        && $user->hasRole('teacher')
        && (string) $user->id === (string) $teacherId; // FIX: Cast to string for safe comparison
});

// Exam monitoring - teacher sees their exam's students activity
Broadcast::channel('teacher.{teacherId}.exam.{examId}', function ($user, $teacherId, $examId) {
    // NOTE: Verify if this should be 'school_admin' instead of 'admin'
    if ((string) $user->id !== (string) $teacherId && ! $user->hasRole('admin')) {
        return false;
    }
    
    $exam = Exam::find($examId);
    
    return $exam !== null && ((string) $user->id === (string) $exam->created_by || $user->hasRole('admin'));
});

// Exam attempt - student sees their own session
Broadcast::channel('student.{studentId}.exam.{examId}', function ($user, $studentId, $examId) {
    return (string) $user->id === (string) $studentId; // FIX: Cast to string for safe comparison
});