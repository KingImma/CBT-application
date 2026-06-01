<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Concerns;

use App\Models\Tenant\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\Hash;

trait CreatesTenantUser
{
    protected function createUser(array $data, string $role, string $password): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'phone' => $data['phone'] ?? null,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function assignRoleAndSyncIndex(User $user, string $role, TenantUserService $tenantUserService): void
    {
        $user->assignRole($role);
        $tenantUserService->updateCentralIndex($user->email, $role);
    }

    protected function updateUserAndProfile(User $user, array $data, array $profileKeys): void
    {
        $userData = collect($data)->only(['first_name', 'last_name', 'email', 'phone'])->toArray();
        if (! empty($userData)) {
            $user->update($userData);
        }

        $profileData = collect($data)->only($profileKeys)->toArray();
        if (! empty($profileData)) {
            $this->updateProfile($user, $profileData);
        }
    }

    protected function createProfile(User $user, string $profileRelation, array $profileData): void
    {
        $user->{$profileRelation}()->create($profileData);
    }

    protected function updateProfile(User $user, array $profileData): void
    {
        $profileRelation = $this->profileRelation();
        $user->{$profileRelation}()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );
    }

    abstract protected function profileRelation(): string;
}
