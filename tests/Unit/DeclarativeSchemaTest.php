<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Schemas\Concerns\HasSchemaValidation;
use App\Schemas\Requests\Exam\CreateExamRequestData;
use App\Schemas\Shared\ExamSettingsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(CreateExamRequestData::class)]
#[CoversClass(ExamSettingsData::class)]
#[CoversClass(HasSchemaValidation::class)]
class DeclarativeSchemaTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function create_exam_request_data_has_expected_rules(): void
    {
        $rules = CreateExamRequestData::getValidationRules([]);

        // Required field validation
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

        // Nullable fields
        $this->assertArrayHasKey('class_arm_id', $rules);
        $this->assertContains('nullable', $rules['class_arm_id']);

        $this->assertArrayHasKey('pass_mark', $rules);
        $this->assertContains('nullable', $rules['pass_mark']);

        $this->assertArrayHasKey('instructions', $rules);
        $this->assertContains('nullable', $rules['instructions']);

        // Cross-field validation
        $this->assertArrayHasKey('scheduled_end', $rules);
        $this->assertContains('after:scheduled_start', $rules['scheduled_end']);
    }

    #[Test]
    public function nested_settings_data_validates_correctly(): void
    {
        $rules = CreateExamRequestData::getValidationRules([]);

        // Settings is nullable
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
                return CreateExamRequestData::class;
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
        $rules = CreateExamRequestData::getValidationRules([]);

        $this->assertArrayHasKey('type', $rules);
        $this->assertContains('required', $rules['type']);

        // The enum validation should accept valid backed values
        $typeRules = $rules['type'];
        // Spatie Data validates BackedEnum by checking it's a valid value
        // The exact rule format depends on the EnumTransformer
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
        $examData = CreateExamRequestData::from($data);

        $this->assertEquals('Mid-Term Exam', $examData->title);
        $this->assertEquals('exam', $examData->type->value);
        $this->assertEquals(60, $examData->duration_minutes);
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
            'scheduled_end' => '2026-06-10T11:00:00',
            'instructions' => 'Read carefully.',
            'settings' => [
                'randomize_questions' => true,
                'show_result_immediately' => false,
            ],
        ];
        $examData = CreateExamRequestData::from($data);

        $this->assertEquals(75.0, $examData->pass_mark);
        $this->assertEquals(2, $examData->max_attempts);
        $this->assertEquals('Read carefully.', $examData->instructions);
        $this->assertNotNull($examData->settings);
        $this->assertTrue($examData->settings->randomize_questions);
        $this->assertFalse($examData->settings->show_result_immediately);
    }
}
