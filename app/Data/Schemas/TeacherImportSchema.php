<?php

declare(strict_types=1);

namespace App\Data\Schemas;

class TeacherImportSchema
{
    public const COLUMNS = [
        'first_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'last_name' => ['required' => true, 'rules' => ['required', 'string', 'max:100']],
        'email' => ['required' => true, 'rules' => ['required', 'email']],
        'phone' => ['required' => false, 'rules' => ['nullable', 'string', 'max:20']],
        'qualification' => ['required' => false, 'rules' => ['nullable', 'string', 'max:255']],
        'staff_id' => ['required' => false, 'rules' => ['nullable', 'string', 'max:50']],
    ];

    public const IDENTITY = ['email', 'staff_id'];

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

    public static function validatorRules(?string $ignored = null): array
    {
        $rules = [];
        foreach (self::COLUMNS as $name => $config) {
            $rules[$name] = $config['rules'];
        }

        return $rules;
    }
}
