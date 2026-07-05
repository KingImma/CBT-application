<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\Schemas\StudentImportSchema;
use Tests\TestCase;

class StudentImportSchemaTest extends TestCase
{
    public function test_required_headers(): void
    {
        $required = StudentImportSchema::requiredHeaders();
        $this->assertContains('first_name', $required);
        $this->assertContains('last_name', $required);
        $this->assertContains('class_level', $required);
        $this->assertContains('class_arm', $required);
    }

    public function test_all_headers_includes_optional(): void
    {
        $all = StudentImportSchema::allHeaders();
        $this->assertContains('email', $all);
        $this->assertContains('phone', $all);
        $this->assertContains('admission_number', $all);
        $this->assertContains('date_of_birth', $all);
        $this->assertContains('gender', $all);
        $this->assertContains('guardian_email', $all);
    }

    public function test_missing_required_headers_detected(): void
    {
        $missing = StudentImportSchema::missingRequiredHeaders(['first_name', 'email']);
        $this->assertContains('last_name', $missing);
        $this->assertContains('class_level', $missing);
        $this->assertContains('class_arm', $missing);
    }

    public function test_validator_rules(): void
    {
        $rules = StudentImportSchema::validatorRules();

        $this->assertArrayHasKey('first_name', $rules);
        $this->assertArrayHasKey('class_level', $rules);
        $this->assertArrayHasKey('class_arm', $rules);

        $this->assertContains('required', $rules['first_name']);
        $this->assertContains('required', $rules['class_level']);
    }

    public function test_identity_fields(): void
    {
        $this->assertContains('email', StudentImportSchema::IDENTITY);
        $this->assertContains('admission_number', StudentImportSchema::IDENTITY);
    }
}
