<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    public function authenticate(string $email, string $password): array
    {
        $indexRecord = DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('email', $email)
            ->first();

        if (!$indexRecord) {
            $this->throwFailedAuthException();
        }

        tenancy()->initialize($indexRecord->tenant_id);

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->throwFailedAuthException();
        }

        if (!$user->is_active) {
            abort(403, 'Your account has been deactivated. Contact your school admin.');
        }

        $user->tokens()->where('name', 'tenant-token')->delete();

        $role = $user->getRoleNames()->first();
        $expiresAt = match ($role) {
            'student' => now()->addHours(4),
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