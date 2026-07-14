<?php

declare(strict_types=1);

namespace App\Domains\Import\Data\Schemas;

class StudentImportSchema extends ImportSchema
{
    public const COLUMNS = [
        'first_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'last_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'email' => ['required' => false, 'rules' => ['nullable', 'email']],
        'phone' => ['required' => false, 'rules' => ['nullable', 'string', 'max:20']],
        'admission_number' => ['required' => false, 'rules' => ['nullable', 'string', 'max:50']],
        'class_level' => ['required' => true, 'rules' => ['required', 'string']],
        'class_arm' => ['required' => true, 'rules' => ['required', 'string']],
        'date_of_birth' => ['required' => false, 'rules' => ['nullable', 'date']],
        'gender' => ['required' => false, 'rules' => ['nullable', 'in:male,female,other']],
        'guardian_email' => ['required' => false, 'rules' => ['nullable', 'email', 'max:255']],
    ];

    public const IDENTITY = ['email', 'admission_number'];

    public static function validatorRules(?string $overrideClassLevelId = null): array
    {
        $rules = [];
        foreach (static::COLUMNS as $name => $config) {
            if ($name === 'class_level' && $overrideClassLevelId) {
                $rules[$name] = ['nullable', 'string'];
            } else {
                $rules[$name] = $config['rules'];
            }
        }

        return $rules;
    }
}
