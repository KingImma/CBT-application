<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/*
 * 1. What it is: The optimized TenantAuthService.
 * 2. What it does in a nutshell: Authenticates a user directly against the active tenant database context, issues a Sanctum token with role-based expiration, and handles deactivated accounts.
 * 3. Why this was chosen: It removes redundant central database queries. Because the `tenant.header` middleware intercepts the request before it reaches this service, `App\Models\Tenant\User` is already strictly scoped to the correct school.
 * 4. Expected deliverables: A clean authentication array for the controller to return.
 */

class TenantAuthService
{
    public function authenticate(string $email, string $password): array
    {
        // Eloquent is safely querying ONLY the tenant database here.
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->throwFailedAuthException();
        }

        if (!$user->is_active) {
            abort(403, 'Your account has been deactivated. Contact your school admin.');
        }

        // Revoke old tokens for this specific device/context
        $user->tokens()->where('name', 'tenant-token')->delete();

        $role = $user->getRoleNames()->first();
        
        $expiresAt = match ($role) {
            'student' => now()->addHours(4),
            default   => now()->addHours(8),
        };

        $token = $user->createToken(
            'tenant-token', 
            ['*'], 
            $expiresAt
        )->plainTextToken;

        return [
            'token'       => $token,
            'expires_in'  => (int) now()->diffInSeconds($expiresAt),
            'tenant_slug' => tenant('slug'),
            'user'        => $user,
            'role'        => $role,
        ];
    }

    private function throwFailedAuthException(): void
    {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }
}