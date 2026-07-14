<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Actions;

use App\Domains\Tenancy\Actions\SyncTenantUser;
use App\Domains\Teachers\Support\TeacherRules;
use App\Enums\RoleType;
use App\Models\Tenant\TeacherProfile;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherService
{
    public function __construct(
        private SyncTenantUser $syncTenantUser,
    ) {}

    public function create(array $data): array
    {
        $password = $data['password'] ?? config('app.teacher_default_password', 'teach12345');

        $user = DB::transaction(function () use ($data, $password) {
            $prepared = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($password),
                'phone' => $data['phone'] ?? null,
                'role' => RoleType::Teacher->value,
                'is_active' => true,
            ];

            $user = User::create($prepared);

            $user->assignRole(RoleType::Teacher->value);
            $this->syncTenantUser->execute($user->email, RoleType::Teacher->value);

            $user->teacherProfile()->create([
                'qualification' => $data['qualification'] ?? null,
                'staff_id' => $data['staff_id'] ?? $this->generateStaffId(),
                'class_level_id' => $data['class_level_id'] ?? null,
            ]);

            return $user;
        });

        return ['user' => $user, 'password' => $password];
    }

    public function update(array $data, string $userId): User
    {
        $user = User::role(RoleType::Teacher->value)->findOrFail($userId);

        return DB::transaction(function () use ($user, $data) {
            TeacherRules::canUpdate()($user, $data);

            $prepared = collect($data)
                ->only(['first_name', 'last_name', 'email', 'phone'])
                ->toArray();

            $user->update($prepared);

            $fresh = $user->fresh();

            $profileData = collect($data)
                ->only(['qualification', 'staff_id', 'class_level_id'])
                ->toArray();

            if (! empty($profileData)) {
                $fresh->teacherProfile()->updateOrCreate(
                    ['user_id' => $fresh->id],
                    $profileData
                );
            }

            return $fresh;
        });
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
