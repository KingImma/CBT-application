<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherAction
{
    public function __construct(private TenantUserService $tenantUserService) {}

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $password = $data['password'] ?? 'teach12345';

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
                'staff_id' => $data['staff_id'] ?? $this->generateStaffId(),
            ]);

            return [
                'user' => $user,
                'password' => $password,
            ];
        });
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role('teacher')->findOrFail($userId);

        DB::transaction(function () use ($user, $data) {
            $userData = collect($data)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();
            if (! empty($userData)) {
                $user->update($userData);
            }

            $profileData = collect($data)->only(['qualification', 'staff_id'])->toArray();
            if (! empty($profileData)) {
                $user->teacherProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
        });

        return $user->fresh('teacherProfile');
    }

    public function generateStaffId(): string
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

        return "TCH/{$year}/".str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
