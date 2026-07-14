<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Import\Data\Schemas\TeacherImportSchema;
use Tests\TestCase;

class TeacherImportSchemaTest extends TestCase
{
    public function test_required_headers(): void
    {
        $required = TeacherImportSchema::requiredHeaders();
        $this->assertContains('first_name', $required);
        $this->assertContains('last_name', $required);
        $this->assertContains('email', $required);
    }

    public function test_all_headers_includes_optional(): void
    {
        $all = TeacherImportSchema::allHeaders();
        $this->assertContains('phone', $all);
        $this->assertContains('qualification', $all);
        $this->assertContains('staff_id', $all);
    }

    public function test_missing_required_headers_detected(): void
    {
        $missing = TeacherImportSchema::missingRequiredHeaders(['first_name']);
        $this->assertContains('last_name', $missing);
        $this->assertContains('email', $missing);
    }

    public function test_validator_rules(): void
    {
        $rules = TeacherImportSchema::validatorRules();

        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('last_name', $rules);
        $this->assertArrayHasKey('email', $rules);

        $this->assertContains('required', $rules['first_name']);
        $this->assertContains('required', $rules['email']);
    }

    public function test_identity_fields(): void
    {
        $this->assertContains('email', TeacherImportSchema::IDENTITY);
        $this->assertContains('staff_id', TeacherImportSchema::IDENTITY);
    }
}
