<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentAction
{
    public function __construct(private TenantUserService $tenantUserService) {}

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $admissionNumber = $data['admission_number'] ?? $this->generateAdmissionNumber();
            $password = $admissionNumber;

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'phone' => $data['phone'] ?? null,
                'role' => 'student',
                'is_active' => true,
            ]);

            $user->assignRole('student');

            $user->studentProfile()->create([
                'class_level_id' => $data['class_level_id'],
                'class_arm_id' => $data['class_arm_id'],
                'admission_number' => strtoupper($admissionNumber),
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'guardian_email' => $data['guardian_email'] ?? null,
            ]);

            $this->tenantUserService->updateCentralIndex($data['email'], 'student');

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
            $userData = collect($data)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();
            if (! empty($userData)) {
                $user->update($userData);
            }

            $profileData = collect($data)->only([
                'class_level_id', 'class_arm_id', 'admission_number',
                'date_of_birth', 'gender', 'guardian_email',
            ])->toArray();

            if (isset($profileData['admission_number'])) {
                $profileData['admission_number'] = strtoupper($profileData['admission_number']);
            }

            if (! empty($profileData)) {
                $user->studentProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
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
