<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Auth;

use App\Enums\RoleType;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateTenantUser
{
    public function execute(string $identifier, string $password): array
    {
        $user = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $identifier)->first();
        } else {
            $user = User::whereHas('studentProfile', fn ($q) => $q->where('admission_number', strtoupper($identifier)))->first();
        }

        $credentialsInvalid = ! $user || ! Hash::check($password, $user->password);

        if ($credentialsInvalid) {
            $this->throwFailedAuthException();
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated. Contact your school admin.');
        }

        $user->tokens()->where('name', 'tenant-token')->delete();

        $role = $user->getRoleNames()->first();

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
