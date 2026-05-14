<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Models\Tenant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication & Onboarding
 * * APIs for user login, password resets, and initial school onboarding.
 */
class OnboardingController extends Controller
{
    public function checkHandle(Request $request): JsonResponse
    {
        $request->validate([
            'handle' => ['required', 'string', 'max:255'],
        ]);

        $exists = Tenant::where('handle', $request->handle)->exists();

        return ApiResponse::success([
            'available' => ! $exists,
        ], 'Handle availability checked.');
    }

    public function register(OnboardingRequest $request, CreateTenantAction $action): JsonResponse
    {
        $data = $request->mappedData();

        try {
            $tenant = $action->execute($data);

            $centralDomain = config('app.central_domain', 'myapp.com');
            $loginUrl = "https://{$tenant->handle}.{$centralDomain}/login";

            return ApiResponse::created([
                'handle' => $tenant->handle,
                'name' => $tenant->name,
                'login_url' => $loginUrl,
            ], 'School provisioned successfully.');

        }
    }
}
