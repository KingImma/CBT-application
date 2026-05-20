<?php

declare(strict_types=1);

namespace App\Data\Schemas;

class ExamSettingsSchema
{
    public static function validatorRules(string $prefix = 'settings'): array
    {
        return [
            "{$prefix}" => ['nullable', 'array'],
            "{$prefix}.randomize_questions" => ['sometimes', 'boolean'],
            "{$prefix}.show_result_immediately" => ['sometimes', 'boolean'],
            "{$prefix}.results_release_date" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.require_attendance" => ['sometimes', 'boolean'],
            "{$prefix}.distribution" => ['sometimes', 'in:pooled,by_topic'],
            "{$prefix}.topic_weights" => ['sometimes', 'nullable', 'array'],
            "{$prefix}.topic_weights.*" => ['numeric', 'min:0', 'max:100'],
        ];
    }
}
