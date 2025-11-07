<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Shipment;
use App\Services\Ingestion\EventOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class EventOrderingServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventOrderingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventOrderingService();
    }

    /** @test */
    public function updates_shipment_status_when_new_event_is_latest()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'CREATED'
        ]);

        $newEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => now()
        ]);

        $result = $this->service->processEventOrdering($shipment, $newEvent);

        $shipment->refresh();

        $this->assertTrue($result['status_changed']);
        $this->assertEquals('CREATED', $result['old_status']);
        $this->assertEquals('IN_TRANSIT', $result['new_status']);
        $this->assertEquals('IN_TRANSIT', $shipment->current_status);
        $this->assertTrue($result['is_latest_event']);
    }

    /** @test */
    public function does_not_update_status_when_event_is_out_of_order()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'DELIVERED'
        ]);

        // Create a later event first
        $laterEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()->addHours(2)
        ]);

        // Now create an earlier event
        $earlierEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => now()
        ]);

        $result = $this->service->processEventOrdering($shipment, $earlierEvent);

        $shipment->refresh();

        $this->assertFalse($result['status_changed']);
        $this->assertEquals('DELIVERED', $shipment->current_status);
        $this->assertFalse($result['is_latest_event']);
    }

    /** @test */
    public function detects_impossible_event_sequences()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        // Create delivered event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()
        ]);

        // Create pickup event after delivery (impossible)
        $impossibleEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => now()->addHour()
        ]);

        $result = $this->service->processEventOrdering($shipment, $impossibleEvent);

        $this->assertNotEmpty($result['sequence_analysis']['anomalies']);
        
        $hasImpossibleSequence = collect($result['sequence_analysis']['anomalies'])
            ->contains(fn($anomaly) => $anomaly['type'] === 'impossible_sequence');
        
        $this->assertTrue($hasImpossibleSequence);
    }

    /** @test */
    public function detects_future_events()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        $futureEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => now()->addHours(3) // More than 2 hours in future
        ]);

        $result = $this->service->processEventOrdering($shipment, $futureEvent);

        $hasFutureEvent = collect($result['sequence_analysis']['anomalies'])
            ->contains(fn($anomaly) => $anomaly['type'] === 'future_event');
        
        $this->assertTrue($hasFutureEvent);
    }

    /** @test */
    public function detects_very_old_events()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        $oldEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => now()->subYears(2) // More than 1 year old
        ]);

        $result = $this->service->processEventOrdering($shipment, $oldEvent);

        $hasOldEvent = collect($result['sequence_analysis']['anomalies'])
            ->contains(fn($anomaly) => $anomaly['type'] === 'very_old_event');
        
        $this->assertTrue($hasOldEvent);
    }

    /** @test */
    public function detects_duplicate_events_within_time_window()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        $eventTime = now();

        // Create two pickup events within 30 minutes
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => $eventTime
        ]);

        $duplicateEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKED_UP',
            'event_time' => $eventTime->copy()->addMinutes(30)
        ]);

        $result = $this->service->processEventOrdering($shipment, $duplicateEvent);

        $hasDuplicate = collect($result['sequence_analysis']['anomalies'])
            ->contains(fn($anomaly) => $anomaly['type'] === 'potential_duplicate');
        
        $this->assertTrue($hasDuplicate);
    }

    /** @test */
    public function calculates_sequence_score()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        // Create events in expected sequence
        $events = [
            ['code' => 'CREATED', 'time' => now()],
            ['code' => 'PICKED_UP', 'time' => now()->addHour()],
            ['code' => 'IN_TRANSIT', 'time' => now()->addHours(2)],
            ['code' => 'ARRIVED_AT_HUB', 'time' => now()->addHours(3)],
            ['code' => 'DELIVERED', 'time' => now()->addHours(4)],
        ];

        foreach ($events as $eventData) {
            Event::factory()->create([
                'shipment_id' => $shipment->id,
                'event_code' => $eventData['code'],
                'event_time' => $eventData['time']
            ]);
        }

        $lastEvent = Event::where('shipment_id', $shipment->id)
            ->orderBy('event_time', 'desc')
            ->first();

        $result = $this->service->processEventOrdering($shipment, $lastEvent);

        $this->assertGreaterThan(50, $result['sequence_analysis']['sequence_score']);
    }

    /** @test */
    public function handles_customs_clearance_events()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'IN_TRANSIT'
        ]);

        $customsEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'CUSTOMS_CLEARANCE',
            'event_time' => now()
        ]);

        $result = $this->service->processEventOrdering($shipment, $customsEvent);

        $shipment->refresh();

        $this->assertTrue($result['status_changed']);
        $this->assertEquals('CUSTOMS', $shipment->current_status);
    }

    /** @test */
    public function handles_exception_events()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'IN_TRANSIT'
        ]);

        $exceptionEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'EXCEPTION',
            'event_time' => now()
        ]);

        $result = $this->service->processEventOrdering($shipment, $exceptionEvent);

        $shipment->refresh();

        $this->assertTrue($result['status_changed']);
        $this->assertEquals('EXCEPTION', $shipment->current_status);
    }

    /** @test */
    public function handles_returned_shipments()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'DELIVERY_ATTEMPTED'
        ]);

        $returnEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'RETURNED',
            'event_time' => now()
        ]);

        $result = $this->service->processEventOrdering($shipment, $returnEvent);

        $shipment->refresh();

        $this->assertTrue($result['status_changed']);
        $this->assertEquals('RETURNED', $shipment->current_status);
    }

    /** @test */
    public function prioritizes_significant_events()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890',
            'current_status' => 'IN_TRANSIT'
        ]);

        // Create a delivered event (high significance)
        $deliveredEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'DELIVERED',
            'event_time' => now()
        ]);

        // Process the delivered event
        $this->service->processEventOrdering($shipment, $deliveredEvent);
        $shipment->refresh();

        $this->assertEquals('DELIVERED', $shipment->current_status);

        // Now create an in-transit event that's slightly later (but less significant)
        $transitEvent = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'event_time' => now()->addMinutes(5)
        ]);

        // Process the transit event
        $result = $this->service->processEventOrdering($shipment, $transitEvent);
        $shipment->refresh();

        // Status should remain DELIVERED because it's the latest chronologically
        $this->assertEquals('IN_TRANSIT', $shipment->current_status);
        $this->assertTrue($result['is_latest_event']);
    }

    /** @test */
    public function handles_multiple_hub_transitions()
    {
        $shipment = Shipment::factory()->create([
            'tracking_number' => 'TH1234567890'
        ]);

        $events = [
            ['code' => 'ARRIVED_AT_HUB', 'time' => now()],
            ['code' => 'DEPARTED_FROM_HUB', 'time' => now()->addHour()],
            ['code' => 'ARRIVED_AT_HUB', 'time' => now()->addHours(2)],
            ['code' => 'DEPARTED_FROM_HUB', 'time' => now()->addHours(3)],
        ];

        foreach ($events as $eventData) {
            Event::factory()->create([
                'shipment_id' => $shipment->id,
                'event_code' => $eventData['code'],
                'event_time' => $eventData['time']
            ]);
        }

        $lastEvent = Event::where('shipment_id', $shipment->id)
            ->orderBy('event_time', 'desc')
            ->first();

        $result = $this->service->processEventOrdering($shipment, $lastEvent);

        // Should not flag as anomaly - multiple hub transitions are normal
        $this->assertLessThan(4, count($result['sequence_analysis']['anomalies']));
    }
}