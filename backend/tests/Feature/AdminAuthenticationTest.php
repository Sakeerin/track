<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles and permissions
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'roles',
                            'permissions',
                        ],
                        'token',
                        'expires_at',
                    ],
                    'timestamp',
                ])
                ->assertJson([
                    'success' => true,
                ]);

        // Verify token is returned
        $this->assertNotEmpty($response->json('data.token'));

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);

        // Verify failed login audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login_failed',
        ]);
    }

    /** @test */
    public function inactive_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error_code' => 'ACCOUNT_DISABLED',
                ]);
    }

    /** @test */
    public function authenticated_user_can_get_their_profile()
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->getJson('/api/auth/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully',
                ]);

        // Verify token is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'logout',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function user_can_logout_from_all_devices()
    {
        $user = User::factory()->create(['is_active' => true]);
        
        // Create multiple tokens
        $user->createToken('token-1');
        $user->createToken('token-2');
        $token = $user->createToken('token-3')->plainTextToken;

        $this->assertEquals(3, $user->tokens()->count());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                        ->postJson('/api/auth/logout-all');

        $response->assertStatus(200);

        // All tokens should be revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    /** @test */
    public function user_can_refresh_token()
    {
        $user = User::factory()->create(['is_active' => true]);
        $oldToken = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
                        ->postJson('/api/auth/refresh');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'token',
                        'expires_at',
                    ],
                ]);

        // New token should be different
        $newToken = $response->json('data.token');
        $this->assertNotEquals($oldToken, $newToken);
    }

    /** @test */
    public function login_updates_last_login_timestamp()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'last_login_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    /** @test */
    public function validation_fails_for_invalid_email()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function validation_fails_for_short_password()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }
}
