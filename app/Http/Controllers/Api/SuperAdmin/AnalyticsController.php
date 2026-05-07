<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Super Admin: Platform Analytics
 * * APIs for global SaaS metrics and platform-wide monitoring.
 */
class AnalyticsController extends Controller
{
    /**
     * Platform-wide dashboard overview.
     * All counts come from the central DB — no tenant DB switching needed.
     */
    public function overview(): JsonResponse
    {
        $tenants = Tenant::withTrashed();

        return ApiResponse::success([
            'schools' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('is_active', true)->count(),
                'trial' => Tenant::where('subscription_status', 'trial')->count(),
                'suspended' => Tenant::where('subscription_status', 'suspended')->count(),
                'new_this_month' => Tenant::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ],
            'plans' => SubscriptionPlan::withCount('tenants')
                ->where('is_active', true)
                ->get()
                ->map(fn ($p) => [
                    'name' => $p->name,
                    'school_count' => $p->tenants_count,
                ]),
            'revenue' => [
                // Placeholder — wire to payment processor when billing is live
                'mrr' => null,
                'arr' => null,
            ],
        ], 'Platform overview retrieved successfully.');
    }

    /**
     * Usage trends — school signups over time.
     * Grouped by month for the last 12 months.
     */
    public function usage(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 12);

        $signups = DB::table('tenants')
            ->selectRaw("DATE_TRUNC('month', created_at) as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->whereNull('deleted_at')
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::parse($row->month)->format('Y-m'),
                'count' => (int) $row->count,
            ]);

        $byStatus = DB::table('tenants')
            ->selectRaw('subscription_status, COUNT(*) as count')
            ->whereNull('deleted_at')
            ->groupBy('subscription_status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->subscription_status => (int) $row->count]);

        return ApiResponse::success([
            'period_months' => $months,
            'signups' => $signups,
            'by_status' => $byStatus,
        ], 'Usage analytics retrieved successfully.');
    }

    /**
     * Paginated platform audit logs.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $logs = DB::table('global_audit_logs')
            ->when($request->tenant_id, fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->when($request->action, fn ($q) => $q->where('action', 'like', "%{$request->action}%"))
            ->orderByDesc('created_at')
            ->paginate(50);

        return ApiResponse::paginated($logs, 'Audit logs retrieved successfully.');
    }
}
