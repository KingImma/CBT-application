<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Tenancy\Support\HasSchemaValidation;
use App\Domains\Exams\Data\Input\CreateExamData;
use App\Domains\Exams\Data\Input\ExamSettingsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateExamData::class)]
#[CoversClass(ExamSettingsData::class)]
#[CoversClass(HasSchemaValidation::class)]
class DeclarativeSchemaTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function create_exam_data_has_expected_rules(): void
    {
        $rules = CreateExamData::getValidationRules([]);

        $this->assertArrayHasKey('title', $rules);
        $this->assertContains('required', $rules['title']);
        $this->assertContains('string', $rules['title']);
        $this->assertContains('max:255', $rules['title']);

        $this->assertArrayHasKey('subject_id', $rules);
        $this->assertContains('required', $rules['subject_id']);
        $this->assertContains('uuid', $rules['subject_id']);

        $this->assertArrayHasKey('duration_minutes', $rules);
        $this->assertContains('required', $rules['duration_minutes']);
        $this->assertContains('integer', $rules['duration_minutes']);
        $this->assertContains('min:1', $rules['duration_minutes']);

        $this->assertArrayHasKey('class_arm_id', $rules);
        $this->assertContains('nullable', $rules['class_arm_id']);

        $this->assertArrayHasKey('pass_mark', $rules);
        $this->assertContains('nullable', $rules['pass_mark']);

        $this->assertArrayHasKey('instructions', $rules);
        $this->assertContains('nullable', $rules['instructions']);
    }

    #[Test]
    public function nested_settings_data_validates_correctly(): void
    {
        $rules = CreateExamData::getValidationRules([]);

        $this->assertArrayHasKey('settings', $rules);
        $this->assertContains('nullable', $rules['settings']);
    }

    #[Test]
    public function has_schema_validation_trait_integrates_with_form_request(): void
    {
        $request = new class extends FormRequest
        {
            use HasSchemaValidation;

            protected function schemaClass(): string
            {
                return CreateExamData::class;
            }
        };

        $rules = $request->rules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertContains('required', $rules['title']);
        $this->assertContains('string', $rules['title']);
    }

    #[Test]
    public function exam_type_enum_is_validated(): void
    {
        $rules = CreateExamData::getValidationRules([]);

        $this->assertArrayHasKey('type', $rules);
        $this->assertContains('required', $rules['type']);
    }

    #[Test]
    public function create_exam_data_can_be_hydrated(): void
    {
        $data = [
            'title' => 'Mid-Term Exam',
            'subject_id' => '00000000-0000-0000-0000-000000000001',
            'class_level_id' => '00000000-0000-0000-0000-000000000002',
            'term_id' => '00000000-0000-0000-0000-000000000003',
            'type' => 'exam',
            'duration_minutes' => 60,
        ];
        $examData = CreateExamData::from($data);

        $this->assertEquals('Mid-Term Exam', $examData->getTitle());
        $this->assertEquals('exam', $examData->getType()->value);
        $this->assertEquals(60, $examData->getDurationMinutes());
    }

    #[Test]
    public function create_exam_data_with_optional_fields(): void
    {
        $data = [
            'title' => 'Final Exam',
            'subject_id' => '00000000-0000-0000-0000-000000000001',
            'class_level_id' => '00000000-0000-0000-0000-000000000002',
            'term_id' => '00000000-0000-0000-0000-000000000003',
            'type' => 'exam',
            'duration_minutes' => 90,
            'pass_mark' => 75.0,
            'max_attempts' => 2,
            'scheduled_start' => '2026-06-10T09:00:00',
            'instructions' => 'Read carefully.',
            'settings' => [
                'randomize_questions' => true,
                'show_result_immediately' => false,
            ],
        ];
        $examData = CreateExamData::from($data);

        $this->assertEquals(75.0, $examData->getPassMark());
        $this->assertEquals(2, $examData->getMaxAttempts());
        $this->assertEquals('Read carefully.', $examData->getInstructions());
        $this->assertNotNull($examData->getSettings());
        $this->assertTrue($examData->getSettings()->getRandomizeQuestions());
        $this->assertFalse($examData->getSettings()->getShowResultImmediately());
    }
}
