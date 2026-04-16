<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * List all active plans.
     * Frontend uses this to render plan selection UI
     * before creating a tenant.
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->orderBy('price_monthly')
            ->get();

        return response()->json(SubscriptionPlanResource::collection($plans));
    }
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:100', 'unique:subscription_plans,name'],
            'slug'                => ['required', 'string', 'max:100', 'unique:subscription_plans,slug'],
            'description'         => ['nullable', 'string'],
            'max_students'        => ['required', 'integer', 'min:1'],
            'max_teachers'        => ['required', 'integer', 'min:1'],
            'max_exams_per_term'  => ['required', 'integer', 'min:1'],
            'price_monthly'       => ['required', 'integer', 'min:0'],
            'price_yearly'        => ['required', 'integer', 'min:0'],
            'features'            => ['nullable', 'array'],
            'interval'            => ['nullable', 'in:monthly,yearly'],
        ]);
        
        $plan = SubscriptionPlan::create(array_merge($validated, ['is_active' => true]));
        
        return response()->json(new SubscriptionPlanResource($plan), 201);
    }
    
    public function update(Request $request, string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        $validated = $request->validate([
            'name'                => ['sometimes', 'string', 'max:100', 'unique:subscription_plans,name,' . $id],
            'slug'                => ['sometimes', 'string', 'max:100', 'unique:subscription_plans,slug,' . $id],
            'description'         => ['nullable', 'string'],
            'max_students'        => ['sometimes', 'integer', 'min:1'],
            'max_teachers'        => ['sometimes', 'integer', 'min:1'],
            'max_exams_per_term'  => ['sometimes', 'integer', 'min:1'],
            'price_monthly'       => ['sometimes', 'integer', 'min:0'],
            'price_yearly'        => ['sometimes', 'integer', 'min:0'],
            'features'            => ['nullable', 'array'],
            'interval'            => ['nullable', 'in:monthly,yearly'],
        ]);
        
        $plan->update($validated);
        
        return response()->json(new SubscriptionPlanResource($plan->fresh()));
    }
    
    public function destroy(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);
        
        $activeTenants = $plan->tenants()->where('is_active', true)->count();
        
        if ($activeTenants > 0) {
            return response()->json([
                'message' => "Cannot deactive - {$activeTenants} active school(s) are on this plan. Migrate them first."
            ], 422);
        }
        
        $plan->update(['is_active' => false]);
        
        return response()->json(['message' => "Plan {$plan->name} deactived"]);
    }


    /**
     * Get a single plan's details.
     */
    public function show(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::active()->findOrFail($id);

        return response()->json(new SubscriptionPlanResource($plan));
    }
}