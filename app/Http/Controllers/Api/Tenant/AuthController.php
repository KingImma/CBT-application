<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TenantAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $authService
    ) {}

    public function login(Request $request): JsonResponse
    {
        // 1. Validate the Request
        $validated = $request->validate([
            "email"    => ["required", "email"],
            "password" => ["required", "string"],
        ]);

        // 2. Delegate to the Service
        $authData = $this->authService->authenticate(
            $validated['email'], 
            $validated['password']
        );

        // 3. Format and return the HTTP Response
        return response()->json([
            "token"       => $authData['token'],
            "token_type"  => "Bearer",
            "expires_in"  => $authData['expires_in'],
            "tenant_slug" => $authData['tenant_slug'], 
            "user" => [
                "id"    => $authData['user']->id,
                "name"  => trim($authData['user']->first_name . " " . $authData['user']->last_name),
                "email" => $authData['user']->email,
                "role"  => $authData['role'],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user("tenant")->currentAccessToken()->delete();

        return response()->json(["message" => "Logged out successfully."]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user("tenant");

        return response()->json([
            "id"          => $user->id,
            "first_name"  => $user->first_name,
            "last_name"   => $user->last_name,
            "email"       => $user->email,
            "role"        => $user->getRoleNames()->first(),
            "permissions" => $user->getAllPermissions()->pluck("name"),
        ]);
    }
}