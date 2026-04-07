<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Tenant-scoped login.
     * Detects role and returns it with the token so the frontend
     * can route to the correct dashboard (admin vs teacher vs student).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Your account has been deactivated. Contact your school admin.',
            ], 403);
        }

        // Delete previous tokens for this device — single session per user
        $user->tokens()->where('name', 'tenant-token')->delete();

        $token = $user->createToken('tenant-token', ['*'], now()->addHours(12))->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => 43200, // 12 hours in seconds
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'role'  => $user->getRoleNames()->first(), // school_admin | teacher | student
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('tenant')->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('tenant');

        return response()->json([
            'id'          => $user->id,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'email'       => $user->email,
            'role'        => $user->getRoleNames()->first(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}