<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CsvHeaderNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsvHeaderNormalizerTest extends TestCase
{
    #[Test]
    public function exact_snake_case_passes_through(): void
    {
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('first_name'));
        $this->assertEquals('last_name', CsvHeaderNormalizer::normalize('last_name'));
        $this->assertEquals('email', CsvHeaderNormalizer::normalize('email'));
        $this->assertEquals('phone', CsvHeaderNormalizer::normalize('phone'));
        $this->assertEquals('staff_id', CsvHeaderNormalizer::normalize('staff_id'));
        $this->assertEquals('admission_number', CsvHeaderNormalizer::normalize('admission_number'));
        $this->assertEquals('class_level', CsvHeaderNormalizer::normalize('class_level'));
        $this->assertEquals('class_arm', CsvHeaderNormalizer::normalize('class_arm'));
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('date_of_birth'));
        $this->assertEquals('guardian_email', CsvHeaderNormalizer::normalize('guardian_email'));
    }

    #[Test]
    public function title_case_with_spaces_normalizes(): void
    {
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('First Name'));
        $this->assertEquals('last_name', CsvHeaderNormalizer::normalize('Last Name'));
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('Date of Birth'));
        $this->assertEquals('guardian_email', CsvHeaderNormalizer::normalize('Guardian Email'));
        $this->assertEquals('staff_id', CsvHeaderNormalizer::normalize('Staff ID'));
    }

    #[Test]
    public function all_caps_normalizes(): void
    {
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('FIRST_NAME'));
        $this->assertEquals('email', CsvHeaderNormalizer::normalize('EMAIL'));
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('DATE OF BIRTH'));
        $this->assertEquals('admission_number', CsvHeaderNormalizer::normalize('ADMISSION NUMBER'));
    }

    #[Test]
    public function common_synonyms_normalize(): void
    {
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('firstname'));
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('Given Name'));
        $this->assertEquals('last_name', CsvHeaderNormalizer::normalize('surname'));
        $this->assertEquals('last_name', CsvHeaderNormalizer::normalize('Family Name'));
        $this->assertEquals('email', CsvHeaderNormalizer::normalize('Email Address'));
        $this->assertEquals('email', CsvHeaderNormalizer::normalize('E-mail'));
        $this->assertEquals('phone', CsvHeaderNormalizer::normalize('Phone Number'));
        $this->assertEquals('phone', CsvHeaderNormalizer::normalize('Telephone'));
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('DOB'));
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('Birth Date'));
        $this->assertEquals('admission_number', CsvHeaderNormalizer::normalize('Admission No'));
        $this->assertEquals('admission_number', CsvHeaderNormalizer::normalize('Admission #'));
        $this->assertEquals('admission_number', CsvHeaderNormalizer::normalize('Adm No'));
        $this->assertEquals('class_level', CsvHeaderNormalizer::normalize('class'));
        $this->assertEquals('class_level', CsvHeaderNormalizer::normalize('Grade'));
        $this->assertEquals('class_arm', CsvHeaderNormalizer::normalize('arm'));
        $this->assertEquals('class_arm', CsvHeaderNormalizer::normalize('Section'));
        $this->assertEquals('guardian_email', CsvHeaderNormalizer::normalize('Parent Email'));
        $this->assertEquals('staff_id', CsvHeaderNormalizer::normalize('Employee ID'));
        $this->assertEquals('staff_id', CsvHeaderNormalizer::normalize('Employee Id'));
        $this->assertEquals('qualification', CsvHeaderNormalizer::normalize('qualifications'));
        $this->assertEquals('qualification', CsvHeaderNormalizer::normalize('Highest Qualification'));
    }

    #[Test]
    public function hyphens_and_dots_normalize_to_underscores(): void
    {
        $this->assertEquals('date_of_birth', CsvHeaderNormalizer::normalize('date-of-birth'));
        $this->assertEquals('guardian_email', CsvHeaderNormalizer::normalize('guardian.email'));
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('first.name'));
    }

    #[Test]
    public function surrounding_whitespace_is_stripped(): void
    {
        $this->assertEquals('first_name', CsvHeaderNormalizer::normalize('  First Name  '));
        $this->assertEquals('email', CsvHeaderNormalizer::normalize("\tEmail\n"));
    }

    #[Test]
    public function unrecognized_header_returns_null(): void
    {
        $this->assertNull(CsvHeaderNormalizer::normalize('random_column'));
        $this->assertNull(CsvHeaderNormalizer::normalize('Some Random Header'));
        $this->assertNull(CsvHeaderNormalizer::normalize(''));
    }

    #[Test]
    public function normalize_headers_filters_unknowns(): void
    {
        $input = ['First Name', 'EMAIL', 'random_col', 'Date of Birth', 'bogus'];

        $result = CsvHeaderNormalizer::normalizeHeaders($input);

        $this->assertEquals(['first_name', 'email', 'date_of_birth'], $result);
    }

    #[Test]
    public function normalize_headers_deduplicates(): void
    {
        $input = ['Email', 'email', 'E-mail'];

        $result = CsvHeaderNormalizer::normalizeHeaders($input);

        $this->assertEquals(['email', 'email', 'email'], $result);
    }
}
