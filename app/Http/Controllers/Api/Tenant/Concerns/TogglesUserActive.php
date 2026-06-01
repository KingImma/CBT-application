<?php

declare(strict_types=1);

// @deprecated This trait is defined but never used in any controller.
// Remove in a future cleanup pass if still unused.

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
