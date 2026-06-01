<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Actions\Tenants\Concerns\CreatesTenantUser;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\DB;

class TeacherAction
{
    use CreatesTenantUser;

    public function __construct(private TenantUserService $tenantUserService) {}

    protected function profileRelation(): string
    {
        return 'teacherProfile';
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $password = $data['password'] ?? config('app.teacher_default_password', 'teach12345');

            $user = $this->createUser($data, 'teacher', $password);

            $this->createProfile($user, 'teacherProfile', [
                'qualification' => $data['qualification'] ?? null,
                'staff_id' => $data['staff_id'] ?? $this->generateStaffId(),
            ]);

            $this->assignRoleAndSyncIndex($user, 'teacher', $this->tenantUserService);

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
            $this->updateUserAndProfile($user, $data, ['qualification', 'staff_id']);
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
