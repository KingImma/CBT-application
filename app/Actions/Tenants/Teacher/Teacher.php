<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Actions\Base\CreateAction;
use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\TenantUsers\SyncTenantUser;
use App\Enums\RoleType;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;

class Teacher
{
    public function __construct(
        private SyncTenantUser $syncTenantUser,
        private CreateAction $createAction,
        private UpdateAction $updateAction,
    ) {}

    public function create(array $data): array
    {
        $password = $data['password'] ?? config('app.teacher_default_password', 'teach12345');

        $user = $this->createAction->execute(
            User::class,
            ['data' => $data, 'password' => $password, 'role' => RoleType::Teacher],
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
                $user->assignRole(RoleType::Teacher->value);
                $this->syncTenantUser->execute($user->email, RoleType::Teacher->value);

                $user->teacherProfile()->create([
                    'qualification' => $d['data']['qualification'] ?? null,
                    'staff_id' => $d['data']['staff_id'] ?? $this->generateStaffId(),
                    'class_level_id' => $d['data']['class_level_id'] ?? null,
                ]);
            },
        );

        return ['user' => $user, 'password' => $password];
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role(RoleType::Teacher->value)->findOrFail($userId);

        return $this->updateAction->execute(
            $user,
            ['data' => $data],
            guard: TeacherGuards::canUpdate(),
            prepare: fn (User $user, array $d) => collect($d['data'])
                ->only(['first_name', 'last_name', 'email', 'phone'])
                ->toArray(),
            after: function (User $user, array $d) {
                $profileData = collect($d['data'])
                    ->only(['qualification', 'staff_id', 'class_level_id'])
                    ->toArray();

                if (! empty($profileData)) {
                    $user->teacherProfile()->updateOrCreate(
                        ['user_id' => $user->id],
                        $profileData
                    );
                }
            },
        );
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
