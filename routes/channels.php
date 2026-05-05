<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('teacher.{teacherId}.exam.{examId}', function ($user, $teacherId, $examId) {
    if ($user->id !== $teacherId && ! $user->hasRole('admin')) {
        return false;
    }
    
    $exam = \App\Models\Tenant\Exam::find($examId);
    return $exam !== null && ($user->id === $exam->created_by || $user->hasRole('admin'));
});

Broadcast::channel('student.{studentId}.exam.{examId}', function ($user, $studentId, $examId) {
    return $user->id === $studentId;
});
