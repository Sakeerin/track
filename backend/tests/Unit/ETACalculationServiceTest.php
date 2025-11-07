<?php

namespace Tests\Unit;

use App\Jobs\RecalculateETAJob;
use App\Models\EtaLane;
use App\Models\EtaRule;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Services\ETA\ETACalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ETACalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ETACalculationService $etaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->etaService = new ETACalculationService();
        Queue::fake();
    }

    /** @test */
    public function it_calculates_eta_based_on_lane_configuration()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 48,
            'day_adjustments' => null, // No day adjustments
            'min_hours' => null,
            'max_hours' => null,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
        ]);

        // Use a fixed time for consistent testing (Monday, not a holiday)
        $pickupTime = Carbon::parse('2024-01-08 12:00:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $pickupTime);

        // Assert
        $this->assertNotNull($eta);
        $expectedEta = $pickupTime->copy()->addHours(48);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_returns_null_when_no_lane_exists()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'origin_facility_id' => Facility::factory()->create()->id,
            'destination_facility_id' => Facility::factory()->create()->id,
            'service_type' => 'standard',
        ]);

        $pickupTime = Carbon::parse('2024-01-01 12:00:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $pickupTime);

        // Assert
        $this->assertNull($eta);
    }

    /** @test */
    public function it_applies_service_type_modifiers()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
            'base_hours' => 24,
            'day_adjustments' => null,
            'min_hours' => null,
            'max_hours' => null,
        ]);

        $rule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'express'],
            'adjustments' => ['multiplier' => 0.5],
            'priority' => 100,
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
        ]);

        // Use a Monday to avoid weekend adjustments
        $pickupTime = Carbon::parse('2024-01-08 12:00:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $pickupTime);

        // Assert
        $this->assertNotNull($eta);
        // Express service with 0.5 multiplier should be 12 hours (24 * 0.5)
        $expectedEta = $pickupTime->copy()->addHours(12);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_applies_day_of_week_adjustments()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 24,
            'day_adjustments' => [
                'friday' => 12, // Add 12 hours for Friday pickup
            ],
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
        ]);

        // Friday pickup
        $fridayPickup = Carbon::parse('next friday 10:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $fridayPickup);

        // Assert
        $this->assertNotNull($eta);
        // Should be 36 hours (24 base + 12 Friday adjustment)
        $expectedEta = $fridayPickup->copy()->addHours(36);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_applies_holiday_adjustments()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 24,
        ]);

        $rule = EtaRule::factory()->create([
            'rule_type' => 'holiday_adjustment',
            'conditions' => ['is_holiday_delivery' => true],
            'adjustments' => ['days' => 1],
            'priority' => 90,
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
        ]);

        // New Year's Day pickup (should result in holiday delivery)
        $holidayPickup = Carbon::parse('2024-12-31 10:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $holidayPickup);

        // Assert
        $this->assertNotNull($eta);
        // Should add 1 day for holiday delivery
        $expectedEta = $holidayPickup->copy()->addHours(24)->addDay();
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_applies_cutoff_time_adjustments()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 24,
        ]);

        $rule = EtaRule::factory()->create([
            'rule_type' => 'cutoff_time',
            'conditions' => ['pickup_hour' => ['gte' => 18]],
            'adjustments' => ['hours' => 12],
            'priority' => 70,
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
        ]);

        // Late pickup at 7 PM
        $latePickup = Carbon::parse('2024-01-01 19:00:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $latePickup);

        // Assert
        $this->assertNotNull($eta);
        // Should add 12 hours for late pickup
        $expectedEta = $latePickup->copy()->addHours(36); // 24 base + 12 cutoff
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_applies_multiple_rules_by_priority()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
            'base_hours' => 24,
        ]);

        // High priority rule (applied first)
        $serviceRule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'express'],
            'adjustments' => ['multiplier' => 0.5],
            'priority' => 100,
            'active' => true,
        ]);

        // Lower priority rule (applied second)
        $cutoffRule = EtaRule::factory()->create([
            'rule_type' => 'cutoff_time',
            'conditions' => ['pickup_hour' => ['gte' => 18]],
            'adjustments' => ['hours' => 6],
            'priority' => 70,
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
        ]);

        $latePickup = Carbon::parse('2024-01-01 19:00:00');

        // Act
        $eta = $this->etaService->calculateETA($shipment, $latePickup);

        // Assert
        $this->assertNotNull($eta);
        // Should be: 24 hours * 0.5 (express) + 6 hours (cutoff) = 18 hours
        $expectedEta = $latePickup->copy()->addHours(18);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_recalculates_eta_and_updates_shipment()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 48,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'estimated_delivery' => null,
        ]);

        // Create pickup event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => Carbon::parse('2024-01-01 12:00:00'),
        ]);

        // Act
        $eta = $this->etaService->recalculateETA($shipment);

        // Assert
        $this->assertNotNull($eta);
        $shipment->refresh();
        $this->assertNotNull($shipment->estimated_delivery);
        $this->assertEquals($eta->format('Y-m-d H:i'), $shipment->estimated_delivery->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_identifies_events_that_trigger_eta_recalculation()
    {
        // Arrange
        $triggerEvents = ['PICKUP', 'AT_HUB', 'OUT_FOR_DELIVERY', 'EXCEPTION', 'CUSTOMS'];
        $nonTriggerEvents = ['IN_TRANSIT', 'DELIVERED'];

        foreach ($triggerEvents as $eventCode) {
            $event = Event::factory()->make(['event_code' => $eventCode]);
            $this->assertTrue($this->etaService->shouldRecalculateETA($event));
        }

        foreach ($nonTriggerEvents as $eventCode) {
            $event = Event::factory()->make(['event_code' => $eventCode]);
            $this->assertFalse($this->etaService->shouldRecalculateETA($event));
        }
    }

    /** @test */
    public function it_gets_applicable_rules_for_shipment()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
            'base_hours' => 24,
        ]);

        $applicableRule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'express'],
            'adjustments' => ['multiplier' => 0.5],
            'active' => true,
        ]);

        $nonApplicableRule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'economy'],
            'adjustments' => ['multiplier' => 1.5],
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => Carbon::parse('2024-01-01 12:00:00'),
        ]);

        // Act
        $applicableRules = $this->etaService->getApplicableRules($shipment);

        // Assert
        $this->assertCount(1, $applicableRules);
        $this->assertEquals($applicableRule->id, $applicableRules->first()->id);
    }

    /** @test */
    public function it_respects_min_and_max_hours_constraints()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
            'base_hours' => 24,
            'min_hours' => 12,
            'max_hours' => 36,
        ]);

        // Rule that would make delivery too fast
        $fastRule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'express'],
            'adjustments' => ['multiplier' => 0.3], // Would result in 7.2 hours
            'active' => true,
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
        ]);

        $pickupTime = Carbon::now();

        // Act
        $eta = $this->etaService->calculateETA($shipment, $pickupTime);

        // Assert
        $this->assertNotNull($eta);
        // Should be constrained to minimum 12 hours
        $expectedEta = $pickupTime->copy()->addHours(12);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }

    /** @test */
    public function it_handles_inactive_rules()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();
        
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 24,
        ]);

        $inactiveRule = EtaRule::factory()->create([
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => 'standard'],
            'adjustments' => ['hours' => 24],
            'active' => false, // Inactive rule
        ]);

        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
        ]);

        $pickupTime = Carbon::now();

        // Act
        $eta = $this->etaService->calculateETA($shipment, $pickupTime);

        // Assert
        $this->assertNotNull($eta);
        // Should only use base hours (24), not apply inactive rule
        $expectedEta = $pickupTime->copy()->addHours(24);
        $this->assertEquals($expectedEta->format('Y-m-d H:i'), $eta->format('Y-m-d H:i'));
    }
}