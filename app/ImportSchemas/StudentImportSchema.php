<?php

declare(strict_types=1);

namespace App\ImportSchemas;

class StudentImportSchema
{
    public const COLUMNS = [
        'first_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'last_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'email' => ['required' => false, 'rules' => ['nullable', 'email']],
        'admission_number' => ['required' => false, 'rules' => ['nullable', 'string', 'max:50']],
        'class_level' => ['required' => true, 'rules' => ['required', 'string']],
        'class_arm' => ['required' => false, 'rules' => ['nullable', 'string']],
        'date_of_birth' => ['required' => false, 'rules' => ['nullable', 'date']],
        'gender' => ['required' => false, 'rules' => ['nullable', 'in:male,female,other']],
        'guardian_email' => ['required' => false, 'rules' => ['nullable', 'email', 'max:255']],
    ];

    public const IDENTITY = ['email', 'admission_number'];

    public static function requiredHeaders(): array
    {
        return array_keys(array_filter(self::COLUMNS, fn ($c) => $c['required']));
    }

    public static function allHeaders(): array
    {
        return array_keys(self::COLUMNS);
    }

    public static function missingRequiredHeaders(array $headers): array
    {
        return array_values(array_diff(self::requiredHeaders(), $headers));
    }

    public static function validatorRules(?string $overrideClassLevelId = null): array
    {
        $rules = [];
        foreach (self::COLUMNS as $name => $config) {
            if ($name === 'class_level' && $overrideClassLevelId) {
                $rules[$name] = ['nullable', 'string'];
            } else {
                $rules[$name] = $config['rules'];
            }
        }
        return $rules;
    }
}
