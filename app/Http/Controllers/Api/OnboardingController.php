<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Tenant;
use App\Jobs\SendSchoolWelcomeEmail;


/*
 * 1. What it is: The API endpoint controller (`OnboardingController`).
 * 2. What it does in a nutshell: Accepts the HTTP request, grabs the transformed data from the FormRequest, passes it to the action, and returns the standard JSON response.
 * 3. Why this was chosen: Adheres to the strict MVC pattern. The controller's only responsibility is handling the HTTP layer.
 * 4. Expected deliverables and alternatives: A 201 Created response with the tenant handle. The alternative is a "fat controller" that handles validation, database creation, and event dispatching all in one file.
 */

class OnboardingController extends Controller
{
    public function checkHandle(Request $request): JsonResponse
    {
        $request->validate([
            'handle' => ['required', 'string', 'max:255'],
        ]);

        $exists = Tenant::where('handle', $request->handle)->exists();

        return response()->json([
            'success' => true,
            'available' => !$exists,
        ]);
    }

    public function register(OnboardingRequest $request, CreateTenantAction $action): JsonResponse
    {
        try {
            // Pass the mapped, snake_case data directly to the action
            $tenant = $action->execute($request->mappedData());
            
            SendSchoolWelcomeEmail::dispatch(
                adminEmail: $tenant->admin_email,
                adminName:  $tenant->admin_name,
                schoolName: $tenant->name,
                handle:     $tenant->handle,
                loginUrl:   $tenant->login_url,
            );

            return response()->json([
                'success' => true,
                'message' => 'School provisioned successfully.',
                'data' => [
                    'handle' => $tenant->handle,
                    'name'   => $tenant->name,
                ]
            ], 201);

        } catch (\App\Exceptions\Tenant\TenantProvisioningException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Provisioning failed: ' . $e->getMessage()
            ], 500);
        }
    }
}