<?php

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Tests\TestCase;

class ExamFullFlowTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        try {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        } catch (\Exception) {
            // Ignore cleanup failures.
        }
        parent::tearDown();
    }

    public function test_exam_creation_and_publishing_flow(): void
    {
        // Create admin
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'school_admin',
            'is_active' => true,
        ]);
        $admin->assignRole('school_admin');
        $this->actingAs($admin, 'tenant');

        // Create required related models
        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

        $academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $academicSession->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(3),
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
        $this->assertEquals(ExamStatus::Draft, $exam->status);

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
