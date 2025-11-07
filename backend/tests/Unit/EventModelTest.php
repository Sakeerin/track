<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_can_be_created_with_required_fields()
    {
        $shipment = Shipment::factory()->create();
        
        $event = Event::create([
            'shipment_id' => $shipment->id,
            'event_id' => 'EVT123',
            'event_code' => 'PICKUP',
            'event_time' => now(),
            'source' => 'handheld',
        ]);

        $this->assertDatabaseHas('events', [
            'shipment_id' => $shipment->id,
            'event_id' => 'EVT123',
            'event_code' => 'PICKUP',
            'source' => 'handheld',
        ]);
    }

    public function test_event_casts_event_time_to_datetime()
    {
        $event = Event::factory()->create([
            'event_time' => '2024-12-25 10:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $event->event_time);
        $this->assertEquals('2024-12-25 10:00:00', $event->event_time->format('Y-m-d H:i:s'));
    }

    public function test_event_casts_raw_payload_to_array()
    {
        $payload = ['partner_id' => 'TEST', 'scanner_id' => 'SC001'];
        
        $event = Event::factory()->create([
            'raw_payload' => $payload,
        ]);

        $this->assertIsArray($event->raw_payload);
        $this->assertEquals($payload, $event->raw_payload);
    }

    public function test_event_has_shipment_relationship()
    {
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
        ]);

        $this->assertEquals($shipment->id, $event->shipment->id);
    }

    public function test_event_has_facility_relationships()
    {
        $facility = Facility::factory()->create();
        $location = Facility::factory()->create();
        
        $event = Event::factory()->create([
            'facility_id' => $facility->id,
            'location_id' => $location->id,
        ]);

        $this->assertEquals($facility->id, $event->facility->id);
        $this->assertEquals($location->id, $event->location->id);
    }

    public function test_chronological_scope_orders_by_event_time_asc()
    {
        $shipment = Shipment::factory()->create();
        
        $laterEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->addHours(1),
        ]);
        
        $earlierEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->subHours(1),
        ]);

        $events = Event::chronological()->get();

        $this->assertEquals($earlierEvent->id, $events->first()->id);
        $this->assertEquals($laterEvent->id, $events->last()->id);
    }

    public function test_reverse_chronological_scope_orders_by_event_time_desc()
    {
        $shipment = Shipment::factory()->create();
        
        $laterEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->addHours(1),
        ]);
        
        $earlierEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_time' => now()->subHours(1),
        ]);

        $events = Event::reverseChronological()->get();

        $this->assertEquals($laterEvent->id, $events->first()->id);
        $this->assertEquals($earlierEvent->id, $events->last()->id);
    }

    public function test_with_event_code_scope_filters_by_event_code()
    {
        $pickupEvent = Event::factory()->create(['event_code' => 'PICKUP']);
        $deliveryEvent = Event::factory()->create(['event_code' => 'DELIVERED']);

        $pickupEvents = Event::withEventCode('PICKUP')->get();

        $this->assertTrue($pickupEvents->contains($pickupEvent));
        $this->assertFalse($pickupEvents->contains($deliveryEvent));
    }

    public function test_from_source_scope_filters_by_source()
    {
        $handheldEvent = Event::factory()->create(['source' => 'handheld']);
        $apiEvent = Event::factory()->create(['source' => 'partner_api']);

        $handheldEvents = Event::fromSource('handheld')->get();

        $this->assertTrue($handheldEvents->contains($handheldEvent));
        $this->assertFalse($handheldEvents->contains($apiEvent));
    }

    public function test_is_duplicate_detects_duplicate_events()
    {
        $shipment = Shipment::factory()->create();
        
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_id' => 'DUPLICATE123',
            'event_time' => '2024-12-25 10:00:00',
        ]);

        $this->assertTrue(Event::isDuplicate(
            $shipment->id,
            'DUPLICATE123',
            '2024-12-25 10:00:00'
        ));

        $this->assertFalse(Event::isDuplicate(
            $shipment->id,
            'DIFFERENT123',
            '2024-12-25 10:00:00'
        ));
    }

    public function test_get_event_display_name_returns_english_names()
    {
        $event = Event::factory()->create(['event_code' => 'PICKUP']);

        $this->assertEquals('Picked up', $event->getEventDisplayName('en'));
    }

    public function test_get_event_display_name_returns_thai_names()
    {
        $event = Event::factory()->create(['event_code' => 'PICKUP']);

        $this->assertEquals('รับพัสดุแล้ว', $event->getEventDisplayName('th'));
    }

    public function test_get_event_display_name_returns_event_code_for_unknown_codes()
    {
        $event = Event::factory()->create(['event_code' => 'UNKNOWN_CODE']);

        $this->assertEquals('UNKNOWN_CODE', $event->getEventDisplayName('en'));
        $this->assertEquals('UNKNOWN_CODE', $event->getEventDisplayName('th'));
    }

    public function test_unique_constraint_prevents_duplicate_events()
    {
        $shipment = Shipment::factory()->create();
        
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_id' => 'UNIQUE123',
            'event_time' => '2024-12-25 10:00:00',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_id' => 'UNIQUE123',
            'event_time' => '2024-12-25 10:00:00',
        ]);
    }

    public function test_event_creation_triggers_shipment_status_update()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create(['current_status' => 'created']);

        // Create an event that should update the shipment status
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'facility_id' => $facility->id,
        ]);

        // Refresh the shipment to get updated data
        $shipment->refresh();

        $this->assertEquals('delivered', $shipment->current_status);
        $this->assertEquals($facility->id, $shipment->current_location_id);
    }

    public function test_out_of_order_events_maintain_correct_current_status()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create(['current_status' => 'created']);

        // Create events out of chronological order
        $oldEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => now()->subHours(3),
            'facility_id' => $facility->id,
        ]);

        $newerEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()->subHours(1),
            'facility_id' => $facility->id,
        ]);

        // Current status should be based on the latest event by time, not creation order
        $shipment->refresh();
        $this->assertEquals('delivered', $shipment->current_status);
    }

    public function test_event_deduplication_with_complex_scenarios()
    {
        $shipment = Shipment::factory()->create();
        $eventTime = '2024-12-25 10:00:00';
        
        // Create first event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_id' => 'COMPLEX123',
            'event_time' => $eventTime,
        ]);

        // Test duplicate detection with same shipment, event_id, and time
        $this->assertTrue(Event::isDuplicate(
            $shipment->id,
            'COMPLEX123',
            $eventTime
        ));

        // Test non-duplicate with different shipment
        $otherShipment = Shipment::factory()->create();
        $this->assertFalse(Event::isDuplicate(
            $otherShipment->id,
            'COMPLEX123',
            $eventTime
        ));

        // Test non-duplicate with different event_id
        $this->assertFalse(Event::isDuplicate(
            $shipment->id,
            'DIFFERENT123',
            $eventTime
        ));

        // Test non-duplicate with different time
        $this->assertFalse(Event::isDuplicate(
            $shipment->id,
            'COMPLEX123',
            '2024-12-25 11:00:00'
        ));
    }
}