<?php

namespace Tests\Feature\Api\Tenant;

use Tests\TestCase;

class ExamModelTest extends TestCase
{
    public function test_exam_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\Exam::class));
        $this->assertTrue(method_exists(\App\Models\Tenant\Exam::class, 'examQuestions'));
        $this->assertTrue(method_exists(\App\Models\Tenant\Exam::class, 'attempts'));
        $this->assertTrue(method_exists(\App\Models\Tenant\Exam::class, 'attendanceRecords'));
        $this->assertTrue(method_exists(\App\Models\Tenant\Exam::class, 'topics'));
    }

    public function test_exam_attempt_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\ExamAttempt::class));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttempt::class, 'exam'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttempt::class, 'student'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttempt::class, 'answers'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttempt::class, 'getTimeRemainingSeconds'));
    }

    public function test_exam_answer_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\ExamAnswer::class));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAnswer::class, 'attempt'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAnswer::class, 'question'));
    }

    public function test_exam_question_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\ExamQuestion::class));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamQuestion::class, 'exam'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamQuestion::class, 'question'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamQuestion::class, 'getEffectiveMarks'));
    }

    public function test_exam_attendance_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(\App\Models\Tenant\ExamAttendance::class));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttendance::class, 'exam'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttendance::class, 'student'));
        $this->assertTrue(method_exists(\App\Models\Tenant\ExamAttendance::class, 'markedBy'));
    }
}
