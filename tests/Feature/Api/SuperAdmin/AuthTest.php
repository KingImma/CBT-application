<?php

declare(strict_types=1);

namespace Tests\Feature\Api\SuperAdmin;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    
    protected SuperAdmin $admin;    
    
    /*
     * Login tests verify the full auth flow — credentials validation,
     * token generation, and that inactive admins are blocked.
     */
    #[Test]
    public function super_admin_can_login_with_valid_credentials(): void
    {
        $this->admin = SuperAdmin::factory()->create([
            'email'    => 'admin@educbt.com',
            'password' => 'password123',
        ]);
    
        $response = $this->postJson('/api/super-admin/login', [
            'email'    => 'admin@educbt.com',
            'password' => 'password123',
        ]);
    
        // Assert the response shape matches what the frontend expects
        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'admin' => ['id', 'name', 'email'],
        ]);
    }
    
    #[Test]
    public function login_creates_a_personal_access_token_in_database(): void
    {
        // Verifies the token is actually persisted, not just returned in memory
        $this->admin = SuperAdmin::factory()->create(['password' => 'password123']);
    
        $this->postJson('/api/super-admin/login', [
            'email'    => $this->admin->email,
            'password' => 'password123',
        ])->assertStatus(200);
    
        expect(PersonalAccessToken::count())->toBe(1);
        expect(PersonalAccessToken::first()->tokenable_id)->toBe($this->admin->id);
    }
    
    #[Test]
    public function login_updates_last_login_at_timestamp(): void
    {
        // Ensures audit trail is maintained on each login
        $this->admin = SuperAdmin::factory()->create(['password' => 'password123']);
    
        $this->postJson('/api/super-admin/login', [
            'email'    => $this->admin->email,
            'password' => 'password123',
        ]);
    
        expect($this->admin->fresh()->last_login_at)->not->toBeNull();
    }
    
    #[Test]
    public function login_fails_with_incorrect_password(): void
    {
        $this->admin = SuperAdmin::factory()->create(['password' => 'password123']);
    
        $this->postJson('/api/super-admin/login', [
            'email'    => $this->admin->email,
            'password' => 'wrongpassword',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    }
    
    #[Test]
    public function login_fails_for_inactive_super_admin(): void
    {
        // Inactive admins must be blocked even with correct credentials
        // This prevents suspended platform admins from accessing the system
        $this->admin = SuperAdmin::factory()->inactive()->create(['password' => 'password123']);
    
        $this->postJson('/api/super-admin/login', [
            'email'    => $this->admin->email,
            'password' => 'password123',
        ])->assertStatus(422);
    }
    
    #[Test]
    public function login_fails_with_missing_fields():void 
    {
        $this->postJson('/api/super-admin/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
    
    /*
     * Logout tests verify token revocation.
     * The key check is that the token is removed from the DB, not just
     * that the response says 200 — a 200 with the token still active would be a bug.
     */
    #[Test]
    public function super_admin_can_logout(): void
    {
        $this->admin = SuperAdmin::factory()->create();
        $token = $this->admin->createToken('test-token')->plainTextToken;
    
        $this->withToken($token)
            ->postJson('/api/super-admin/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    
        // Refresh from DB — the in-memory relationship is cached and stale after logout
        expect($this->admin->fresh()->tokens()->count())->toBe(0);
    }
    
    #[Test]
    public function logout_only_revokes_the_current_token_not_all_tokens(): void
    {
        // An admin logged in on multiple devices should only lose the current session
        $this->admin        = SuperAdmin::factory()->create();
        $activeToken  = $this->admin->createToken('device-1')->plainTextToken;
        $this->admin->createToken('device-2'); // second token, should survive
    
        $this->withToken($activeToken)
            ->postJson('/api/super-admin/logout')
            ->assertStatus(200);
    
        expect($this->admin->fresh()->tokens()->count())->toBe(1);
    }
    
    #[Test]
    public function unauthenticated_request_to_logout_returns_401(): void
    {
        $this->postJson('/api/super-admin/logout')
            ->assertStatus(401);
    }
    
    /*
     * /me endpoint tests verify the authenticated admin's profile is returned.
     * Uses actingAs with the sanctum guard explicitly to avoid guard resolution issues.
     */
    #[Test]
    public function super_admin_can_fetch_their_own_profile(): void
    {
        $this->admin = SuperAdmin::factory()->create();
    
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/super-admin/me')
            ->assertStatus(200)
            ->assertJson([
                'id'    => $this->admin->id,
                'email' => $this->admin->email,
                'name'  => $this->admin->name,
            ]);
    }
    
    
    #[Test]
    public function profile_response_does_not_expose_password(): void
    {
        // Ensures the hidden field is respected in JSON output
        $this->admin = SuperAdmin::factory()->create();
    
        $response = $this->actingAs($this-> admin, 'sanctum')
            ->getJson('/api/super-admin/me')
            ->assertStatus(200);
    
        expect($response->json())->not->toHaveKey('password');
    }
    
    #[Test]
    public function unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson('/api/super-admin/me')
            ->assertStatus(401);
    }
}