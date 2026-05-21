<?php

namespace Tests\Feature\Api\Tenant;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamAttendance;
use App\Models\Tenant\ExamQuestion;
use Tests\TestCase;

class ExamModelTest extends TestCase
{
    public function test_exam_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(Exam::class));
        $this->assertTrue(method_exists(Exam::class, 'examQuestions'));
        $this->assertTrue(method_exists(Exam::class, 'attempts'));
        $this->assertTrue(method_exists(Exam::class, 'attendanceRecords'));
    }

    public function test_exam_attempt_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(ExamAttempt::class));
        $this->assertTrue(method_exists(ExamAttempt::class, 'exam'));
        $this->assertTrue(method_exists(ExamAttempt::class, 'student'));
        $this->assertTrue(method_exists(ExamAttempt::class, 'answers'));
        $this->assertTrue(method_exists(ExamAttempt::class, 'getTimeRemainingSeconds'));
    }

    public function test_exam_answer_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(ExamAnswer::class));
        $this->assertTrue(method_exists(ExamAnswer::class, 'attempt'));
        $this->assertTrue(method_exists(ExamAnswer::class, 'question'));
    }

    public function test_exam_question_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(ExamQuestion::class));
        $this->assertTrue(method_exists(ExamQuestion::class, 'exam'));
        $this->assertTrue(method_exists(ExamQuestion::class, 'question'));
        $this->assertTrue(method_exists(ExamQuestion::class, 'getEffectiveMarks'));
    }

    public function test_exam_attendance_model_has_relationships(): void
    {
        $this->assertTrue(class_exists(ExamAttendance::class));
        $this->assertTrue(method_exists(ExamAttendance::class, 'exam'));
        $this->assertTrue(method_exists(ExamAttendance::class, 'student'));
        $this->assertTrue(method_exists(ExamAttendance::class, 'markedBy'));
    }
}
