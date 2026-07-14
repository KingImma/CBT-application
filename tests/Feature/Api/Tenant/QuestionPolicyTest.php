<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Questions\Policies\QuestionPolicy;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function assigned_class_teacher_can_create_questions_for_their_class_level(): void
    {
        $classLevel = ClassLevel::create(['name' => 'Grade 1']);

        $teacher = User::create([
            'first_name' => 'Alice',
            'last_name' => 'Teacher',
            'email' => 'alice.teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        ClassArm::create([
            'class_level_id' => $classLevel->id,
            'name' => 'Arm A',
            'capacity' => 30,
            'assigned_teacher_id' => $teacher->id,
        ]);

        $policy = new QuestionPolicy;

        $this->assertTrue($policy->createForClass($teacher, $classLevel->id));
    }

    #[Test]
    public function non_assigned_teacher_cannot_create_questions_for_other_class_levels(): void
    {
        $classLevel = ClassLevel::create(['name' => 'Grade 1']);

        $assignedTeacher = User::create([
            'first_name' => 'Alice',
            'last_name' => 'Teacher',
            'email' => 'alice.teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $otherTeacher = User::create([
            'first_name' => 'Bob',
            'last_name' => 'Teacher',
            'email' => 'bob.teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        ClassArm::create([
            'class_level_id' => $classLevel->id,
            'name' => 'Arm A',
            'capacity' => 30,
            'assigned_teacher_id' => $assignedTeacher->id,
        ]);

        $policy = new QuestionPolicy;

        $this->assertFalse($policy->createForClass($otherTeacher, $classLevel->id));
    }
}
