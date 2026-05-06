<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Models\Tenant\TeacherProfile;

class GenerateStaffIdAction
{
    public function execute(): string
    {
        $year = date('Y');
    
        $last = TeacherProfile::lockForUpdate()
            ->where('staff_id', 'like', "TCH/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();
    
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last->staff_id, $m)) {
            $next = (int) $m[1] + 1;
        }
    
        return "TCH/{$year}/" . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
