<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanController extends Controller
{
    /**
     * List all active plans.
     * Frontend uses this to render plan selection UI
     * before creating a tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::query()
            ->where('is_active', true)      // Only active
            ->orderBy('price_monthly');     // Cheapest first

        // // Optional filters
        // if ($request->filled('interval')) {
        //     $query->where('interval', $request->interval); // monthly/yearly
        // }

        // if ($request->filled('category')) {
        //     $query->where('category', $request->category);
        // }

        $plans = $query->get();

        return ApiResponse::success(
            SubscriptionPlanResource::collection($plans)->resolve(),
            'Subscription plans retrieved successfully.',
            meta: ['total' => $plans->count()]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:subscription_plans,name'],
            'max_students' => ['required', 'integer', 'min:1'],
            'max_teachers' => ['required', 'integer', 'min:1'],
            'max_exams_per_term' => ['required', 'integer', 'min:1'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'price_yearly' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $plan = SubscriptionPlan::create(array_merge($validated, ['is_active' => true]));

        return ApiResponse::created(
            (new SubscriptionPlanResource($plan))->resolve(),
            'Subscription plan created successfully.'
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:subscription_plans,name,'.$id],
            'max_students' => ['sometimes', 'integer', 'min:1'],
            'max_teachers' => ['sometimes', 'integer', 'min:1'],
            'max_exams_per_term' => ['sometimes', 'integer', 'min:1'],
            'price_monthly' => ['sometimes', 'integer', 'min:0'],
            'price_yearly' => ['sometimes', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $plan->update($validated);

        return ApiResponse::success(
            (new SubscriptionPlanResource($plan->fresh()))->resolve(),
            'Subscription plan updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        // Atomic transaction
        return DB::transaction(function () use ($plan) {
            $this->validateDeletion($plan);

            $plan->delete(); // ✅ Hard delete

            return ApiResponse::message("Plan '{$plan->name}' deleted successfully");
        });
    }

    /**
     * Validate plan can be deleted
     */
    private function validateDeletion(SubscriptionPlan $plan): void
    {
        $constraints = $this->checkConstraints($plan);

        if ($constraints->isNotEmpty()) {
            throw ValidationException::withMessages([
                'plan' => $constraints->implode(' | '),
            ]);
        }
    }

    /**
     * Check all deletion constraints
     */
    private function checkConstraints(SubscriptionPlan $plan): Collection
    {
        $errors = collect();

        $activeTenants = $plan->tenants()
            ->withTrashed()
            ->where('is_active', true)
            ->count();

        if ($activeTenants > 0) {
            $errors->push("{$activeTenants} active tenant(s)");
        }

        $trialTenants = $plan->tenants()
            ->whereNull('subscription_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<', now()->addDays(7))
            ->count();

        if ($trialTenants > 0) {
            $errors->push("{$trialTenants} trial(s) ending soon");
        }

        $recentSubs = $plan->tenants()
            ->where('created_at', '>', now()->subDays(30))
            ->count();

        if ($recentSubs > 0) {
            $errors->push("{$recentSubs} recent subscription(s)");
        }

        return $errors;
    }

    /**
     * Get a single plan's details.
     */
    public function show(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::active()->findOrFail($id);

        return ApiResponse::success(
            (new SubscriptionPlanResource($plan))->resolve(),
            'Subscription plan retrieved successfully.'
        );
    }
}
