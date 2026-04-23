<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Exceptions\Tenant\TenantProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use App\Jobs\SendSchoolWelcomeEmail;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function checkHandle(Request $request): JsonResponse
    {
        $request->validate([
            'handle' => ['required', 'string', 'max:255'],
        ]);

        $exists = Tenant::where('handle', $request->handle)->exists();

        return response()->json([
            'success'   => true,
            'available' => ! $exists,
        ]);
    }

    public function register(OnboardingRequest $request, CreateTenantAction $action): JsonResponse
    {
        // Pull mapped data once — used for both provisioning and the welcome email
        $data = $request->mappedData();

        try {
            $tenant = $action->execute($data);

            $centralDomain = config('app.central_domain', 'myapp.com');
            $loginUrl      = "https://{$tenant->handle}.{$centralDomain}/login";

            // Use $data for email fields — Tenant model doesn't carry admin credentials
            SendSchoolWelcomeEmail::dispatch(
                adminEmail: $data['admin_email'],
                adminName:  trim(($data['admin_first_name'] ?? '') . ' ' . ($data['admin_last_name'] ?? '')),
                schoolName: $tenant->name,
                handle:     $tenant->handle,
                loginUrl:   $loginUrl,
            )->onQueue('emails');

            return response()->json([
                'success' => true,
                'message' => 'School provisioned successfully.',
                'data'    => [
                    'handle'    => $tenant->handle,
                    'name'      => $tenant->name,
                    'login_url' => $loginUrl,
                ],
            ], 201);

        } catch (TenantProvisioningException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Provisioning failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}