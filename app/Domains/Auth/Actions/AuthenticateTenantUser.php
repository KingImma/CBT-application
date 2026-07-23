<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Enums\RoleType;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateTenantUser
{
    public function execute(string $identifier, string $password): array
    {
        $user = $this->findUser($identifier);

        $this->ensureValidCredentials($user, $password);
        $this->ensureAccountIsActive($user);

        $role = $this->resolveRole($user);

        return $this->createAuthenticationResponse($user, $role);
    }

    private function findUser(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        return User::whereHas(
            'studentProfile',
            fn ($query) => $query->where('admission_number', strtoupper($identifier))
        )->first();
    }

    private function ensureValidCredentials(?User $user, string $password): void
    {
        if (! $user || ! Hash::check($password, $user->password)) {
            $this->throwFailedAuthException();
        }
    }

    private function ensureAccountIsActive(User $user): void
    {
        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated. Contact your school admin.');
        }
    }

    private function resolveRole(User $user): string
    {
        return $user->getRoleNames()->first();
    }

    private function createAuthenticationResponse(User $user, string $role): array
    {
        $user->tokens()->where('name', 'tenant-token')->delete();

        $expiresAt = match ($role) {
            RoleType::Student->value => now()->addHours(4),
            default => now()->addHours(8),
        };

        $token = $user->createToken(
            'tenant-token',
            ['*'],
            $expiresAt
        )->plainTextToken;

        return [
            'token' => $token,
            'expires_in' => (int) now()->diffInSeconds($expiresAt),
            'tenant_slug' => tenant('slug'),
            'user' => $user,
            'role' => $role,
        ];
    }

    private function throwFailedAuthException(): void
    {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }
}
