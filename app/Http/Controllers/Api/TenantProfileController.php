<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @group Authentication & Onboarding
 * * Public school branding for pre-login screens (login, forgot password, onboarding).
 */
class TenantProfileController extends Controller
{
    /**
     * Public branding for the school resolved from the X-Tenant header.
     *
     * Reads only the central `tenants` table, so it costs no tenant-database
     * connection and exposes nothing beyond what is already printed on the
     * login screen.
     *
     * @subgroup School Profile
     *
     * @unauthenticated
     */
    public function show(): JsonResponse
    {
        if (! tenancy()->initialized) {
            return ApiResponse::success([
                'handle' => null,
                'name' => config('app.name', 'EduCBT'),
                'logo' => null,
            ], 'Central profile retrieved.');
        }

        $tenant = tenant();

        return ApiResponse::success([
            'handle' => $tenant->handle,
            'name' => $tenant->name,
            'logo' => $tenant->logo,
        ], 'School profile retrieved.');
    }
}