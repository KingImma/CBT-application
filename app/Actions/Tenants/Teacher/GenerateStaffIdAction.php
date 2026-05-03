<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Models\Tenant\TeacherProfile;

class GenerateStaffIdAction
{
    public function execute(): string
    {
        $currentYear = date('Y');
        $teacherCount = TeacherProfile::whereYear('created_at', $currentYear)->count();
        $nextSequence = $teacherCount + 1;
        $formattedSequence = str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);

        return "TCH/{$currentYear}/{$formattedSequence}";
    }
}
