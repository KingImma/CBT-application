<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

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


    /**
     * Get a single plan's details.
     */
    public function show(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::active()->findOrFail($id);

        return response()->json(new SubscriptionPlanResource($plan));
    }
}