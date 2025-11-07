<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_can_be_created_with_required_fields()
    {
        $shipment = Shipment::create([
            'tracking_number' => 'TH1234567890',
            'service_type' => 'standard',
            'current_status' => 'created',
        ]);

        $this->assertDatabaseHas('shipments', [
            'tracking_number' => 'TH1234567890',
            'service_type' => 'standard',
            'current_status' => 'created',
        ]);
    }

    public function test_shipment_casts_estimated_delivery_to_datetime()
    {
        $shipment = Shipment::factory()->create([
            'estimated_delivery' => '2024-12-25 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $shipment->estimated_delivery);
        $this->assertEquals('2024-12-25 10:00:00', $shipment->estimated_delivery->format('Y-m-d H:i:s'));
    }

    public function test_shipment_has_events_relationship_ordered_by_time_desc()
    {
        $shipment = Shipment::factory()->create();
        
        $oldEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->subHours(2),
        ]);
        
        $newEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->subHours(1),
        ]);

        $events = $shipment->events;
        
        $this->assertCount(2, $events);
        $this->assertEquals($newEvent->id, $events->first()->id);
        $this->assertEquals($oldEvent->id, $events->last()->id);
    }

    public function test_shipment_has_subscriptions_relationship()
    {
        $shipment = Shipment::factory()->create();
        $subscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->assertTrue($shipment->subscriptions->contains($subscription));
        $this->assertCount(1, $shipment->subscriptions);
    }

    public function test_shipment_has_facility_relationships()
    {
        $originFacility = Facility::factory()->create();
        $destinationFacility = Facility::factory()->create();
        $currentLocation = Facility::factory()->create();

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $originFacility->id,
            'destination_facility_id' => $destinationFacility->id,
            'current_location_id' => $currentLocation->id,
        ]);

        $this->assertEquals($originFacility->id, $shipment->originFacility->id);
        $this->assertEquals($destinationFacility->id, $shipment->destinationFacility->id);
        $this->assertEquals($currentLocation->id, $shipment->currentLocation->id);
    }

    public function test_with_status_scope_filters_by_status()
    {
        $deliveredShipment = Shipment::factory()->create(['current_status' => 'delivered']);
        $inTransitShipment = Shipment::factory()->create(['current_status' => 'in_transit']);

        $deliveredShipments = Shipment::withStatus('delivered')->get();

        $this->assertTrue($deliveredShipments->contains($deliveredShipment));
        $this->assertFalse($deliveredShipments->contains($inTransitShipment));
    }

    public function test_with_service_type_scope_filters_by_service_type()
    {
        $expressShipment = Shipment::factory()->create(['service_type' => 'express']);
        $standardShipment = Shipment::factory()->create(['service_type' => 'standard']);

        $expressShipments = Shipment::withServiceType('express')->get();

        $this->assertTrue($expressShipments->contains($expressShipment));
        $this->assertFalse($expressShipments->contains($standardShipment));
    }

    public function test_update_current_status_updates_from_latest_event()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create(['current_status' => 'created']);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subHours(2),
            'facility_id' => $facility->id,
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()->subHours(1),
            'facility_id' => $facility->id,
        ]);

        $shipment->updateCurrentStatus();

        $this->assertEquals('delivered', $shipment->fresh()->current_status);
        $this->assertEquals($facility->id, $shipment->fresh()->current_location_id);
    }

    public function test_has_exceptions_returns_true_when_exception_events_exist()
    {
        $shipment = Shipment::factory()->create();

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'EXCEPTION',
        ]);

        $this->assertTrue($shipment->hasExceptions());
    }

    public function test_has_exceptions_returns_false_when_no_exception_events()
    {
        $shipment = Shipment::factory()->create();

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
        ]);

        $this->assertFalse($shipment->hasExceptions());
    }

    public function test_active_subscriptions_returns_only_active_subscriptions()
    {
        $shipment = Shipment::factory()->create();
        
        $activeSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => true,
        ]);
        
        $inactiveSubscription = Subscription::factory()->create([
            'shipment_id' => $shipment->id,
            'active' => false,
        ]);

        $activeSubscriptions = $shipment->activeSubscriptions;

        $this->assertTrue($activeSubscriptions->contains($activeSubscription));
        $this->assertFalse($activeSubscriptions->contains($inactiveSubscription));
    }

    public function test_tracking_number_must_be_unique()
    {
        Shipment::factory()->create(['tracking_number' => 'TH1234567890']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Shipment::factory()->create(['tracking_number' => 'TH1234567890']);
    }
}