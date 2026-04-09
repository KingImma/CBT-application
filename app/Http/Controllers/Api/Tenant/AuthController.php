<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Headerless login — resolves tenant from email automatically.
     *
     * Looks up the central tenant_user_index to find which tenant
     * owns this email, initializes that tenant's DB connection,
     * then authenticates against it. No X-Tenant header required.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Step 1 — resolve tenant from central index
        $index = DB::connection('central')
            ->table('tenant_user_index')
            ->where('email', $request->email)
            ->first();

        if (! $index) {
            throw ValidationException::withMessages([
                'email' => ['No account found with this email address.'],
            ]);
        }

        $tenant = \App\Models\Tenant::find($index->tenant_id);

        if (! $tenant || ! $tenant->is_active) {
            return response()->json([
                'message' => 'This school account is inactive. Contact support.',
            ], 403);
        }

        // Step 2 — initialize tenancy to switch DB connection
        tenancy()->initialize($tenant);

        // Step 3 — authenticate against tenant DB
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            tenancy()->end();

            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        if (! $user->is_active) {
            tenancy()->end();

            return response()->json([
                'message' => 'Your account has been deactivated. Contact your school admin.',
            ], 403);
        }

        // Revoke previous tokens for this device
        $user->tokens()->where('name', 'like', 'tenant-token:%')->delete();

        // Embed slug in token name — post-login requests need no header
        $token = $user->createToken(
            'tenant-token:' . $tenant->slug,
            ['*'],
            now()->addHours(12)
        )->plainTextToken;

        return response()->json([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'expires_in'  => 43200,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'user'        => [
                'id'    => $user->id,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'role'  => $user->getRoleNames()->first(),
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