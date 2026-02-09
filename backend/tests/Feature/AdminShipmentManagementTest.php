<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Shipment;
use App\Models\Event;
use App\Models\Facility;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminShipmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $opsUser;
    protected Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        
        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->assignRole('admin');
        
        $this->opsUser = User::factory()->create(['is_active' => true]);
        $this->opsUser->assignRole('ops');
        
        $this->facility = Facility::factory()->create();
    }

    protected function actingAsUser(User $user)
    {
        $token = $user->createToken('test-token')->plainTextToken;
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @test */
    public function can_search_shipments_by_tracking_number()
    {
        Shipment::factory()->create(['tracking_number' => 'TH1234567890']);
        Shipment::factory()->create(['tracking_number' => 'TH0987654321']);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments?tracking_number=TH123');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'shipments',
                        'pagination',
                    ],
                ]);

        $shipments = $response->json('data.shipments');
        $this->assertCount(1, $shipments);
        $this->assertEquals('TH1234567890', $shipments[0]['tracking_number']);
    }

    /** @test */
    public function can_search_shipments_by_status()
    {
        Shipment::factory()->create(['current_status' => 'in_transit']);
        Shipment::factory()->create(['current_status' => 'delivered']);
        Shipment::factory()->create(['current_status' => 'in_transit']);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments?status=in_transit');

        $response->assertStatus(200);
        $shipments = $response->json('data.shipments');
        $this->assertCount(2, $shipments);
    }

    /** @test */
    public function can_search_shipments_by_date_range()
    {
        Shipment::factory()->create(['created_at' => now()->subDays(5)]);
        Shipment::factory()->create(['created_at' => now()->subDays(10)]);
        Shipment::factory()->create(['created_at' => now()]);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments?' . http_build_query([
                            'date_from' => now()->subDays(7)->toDateString(),
                            'date_to' => now()->toDateString(),
                        ]));

        $response->assertStatus(200);
        $shipments = $response->json('data.shipments');
        $this->assertCount(2, $shipments);
    }

    /** @test */
    public function can_sort_shipments()
    {
        Shipment::factory()->create(['tracking_number' => 'TH1111111111', 'created_at' => now()->subDay()]);
        Shipment::factory()->create(['tracking_number' => 'TH2222222222', 'created_at' => now()]);

        // Sort by created_at desc
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments?sort_by=created_at&sort_order=desc');

        $response->assertStatus(200);
        $shipments = $response->json('data.shipments');
        $this->assertEquals('TH2222222222', $shipments[0]['tracking_number']);
    }

    /** @test */
    public function can_get_shipment_details_with_events()
    {
        $shipment = Shipment::factory()->create();
        Event::factory()->count(3)->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson("/api/admin/shipments/{$shipment->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'shipment',
                        'events',
                        'subscriptions',
                    ],
                ]);

        $this->assertCount(3, $response->json('data.events'));
    }

    /** @test */
    public function can_get_shipment_with_raw_payloads()
    {
        $shipment = Shipment::factory()->create();
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'raw_payload' => ['source' => 'test', 'data' => ['key' => 'value']],
        ]);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson("/api/admin/shipments/{$shipment->id}?include_raw=true");

        $response->assertStatus(200);
        $event = $response->json('data.events.0');
        $this->assertArrayHasKey('raw_payload', $event);
    }

    /** @test */
    public function can_add_manual_event_to_shipment()
    {
        $shipment = Shipment::factory()->create(['current_status' => 'in_transit']);

        $response = $this->actingAsUser($this->opsUser)
                        ->postJson("/api/admin/shipments/{$shipment->id}/events", [
                            'event_code' => 'AT_HUB',
                            'event_time' => now()->toISOString(),
                            'description' => 'Arrived at sorting hub',
                            'facility_id' => $this->facility->id,
                            'notes' => 'Manual update by ops',
                        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => ['event'],
                    'message',
                ]);

        // Verify event was created
        $this->assertDatabaseHas('events', [
            'shipment_id' => $shipment->id,
            'event_code' => 'AT_HUB',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'manual_event',
            'user_id' => $this->opsUser->id,
            'entity_type' => Shipment::class,
            'entity_id' => $shipment->id,
        ]);
    }

    /** @test */
    public function adding_event_invalidates_cache()
    {
        $shipment = Shipment::factory()->create(['tracking_number' => 'TH1234567890']);
        
        // Pre-populate cache
        Cache::put("shipment:TH1234567890", ['cached' => true], 60);
        $this->assertTrue(Cache::has("shipment:TH1234567890"));

        $this->actingAsUser($this->opsUser)
            ->postJson("/api/admin/shipments/{$shipment->id}/events", [
                'event_code' => 'AT_HUB',
                'event_time' => now()->toISOString(),
            ]);

        // Cache should be invalidated
        $this->assertFalse(Cache::has("shipment:TH1234567890"));
    }

    /** @test */
    public function can_update_existing_event()
    {
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'description' => 'Original description',
        ]);

        $response = $this->actingAsUser($this->opsUser)
                        ->putJson("/api/admin/shipments/{$shipment->id}/events/{$event->id}", [
                            'event_code' => 'AT_HUB',
                            'description' => 'Updated description',
                            'notes' => 'Correcting event type',
                        ]);

        $response->assertStatus(200);

        $event->refresh();
        $this->assertEquals('AT_HUB', $event->event_code);
        $this->assertEquals('Updated description', $event->description);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event_correction',
            'user_id' => $this->opsUser->id,
            'entity_type' => Event::class,
            'entity_id' => $event->id,
        ]);
    }

    /** @test */
    public function updating_event_requires_notes()
    {
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAsUser($this->opsUser)
                        ->putJson("/api/admin/shipments/{$shipment->id}/events/{$event->id}", [
                            'event_code' => 'AT_HUB',
                            // Missing notes
                        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['notes']);
    }

    /** @test */
    public function can_delete_event()
    {
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAsUser($this->opsUser)
                        ->deleteJson("/api/admin/shipments/{$shipment->id}/events/{$event->id}", [
                            'notes' => 'Duplicate event',
                        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /** @test */
    public function deleting_event_requires_notes()
    {
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAsUser($this->opsUser)
                        ->deleteJson("/api/admin/shipments/{$shipment->id}/events/{$event->id}", [
                            // Missing notes
                        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['notes']);
    }

    /** @test */
    public function can_export_shipments()
    {
        Shipment::factory()->count(5)->create();

        $response = $this->actingAsUser($this->opsUser)
                        ->postJson('/api/admin/shipments/export', [
                            'status' => null,
                        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'count',
                ]);

        $this->assertEquals(5, $response->json('count'));

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'export',
            'user_id' => $this->opsUser->id,
        ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_shipment()
    {
        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments/nonexistent-id');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_event()
    {
        $shipment = Shipment::factory()->create();

        $response = $this->actingAsUser($this->opsUser)
                        ->putJson("/api/admin/shipments/{$shipment->id}/events/nonexistent-id", [
                            'event_code' => 'AT_HUB',
                            'notes' => 'Test',
                        ]);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error_code' => 'NOT_FOUND',
                ]);
    }

    /** @test */
    public function pagination_works_correctly()
    {
        Shipment::factory()->count(25)->create();

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson('/api/admin/shipments?per_page=10&page=2');

        $response->assertStatus(200);
        
        $pagination = $response->json('data.pagination');
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    /** @test */
    public function masks_contact_values_in_subscriptions()
    {
        $shipment = Shipment::factory()->create();
        $shipment->subscriptions()->create([
            'channel' => 'email',
            'contact_value' => 'test@example.com',
            'active' => true,
            'events' => ['DELIVERED'],
        ]);

        $response = $this->actingAsUser($this->adminUser)
                        ->getJson("/api/admin/shipments/{$shipment->id}");

        $response->assertStatus(200);
        $subscription = $response->json('data.subscriptions.0');
        
        // Should be masked: te**@example.com
        $this->assertStringContainsString('*', $subscription['contact_value']);
        $this->assertStringContainsString('@example.com', $subscription['contact_value']);
    }
}
