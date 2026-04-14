<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantAuthService
{
    /**
     * Authenticates a user across the multi-tenant architecture
     * and generates a routed Sanctum token
     */
    public function authenticate(string $email, string $password): array
    {
        // 1. Look up the email in the central index
        $indexRecord = DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_user_index')
            ->where('email', $email)
            ->first();

        if (!$indexRecord) {
            $this->throwFailedAuthException();
        }

        // 2. Initialize the tenant dynamically
        tenancy()->initialize($indexRecord->tenant_id);

        // 3. Safely connected to the tenant's isolated DB. Verify credentials.
        $user = User::where("email", $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->throwFailedAuthException();
        }

        // 4. Verify account status
        if (!$user->is_active) {
            abort(403, "Your account has been deactivated. Contact your school admin.");
        }

        // 5. Enforce single session per user by wiping old tokens
        $user->tokens()->where("name", "tenant-token")->delete();

        // 6. Determine role and expiration
        $role = $user->getRoleNames()->first();
        $expiresAt = match ($role) {
            "student" => now()->addHours(4),
            default   => now()->addHours(8), 
        };

        // 7. Generate the raw and routed tokens
        $rawToken = $user->createToken("tenant-token", ["*"], $expiresAt)->plainTextToken;
        $routedToken = tenant('slug') . '::' . $rawToken;

        // 8. Return the raw data to the controller
        return [
            "token"       => $routedToken,
            "expires_in"  => (int) now()->diffInSeconds($expiresAt),
            "tenant_slug" => tenant('slug'), 
            "user"        => $user,
            "role"        => $role,
        ];
    }

    private function throwFailedAuthException(): void
    {
        throw ValidationException::withMessages([
            "email" => ["The provided credentials are incorrect."],
        ]);
    }
}