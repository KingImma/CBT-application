<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Actions\Contracts\CreatesTeacher;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTeacherAction implements CreatesTeacher
{
    public function __construct(
        private TenantUserService $tenantUserService,
        private GenerateStaffIdAction $staffIdGenerator
    ) {}

    /**
     * Executes the teacher creation process.
     *
     * @return array{user: User, password: string}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $password = $data['password'] ?? Str::random(10);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'phone' => $data['phone'] ?? null,
                'role' => 'teacher',
                'is_active' => true,
            ]);

            $user->assignRole('teacher');

            $this->tenantUserService->updateCentralIndex($user->email, 'teacher');

            $user->teacherProfile()->create([
                'qualification' => $data['qualification'] ?? null,
                'staff_id' => $data['staff_id'] ?? $this->staffIdGenerator->execute(),
            ]);

            return [
                'user' => $user,
                'password' => $password,
            ];
        });
    }
}
