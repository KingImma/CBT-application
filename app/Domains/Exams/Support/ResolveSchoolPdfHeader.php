<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Models\Tenant\SchoolSetting;

/**
 * Resolves the school identity block printed at the top of a result sheet.
 */
final class ResolveSchoolPdfHeader
{
    private const array KEYS = [
        'school_name',
        'school_address',
        'school_motto',
        'school_logo',
    ];

    /**
     * @return array{name: string, address: ?string, motto: ?string, logo: ?string}
     */
    public function execute(): array
    {
        $settings = SchoolSetting::whereIn('key', self::KEYS)->pluck('value', 'key');

        $logo = $settings->get('school_logo');

        return [
            'name' => $settings->get('school_name') ?: (tenant('name') ?? 'EduCBT'),
            'address' => $settings->get('school_address'),
            'motto' => $settings->get('school_motto'),
            /*
             * Dompdf is not configured with remote image support, so a hosted
             * logo URL would abort rendering. Only embed inline (data URI) or
             * local logos; otherwise fall back to an empty logo cell.
             */
            'logo' => $logo && ! str_starts_with($logo, 'http') ? $logo : null,
        ];
    }
}