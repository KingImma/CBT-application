<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforces subscription plan limits per tenant.
 * Applied to tenant-side routes that create students, teachers, and exams.
 *
 * Think of this as a bouncer at the door — it checks capacity
 * before the request ever reaches the controller.
 */
class EnforceTenantPlanLimits
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $resource,
    ): mixed {
        $tenant = tenant();

        if (! $tenant || ! $tenant->plan_id) {
            Log::warning(
                'Plan limits bypassed — tenant has no plan_id assigned.',
                [
                    'tenant_id' => $tenant->id ?? 'unknown',
                ],
            );

            return $next($request);
        }

        $plan = DB::connection('central')
            ->table('subscription_plans')
            ->where('id', $tenant->plan_id)
            ->first();

        if (! $plan) {
            return $next($request);
        }

        $limit = null;
        $current = 0;

        if ($resource === 'students') {
            $limit = $plan->max_students;
            $current = DB::table('student_profiles')->count();
        } elseif ($resource === 'teachers') {
            $limit = $plan->max_teachers;
            $current = DB::table('teacher_profiles')->count();
        } elseif ($resource === 'exams') {
            $limit = $plan->max_exams_per_term;
            $termId = $request->input('term_id') ?? $request->route('term_id');

            if (! $termId) {
                return ApiResponse::error(
                    'term_id is required to enforce exam limits.',
                    422,
                );
            }

            $current = DB::table('exams')->where('term_id', $termId)->count();
        }

        if ($limit !== null && $current >= $limit) {
            return ApiResponse::error(
                "Your {$plan->name} plan allows a maximum of {$limit} {$resource}. Please upgrade to add more.",
                403,
                meta: [
                    'limit' => $limit,
                    'current' => $current,
                ],
            );
        }

        return $next($request);
    }
}
