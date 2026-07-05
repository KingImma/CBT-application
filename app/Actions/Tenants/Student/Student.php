<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Student;

use App\Actions\Base\CreateAction;
use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\TenantUsers\SyncTenantUser;
use App\Enums\RoleType;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;

class Student
{
    public function __construct(
        private SyncTenantUser $syncTenantUser,
        private CreateAction $createAction,
        private UpdateAction $updateAction,
    ) {}

    public function create(array $data): array
    {
        $admissionNumber = $data['admission_number'] ?? $this->generateAdmissionNumber();
        $password = config('app.student_default_password');

        $user = $this->createAction->execute(
            User::class,
            ['data' => $data, 'password' => $password, 'role' => RoleType::Student],
            prepare: fn (array $d) => [
                'first_name' => $d['data']['first_name'],
                'last_name' => $d['data']['last_name'],
                'email' => $d['data']['email'],
                'password' => Hash::make($d['password']),
                'phone' => $d['data']['phone'] ?? null,
                'role' => $d['role']->value,
                'is_active' => true,
            ],
            after: function (User $user, array $d) {
                $user->assignRole(RoleType::Student->value);
                $this->syncTenantUser->execute($user->email, RoleType::Student->value);

                $user->studentProfile()->create([
                    'class_level_id' => $d['data']['class_level_id'],
                    'class_arm_id' => $d['data']['class_arm_id'],
                    'admission_number' => strtoupper($d['data']['admission_number'] ?? $this->generateAdmissionNumber()),
                    'date_of_birth' => $d['data']['date_of_birth'] ?? null,
                    'gender' => $d['data']['gender'] ?? null,
                    'guardian_email' => $d['data']['guardian_email'] ?? null,
                ]);
            },
        );

        return ['user' => $user, 'password' => $password];
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role(RoleType::Student->value)->findOrFail($userId);

        return $this->updateAction->execute(
            $user,
            ['data' => $data],
            guard: StudentGuards::canUpdate(),
            prepare: fn (User $user, array $d) => collect($d['data'])
                ->only(['first_name', 'last_name', 'email', 'phone'])
                ->toArray(),
            after: function (User $user, array $d) {
                $profileData = collect($d['data'])
                    ->only(['class_level_id', 'class_arm_id', 'admission_number', 'date_of_birth', 'gender', 'guardian_email'])
                    ->toArray();

                if (! empty($profileData)) {
                    $user->studentProfile()->updateOrCreate(
                        ['user_id' => $user->id],
                        $profileData
                    );
                }
            },
        );
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
