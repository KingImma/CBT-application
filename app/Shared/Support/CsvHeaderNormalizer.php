<?php

declare(strict_types=1);

namespace App\Shared\Support;

class CsvHeaderNormalizer
{
    private const CANONICAL = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
        'phone' => 'phone',
        'qualification' => 'qualification',
        'staff_id' => 'staff_id',
        'admission_number' => 'admission_number',
        'class_level' => 'class_level',
        'class_arm' => 'class_arm',
        'date_of_birth' => 'date_of_birth',
        'gender' => 'gender',
        'guardian_email' => 'guardian_email',
    ];

    private const SYNONYMS = [
        'firstname' => 'first_name',
        'given_name' => 'first_name',
        'given name' => 'first_name',
        'givenname' => 'first_name',
        'surname' => 'last_name',
        'lastname' => 'last_name',
        'family_name' => 'last_name',
        'family name' => 'last_name',
        'familyname' => 'last_name',
        'emailaddress' => 'email',
        'email_address' => 'email',
        'email address' => 'email',
        'e-mail' => 'email',
        'e_mail' => 'email',
        'phone_number' => 'phone',
        'phone number' => 'phone',
        'phonenumber' => 'phone',
        'telephone' => 'phone',
        'contact' => 'phone',
        'dob' => 'date_of_birth',
        'dateofbirth' => 'date_of_birth',
        'birth_date' => 'date_of_birth',
        'birth date' => 'date_of_birth',
        'birthdate' => 'date_of_birth',
        'admission_no' => 'admission_number',
        'admission no' => 'admission_number',
        'admissionno' => 'admission_number',
        'adm_no' => 'admission_number',
        'adm no' => 'admission_number',
        'admno' => 'admission_number',
        'admission' => 'admission_number',
        'admission_number' => 'admission_number',
        'admission number' => 'admission_number',
        'admissionnumber' => 'admission_number',
        'admission_id' => 'admission_number',
        'admission id' => 'admission_number',
        'admissionid' => 'admission_number',
        'class' => 'class_level',
        'grade' => 'class_level',
        'grade_level' => 'class_level',
        'grade level' => 'class_level',
        'gradelevel' => 'class_level',
        'arm' => 'class_arm',
        'section' => 'class_arm',
        'class_section' => 'class_arm',
        'class section' => 'class_arm',
        'classsection' => 'class_arm',
        'guardianemail' => 'guardian_email',
        'guardian email' => 'guardian_email',
        'parent_email' => 'guardian_email',
        'parent email' => 'guardian_email',
        'parentemail' => 'guardian_email',
        'staff_id' => 'staff_id',
        'staff id' => 'staff_id',
        'staffid' => 'staff_id',
        'employee_id' => 'staff_id',
        'employee id' => 'staff_id',
        'employeeid' => 'staff_id',
        'qualifications' => 'qualification',
        'highest_qualification' => 'qualification',
        'highest qualification' => 'qualification',
        'highestqualification' => 'qualification',
    ];

    public static function normalize(string $header): ?string
    {
        $normalized = trim($header);

        if ($normalized === '') {
            return null;
        }

        $normalized = mb_strtolower($normalized);

        $normalized = preg_replace('/\s+/', ' ', $normalized);

        $normalized = preg_replace('/[\s\-\.]+/', '_', $normalized);

        $normalized = preg_replace('/[^\w_]+/', '', $normalized);

        $normalized = trim($normalized, '_');

        if (isset(self::CANONICAL[$normalized])) {
            return self::CANONICAL[$normalized];
        }

        if (isset(self::SYNONYMS[$normalized])) {
            return self::SYNONYMS[$normalized];
        }

        return null;
    }

    public static function normalizeHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $h) {
            $canonical = self::normalize($h);
            if ($canonical !== null) {
                $result[] = $canonical;
            }
        }

        return $result;
    }
}
