<?php

namespace Tests\Feature\Api\Tenant;

use App\Models\Tenant\Exam;
use Tests\TestCase;

class ExamFullFlowTest extends TestCase
{
    public function test_exam_creation_and_publishing_flow(): void
    {
        // Create teacher
        $teacher = \App\Models\Tenant\User::create([
            'name' => 'Teacher One',
            'email' => 'teacher1@test.com',
            'password' => bcrypt('password'),
        ]);
        $teacher->assignRole('teacher');
        $this->actingAs($teacher, 'tenant');

        // Create required related models
        $subject = \App\Models\Tenant\Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $classLevel = \App\Models\Tenant\ClassLevel::create([
            'name' => 'Grade 10',
        ]);

        $academicSession = \App\Models\Tenant\AcademicSession::create([
            'name' => '2025/2026',
        ]);

        $term = \App\Models\Tenant\Term::create([
            'name' => 'First Term',
            'academic_session_id' => $academicSession->id,
            'is_current' => true,
        ]);

        // 1. Create Exam (draft)
        $response = $this->postJson('/api/exams', [
            'title' => 'Mid-Term Mathematics Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'type' => 'exam',
            'duration_minutes' => 120,
            'pass_mark' => 50.00,
        ]);

        $response->assertStatus(201);
        $examId = $response->json('data.id');
        $this->assertEquals('draft', $response->json('data.status'));

        // 2. Verify exam exists
        $exam = Exam::find($examId);
        $this->assertNotNull($exam);
        $this->assertEquals('draft', $exam->status);

        // 3. Try to publish (should fail - no questions)
        $response = $this->postJson("/api/exams/{$examId}/publish");
        $response->assertStatus(422);

        // 4. Get exam
        $response = $this->getJson("/api/exams/{$examId}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'draft');

        // 5. List exams
        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
