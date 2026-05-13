<?php

namespace Tests\Feature\Api\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use Tests\TestCase;

class ExamCreationTest extends TestCase
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
        $this->tenant->database()->drop();
        parent::tearDown();
    }

    public function test_can_create_exam(): void
    {
        $teacher = User::create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
        ]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher, 'tenant');

        // Create subject, class level, term first
        $subject = \App\Models\Tenant\Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $classLevel = \App\Models\Tenant\ClassLevel::create([
            'name' => 'Grade 10',
        ]);

        $term = \App\Models\Tenant\Term::create([
            'name' => 'First Term',
            'academic_session_id' => \App\Models\Tenant\AcademicSession::create([
                'name' => '2025/2026',
                'is_current' => true,
            ])->id,
            'is_current' => true,
        ]);

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
        $response->assertJsonPath('data.status', 'draft');
    }

    public function test_can_list_exams(): void
    {
        $teacher = User::create([
            'name' => 'John Doe',
            'email' => 'john2@test.com',
            'password' => bcrypt('password'),
        ]);
        $teacher->assignRole('teacher');

        $this->actingAs($teacher, 'tenant');

        Exam::create([
            'title' => 'Test Exam',
            'subject_id' => \App\Models\Tenant\Subject::create(['name' => 'English', 'code' => 'ENG101'])->id,
            'class_level_id' => \App\Models\Tenant\ClassLevel::create(['name' => 'Grade 11'])->id,
            'term_id' => \App\Models\Tenant\Term::create([
                'name' => 'Second Term',
                'academic_session_id' => \App\Models\Tenant\AcademicSession::create([
                    'name' => '2025/2026',
                    'is_current' => true,
                ])->id,
                'is_current' => true,
            ])->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'total_marks' => 0,
            'created_by' => $teacher->id,
        ]);

        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
