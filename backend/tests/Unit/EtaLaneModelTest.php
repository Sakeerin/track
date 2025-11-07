<?php

namespace Tests\Unit;

use App\Models\EtaLane;
use App\Models\Facility;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtaLaneModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_eta_lane()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();

        // Act
        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 48,
            'min_hours' => 24,
            'max_hours' => 72,
        ]);

        // Assert
        $this->assertDatabaseHas('eta_lanes', [
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'standard',
            'base_hours' => 48,
        ]);

        $this->assertEquals($origin->id, $lane->origin_facility_id);
        $this->assertEquals($destination->id, $lane->destination_facility_id);
    }

    /** @test */
    public function it_has_facility_relationships()
    {
        // Arrange
        $origin = Facility::factory()->create(['name' => 'Bangkok Hub']);
        $destination = Facility::factory()->create(['name' => 'Chiang Mai Hub']);

        $lane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
        ]);

        // Act & Assert
        $this->assertEquals('Bangkok Hub', $lane->originFacility->name);
        $this->assertEquals('Chiang Mai Hub', $lane->destinationFacility->name);
    }

    /** @test */
    public function it_scopes_active_lanes()
    {
        // Arrange
        EtaLane::factory()->create(['active' => true]);
        EtaLane::factory()->create(['active' => true]);
        EtaLane::factory()->create(['active' => false]);

        // Act
        $activeLanes = EtaLane::active()->get();

        // Assert
        $this->assertCount(2, $activeLanes);
        $this->assertTrue($activeLanes->every(fn($lane) => $lane->active));
    }

    /** @test */
    public function it_scopes_lanes_by_service_type()
    {
        // Arrange
        EtaLane::factory()->create(['service_type' => 'express']);
        EtaLane::factory()->create(['service_type' => 'express']);
        EtaLane::factory()->create(['service_type' => 'standard']);

        // Act
        $expressLanes = EtaLane::forService('express')->get();

        // Assert
        $this->assertCount(2, $expressLanes);
        $this->assertTrue($expressLanes->every(fn($lane) => $lane->service_type === 'express'));
    }

    /** @test */
    public function it_finds_specific_lane()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();

        $targetLane = EtaLane::factory()->create([
            'origin_facility_id' => $origin->id,
            'destination_facility_id' => $destination->id,
            'service_type' => 'express',
            'active' => true,
        ]);

        // Create some other lanes
        EtaLane::factory()->create(['service_type' => 'standard']);
        EtaLane::factory()->create(['service_type' => 'express', 'active' => false]);

        // Act
        $foundLane = EtaLane::findLane($origin->id, $destination->id, 'express');

        // Assert
        $this->assertNotNull($foundLane);
        $this->assertEquals($targetLane->id, $foundLane->id);
    }

    /** @test */
    public function it_returns_null_when_lane_not_found()
    {
        // Arrange
        $origin = Facility::factory()->create();
        $destination = Facility::factory()->create();

        // Act
        $foundLane = EtaLane::findLane($origin->id, $destination->id, 'express');

        // Assert
        $this->assertNull($foundLane);
    }

    /** @test */
    public function it_calculates_base_hours_without_day_adjustments()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 48,
            'day_adjustments' => null,
        ]);

        $monday = Carbon::parse('next monday');

        // Act
        $adjustedHours = $lane->getAdjustedBaseHours($monday);

        // Assert
        $this->assertEquals(48, $adjustedHours);
    }

    /** @test */
    public function it_applies_day_adjustments()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 48,
            'day_adjustments' => [
                'friday' => 12,
                'saturday' => 24,
                'sunday' => 24,
            ],
        ]);

        $friday = Carbon::parse('next friday');
        $monday = Carbon::parse('next monday');

        // Act
        $fridayHours = $lane->getAdjustedBaseHours($friday);
        $mondayHours = $lane->getAdjustedBaseHours($monday);

        // Assert
        $this->assertEquals(60, $fridayHours); // 48 + 12
        $this->assertEquals(48, $mondayHours); // No adjustment for Monday
    }

    /** @test */
    public function it_calculates_eta_with_base_hours()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 24,
            'day_adjustments' => null,
        ]);

        $pickupTime = Carbon::parse('2024-01-01 10:00:00');

        // Act
        $eta = $lane->calculateEta($pickupTime);

        // Assert
        $expectedEta = Carbon::parse('2024-01-02 10:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_calculates_eta_with_day_adjustments()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 24,
            'day_adjustments' => [
                'friday' => 12,
            ],
        ]);

        $fridayPickup = Carbon::parse('next friday 10:00');

        // Act
        $eta = $lane->calculateEta($fridayPickup);

        // Assert
        // Should be 36 hours (24 + 12) from Friday 10:00
        $expectedEta = $fridayPickup->copy()->addHours(36);
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_enforces_minimum_hours_constraint()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 12,
            'min_hours' => 24, // Minimum is higher than base
            'max_hours' => 48,
        ]);

        $pickupTime = Carbon::parse('2024-01-01 10:00:00');

        // Act
        $eta = $lane->calculateEta($pickupTime);

        // Assert
        // Should use minimum hours (24) instead of base hours (12)
        $expectedEta = Carbon::parse('2024-01-02 10:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_enforces_maximum_hours_constraint()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 72,
            'min_hours' => 24,
            'max_hours' => 48, // Maximum is lower than base
        ]);

        $pickupTime = Carbon::parse('2024-01-01 10:00:00');

        // Act
        $eta = $lane->calculateEta($pickupTime);

        // Assert
        // Should use maximum hours (48) instead of base hours (72)
        $expectedEta = Carbon::parse('2024-01-03 10:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_both_min_and_max_constraints()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 36,
            'min_hours' => 24,
            'max_hours' => 48,
        ]);

        $pickupTime = Carbon::parse('2024-01-01 10:00:00');

        // Act
        $eta = $lane->calculateEta($pickupTime);

        // Assert
        // Should use base hours (36) as it's within min/max range
        $expectedEta = Carbon::parse('2024-01-02 22:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_handles_null_min_max_constraints()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 36,
            'min_hours' => null,
            'max_hours' => null,
        ]);

        $pickupTime = Carbon::parse('2024-01-01 10:00:00');

        // Act
        $eta = $lane->calculateEta($pickupTime);

        // Assert
        // Should use base hours without any constraints
        $expectedEta = Carbon::parse('2024-01-02 22:00:00');
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function it_combines_day_adjustments_with_constraints()
    {
        // Arrange
        $lane = EtaLane::factory()->create([
            'base_hours' => 20,
            'min_hours' => 24,
            'max_hours' => 48,
            'day_adjustments' => [
                'friday' => 8, // Would make it 28 hours total
            ],
        ]);

        $fridayPickup = Carbon::parse('next friday 10:00');

        // Act
        $eta = $lane->calculateEta($fridayPickup);

        // Assert
        // Base (20) + Friday adjustment (8) = 28 hours, which is above min (24)
        $expectedEta = $fridayPickup->copy()->addHours(28);
        $this->assertEquals($expectedEta->format('Y-m-d H:i:s'), $eta->format('Y-m-d H:i:s'));
    }
}