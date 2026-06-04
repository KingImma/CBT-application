<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CreateTenantAction;
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
    /**
     * Check if a school handle is available.
     *
     * @subgroup School Onboarding
     *
     * @bodyParam handle string required Desired school handle (subdomain). Example: "greenwood-high"
     */
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

    /**
     * Register a new school (tenant).
     *
     * @subgroup School Onboarding
     *
     * @bodyParam school_name string required School name. Example: "Greenwood High School"
     * @bodyParam admin_email string required Admin email address. No-example
     * @bodyParam admin_first_name string required Admin first name. No-example
     * @bodyParam admin_last_name string required Admin last name. No-example
     * @bodyParam admin_phone string nullable Admin phone number. No-example
     * @bodyParam handle string required Unique school handle for subdomain. No-example
     * @bodyParam plan_id string nullable Subscription plan UUID. No-example
     */
    public function register(OnboardingRequest $request, CreateTenantAction $action): JsonResponse
    {
        $data = $request->mappedData();

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
