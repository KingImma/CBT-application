<?php
// app/Actions/Tenants/Student/GenerateRegistrationNumber.php
// - What: Extracted registration number generator
// - Does: Generates sequential STU/YYYY/NNNN reg numbers with row-level locking to prevent race conditions
// - Why: Logic was copy-pasted in CreateStudentAction AND StudentImportService — two live copies = two places to get out of sync
// - Delivers: Single source of truth; both action and service import this one class
// - Alternative: Put this on StudentProfile as a static method — couples model to generation logic, harder to test in isolation

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Models\Tenant\StudentProfile;

class GenerateAdmissionNumber
{
    public function generate(): string
    {
        $year = date('Y');

        // lockForUpdate() prevents two concurrent requests from generating the same sequence number
        $lastProfile = StudentProfile::lockForUpdate()
            ->where('admission_number', 'like', "STU/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();

        $nextCount = 1;

        if ($lastProfile && preg_match('/(\d+)$/', $lastProfile->admission_number, $matches)) {
            $nextCount = (int) $matches[1] + 1;
        }

        return "STU/{$year}/" . str_pad((string) $nextCount, 4, '0', STR_PAD_LEFT);
    }
}