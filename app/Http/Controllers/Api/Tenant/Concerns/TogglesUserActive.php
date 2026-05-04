<?php

namespace App\Http\Controllers\Api\Tenant\Concerns;

use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

trait TogglesUserActive
{
    protected function toggleActive(string $id, string $role): JsonResponse
    {
        $user = User::role($role)->findOrFail($id);
        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return ApiResponse::success([
            'is_active' => $user->is_active,
        ], $user->is_active ? ucfirst($role).' activated.' : ucfirst($role).' deactivated.');
    }
}
