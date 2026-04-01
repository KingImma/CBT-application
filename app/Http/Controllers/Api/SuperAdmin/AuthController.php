<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string']
        ]);
        
        $admin = SuperAdmin::where('email', $request->email)
                   ->where('is_active', true)
                   ->first();
        
        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['the provided credentials are incorrect'],
            ]);
        }
        
        $admin->update(['last_login_at' => now()]);
        
        $token = $admin->createToken('super-admin-token',[
            'super_admin',
            'tenant:read',
            'tenant:write',
            'tenant:suspend',
        ])->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'admin' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email
            ]
        ]);
    }
    
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logged out successfully']);
    }
    
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
