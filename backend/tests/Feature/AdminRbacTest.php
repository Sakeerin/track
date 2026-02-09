<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shipment;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $opsUser;
    protected User $csUser;
    protected User $readonlyUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles and permissions
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        
        // Create users with different roles
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        
        $this->opsUser = User::factory()->create(['is_active' => true]);
        $this->opsUser->assignRole('ops');
        
        $this->csUser = User::factory()->create(['is_active' => true]);
        $this->csUser->assignRole('cs');
        
        $this->readonlyUser = User::factory()->create(['is_active' => true]);
        $this->readonlyUser->assignRole('readonly');
    }

    protected function actingAsUser(User $user)
    {
        $token = $user->createToken('test-token')->plainTextToken;
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @test */
    public function admin_can_access_user_management()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    /** @test */
    public function ops_cannot_access_user_management()
    {
        $response = $this->actingAsUser($this->opsUser)
                        ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function cs_cannot_access_user_management()
    {
        $response = $this->actingAsUser($this->csUser)
                        ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_configuration()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/config/facilities');

        $response->assertStatus(200);
    }

    /** @test */
    public function ops_cannot_access_configuration()
    {
        $response = $this->actingAsUser($this->opsUser)
                        ->getJson('/api/admin/config/facilities');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_audit_logs()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);
    }

    /** @test */
    public function ops_cannot_access_audit_logs()
    {
        $response = $this->actingAsUser($this->opsUser)
                        ->getJson('/api/admin/audit-logs');

        $response->assertStatus(403);
    }

    /** @test */
    public function all_roles_can_view_dashboard()
    {
        // Admin
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/dashboard/stats');
        $response->assertStatus(200);

        // Ops
        $response = $this->actingAsUser($this->opsUser)
                        ->getJson('/api/admin/dashboard/stats');
        $response->assertStatus(200);

        // CS
        $response = $this->actingAsUser($this->csUser)
                        ->getJson('/api/admin/dashboard/stats');
        $response->assertStatus(200);

        // Readonly
        $response = $this->actingAsUser($this->readonlyUser)
                        ->getJson('/api/admin/dashboard/stats');
        $response->assertStatus(200);
    }

    /** @test */
    public function ops_cs_can_view_shipments()
    {
        Shipment::factory()->create();

        // Ops
        $response = $this->actingAsUser($this->opsUser)
                        ->getJson('/api/admin/shipments');
        $response->assertStatus(200);

        // CS
        $response = $this->actingAsUser($this->csUser)
                        ->getJson('/api/admin/shipments');
        $response->assertStatus(200);
    }

    /** @test */
    public function readonly_cannot_view_shipments()
    {
        $response = $this->actingAsUser($this->readonlyUser)
                        ->getJson('/api/admin/shipments');

        $response->assertStatus(403);
    }

    /** @test */
    public function ops_can_add_manual_events()
    {
        $shipment = Shipment::factory()->create();

        $response = $this->actingAsUser($this->opsUser)
                        ->postJson("/api/admin/shipments/{$shipment->id}/events", [
                            'event_code' => 'IN_TRANSIT',
                            'event_time' => now()->toISOString(),
                            'description' => 'Test event',
                        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function cs_cannot_add_manual_events()
    {
        $shipment = Shipment::factory()->create();

        $response = $this->actingAsUser($this->csUser)
                        ->postJson("/api/admin/shipments/{$shipment->id}/events", [
                            'event_code' => 'IN_TRANSIT',
                            'event_time' => now()->toISOString(),
                        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_user()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->postJson('/api/admin/users', [
                            'name' => 'New User',
                            'email' => 'newuser@example.com',
                            'password' => 'password123',
                            'password_confirmation' => 'password123',
                            'role' => 'readonly',
                        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    /** @test */
    public function admin_can_update_user_roles()
    {
        $targetUser = User::factory()->create(['is_active' => true]);
        $targetUser->assignRole('readonly');

        $response = $this->actingAsUser($this->adminUser)
                        ->putJson("/api/admin/users/{$targetUser->id}/roles", [
                            'roles' => ['ops'],
                        ]);

        $response->assertStatus(200);
        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('ops'));
        $this->assertFalse($targetUser->hasRole('readonly'));
    }

    /** @test */
    public function admin_cannot_modify_own_roles()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->putJson("/api/admin/users/{$this->adminUser->id}/roles", [
                            'roles' => ['readonly'],
                        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'error_code' => 'SELF_MODIFICATION',
                ]);
    }

    /** @test */
    public function admin_can_toggle_user_active_status()
    {
        $targetUser = User::factory()->create(['is_active' => true]);

        $response = $this->actingAsUser($this->adminUser)
                        ->postJson("/api/admin/users/{$targetUser->id}/toggle-active");

        $response->assertStatus(200);
        $targetUser->refresh();
        $this->assertFalse($targetUser->is_active);
    }

    /** @test */
    public function admin_cannot_deactivate_self()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->postJson("/api/admin/users/{$this->adminUser->id}/toggle-active");

        $response->assertStatus(403)
                ->assertJson([
                    'error_code' => 'SELF_MODIFICATION',
                ]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAsUser($this->adminUser)
                        ->deleteJson("/api/admin/users/{$targetUser->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    /** @test */
    public function admin_cannot_delete_self()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->deleteJson("/api/admin/users/{$this->adminUser->id}");

        $response->assertStatus(403)
                ->assertJson([
                    'error_code' => 'SELF_MODIFICATION',
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_routes()
    {
        $response = $this->getJson('/api/admin/dashboard/stats');
        $response->assertStatus(401);

        $response = $this->getJson('/api/admin/shipments');
        $response->assertStatus(401);

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(401);
    }

    /** @test */
    public function inactive_user_cannot_access_admin_routes()
    {
        $this->adminUser->update(['is_active' => false]);
        
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(403)
                ->assertJson([
                    'error_code' => 'ACCOUNT_DISABLED',
                ]);
    }
}
