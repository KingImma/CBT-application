<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Actions\Tenants\Concerns\CreatesTenantUser;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;

class StudentAction
{
    use CreatesTenantUser;

    public function __construct(private TenantUserService $tenantUserService) {}

    protected function profileRelation(): string
    {
        return 'studentProfile';
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $admissionNumber = $data['admission_number'] ?? $this->generateAdmissionNumber();
            $password = config('app.student_default_password');

            $user = $this->createUser($data, 'student', $password);

            $this->createProfile($user, 'studentProfile', [
                'class_level_id' => $data['class_level_id'],
                'class_arm_id' => $data['class_arm_id'],
                'admission_number' => strtoupper($admissionNumber),
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'guardian_email' => $data['guardian_email'] ?? null,
            ]);

            $this->assignRoleAndSyncIndex($user, 'student', $this->tenantUserService);

            return [
                'user' => $user,
                'password' => $password,
            ];
        });
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role('student')->findOrFail($userId);

        DB::transaction(function () use ($user, $data) {
            $this->updateUserAndProfile($user, $data, [
                'class_level_id', 'class_arm_id', 'admission_number',
                'date_of_birth', 'gender', 'guardian_email',
            ]);
        });

        return $user->fresh();
    }

    public function generateAdmissionNumber(): string
    {
        $year = date('Y');

        $lastProfile = StudentProfile::lockForUpdate()
            ->where('admission_number', 'like', "STU/{$year}/%")
            ->orderBy('id', 'desc')
            ->first();

        $nextCount = 1;

        if ($lastProfile && preg_match('/(\d+)$/', $lastProfile->admission_number, $matches)) {
            $nextCount = (int) $matches[1] + 1;
        }

        return "STU/{$year}/".str_pad((string) $nextCount, 4, '0', STR_PAD_LEFT);
    }
}
