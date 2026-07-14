<?php

declare(strict_types=1);

namespace App\Domains\Import\Data\Schemas;

class TeacherImportSchema extends ImportSchema
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

    public static function validatorRules(?string $ignored = null): array
    {
        $rules = [];
        foreach (static::COLUMNS as $name => $config) {
            $rules[$name] = $config['rules'];
        }

        return $rules;
    }
}
