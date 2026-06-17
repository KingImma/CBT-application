<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CreateTenant;
use App\Data\SubscriptionPlan\SubscriptionPlanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Models\SubscriptionPlan;
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
     * @queryParam handle string required Desired school handle (subdomain). Example: "greenwood-high"
     */
    public function checkHandle(Request $request): JsonResponse
    {
        if (tenancy()->initialized) {
            return ApiResponse::error('This endpoint must be called on the central domain.', 400);
        }

        $request->validate([
            'handle' => ['required', 'string', 'max:63'],
        ]);

        $exists = Tenant::where('handle', $request->query('handle'))->exists();

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
    public function register(OnboardingRequest $request, CreateTenant $action): JsonResponse
    {
        $tenant = $action->execute($request->toData()->toArray());

        $centralDomain = config('app.central_domain', 'myapp.com');
        $loginUrl = "https://{$tenant->handle}.{$centralDomain}/login";

        return ApiResponse::created([
            'handle' => $tenant->handle,
            'name' => $tenant->name,
            'login_url' => $loginUrl,
        ], 'School provisioned successfully.');
    }

    /**
     * Retrieve active subscription plans for onboarding.
     *
     * @subgroup School Onboarding
     */
    public function plans(): JsonResponse
    {
        if (tenancy()->initialized) {
            return ApiResponse::error('This endpoint must be called on the central domain.', 400);
        }

        $plans = SubscriptionPlan::active()
            ->orderBy('price_monthly')
            ->get();

        return ApiResponse::success(
            SubscriptionPlanData::collect($plans),
            'Active subscription plans retrieved successfully.',
            meta: ['total' => $plans->count()]
        );
    }
}
