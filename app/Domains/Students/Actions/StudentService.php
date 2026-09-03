<?php

declare(strict_types=1);

namespace App\Domains\Students\Actions;

use App\Domains\Students\Support\StudentRules;
use App\Domains\Tenancy\Actions\SyncTenantUser;
use App\Enums\RoleType;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentService
{
    public function __construct(
        private SyncTenantUser $syncTenantUser,
    ) {}

    public function create(array $data): array
    {
        $password = config('app.student_default_password');
    
        $prepared = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'phone' => $data['phone'] ?? null,
            'role' => RoleType::Student->value,
            'is_active' => true,
        ];
    
        $user = User::create($prepared);
    
        $user->assignRole(RoleType::Student->value);
    
        $this->syncTenantUser->execute(
            $user->email,
            RoleType::Student->value
        );
    
        $admissionNumber = strtoupper(
            $data['admission_number']
                ?? $this->generateAdmissionNumber()
        );
    
        $user->studentProfile()->create([
            'class_level_id' => $data['class_level_id'],
            'class_arm_id' => $data['class_arm_id'],
            'admission_number' => $admissionNumber,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_phone' => $data['guardian_phone'] ?? null,
            'guardian_email' => $data['guardian_email'] ?? null,
        ]);
    
        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role(RoleType::Student->value)->findOrFail($userId);

        return DB::connection('tenant')->transaction(function () use ($user, $data) {
            StudentRules::canUpdate()($user, $data);

            $prepared = collect($data)
                ->only(['first_name', 'last_name', 'email', 'phone'])
                ->toArray();

            $user->update($prepared);

            $fresh = $user->fresh();

            $profileData = collect($data)
                ->only(['class_level_id', 'class_arm_id', 'admission_number', 'date_of_birth', 'gender', 'guardian_name', 'guardian_phone', 'guardian_email'])
                ->toArray();

            if (isset($profileData['admission_number'])) {
                $profileData['admission_number'] = strtoupper($profileData['admission_number']);
            }

            if (! empty($profileData)) {
                $fresh->studentProfile()->updateOrCreate(
                    ['user_id' => $fresh->id],
                    $profileData
                );
            }

            return $fresh;
        });
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
