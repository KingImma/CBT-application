<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\RoleType;
use App\Models\SuperAdmin;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateSuperAdmin
{
    public function execute(SuperAdmin $admin, string $password): JsonResponse
    {
        if (! Hash::check($password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['the provided credentials are incorrect'],
            ]);
        }

        $admin->update(['last_login_at' => now()]);

        $expiresAt = now()->addHours(8);
        $token = $admin->createToken(
            'super-admin-token',
            ['super_admin', 'tenant:read', 'tenant:write', 'tenant:suspend'],
            $expiresAt
        )->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) now()->diffInSeconds($expiresAt),
            'role' => RoleType::SuperAdmin->value,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ], 'Login successful.');
    }
}
