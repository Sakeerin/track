<?php

namespace Tests\Unit;

use App\Jobs\RecalculateETAJob;
use App\Models\EtaLane;
use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Services\ETA\ETACalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RecalculateETAJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_recalculates_eta_for_valid_shipment()
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
            'event_time' => Carbon::now(),
        ]);

        $job = new RecalculateETAJob($shipment->id, 'Test recalculation');

        // Act
        $job->handle(new ETACalculationService());

        // Assert
        $shipment->refresh();
        $this->assertNotNull($shipment->estimated_delivery);
    }

    /** @test */
    public function it_handles_non_existent_shipment_gracefully()
    {
        // Arrange
        Log::shouldReceive('warning')
            ->once()
            ->with('ETA recalculation failed: Shipment not found', [
                'shipment_id' => 'non-existent-id',
            ]);

        $job = new RecalculateETAJob('non-existent-id', 'Test');

        // Act & Assert - Should not throw exception
        $job->handle(new ETACalculationService());
    }

    /** @test */
    public function it_logs_successful_recalculation()
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
            'estimated_delivery' => Carbon::now()->addDays(1),
        ]);

        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => Carbon::now(),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('ETA recalculated successfully', \Mockery::type('array'));

        $job = new RecalculateETAJob($shipment->id, 'Event PICKUP occurred');

        // Act
        $job->handle(new ETACalculationService());
    }

    /** @test */
    public function it_logs_warning_when_eta_calculation_returns_null()
    {
        // Arrange
        $shipment = Shipment::factory()->create([
            'origin_facility_id' => Facility::factory()->create()->id,
            'destination_facility_id' => Facility::factory()->create()->id,
            'service_type' => 'standard',
        ]);

        // No lane exists, so ETA calculation will return null

        Log::shouldReceive('warning')
            ->once()
            ->with('ETA recalculation resulted in null ETA', \Mockery::type('array'));

        $job = new RecalculateETAJob($shipment->id, 'Test');

        // Act
        $job->handle(new ETACalculationService());
    }

    /** @test */
    public function it_handles_exceptions_during_recalculation()
    {
        // Arrange
        $shipment = Shipment::factory()->create();

        // Mock service to throw exception
        $mockService = \Mockery::mock(ETACalculationService::class);
        $mockService->shouldReceive('recalculateETA')
            ->andThrow(new \Exception('Test exception'));

        Log::shouldReceive('error')
            ->once()
            ->with('ETA recalculation failed with exception', \Mockery::type('array'));

        $job = new RecalculateETAJob($shipment->id, 'Test');

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');
        
        $job->handle($mockService);
    }

    /** @test */
    public function it_logs_permanent_failure()
    {
        // Arrange
        $exception = new \Exception('Permanent failure');
        
        Log::shouldReceive('error')
            ->once()
            ->with('ETA recalculation job failed permanently', [
                'shipment_id' => 'test-id',
                'reason' => 'Test reason',
                'error' => 'Permanent failure',
            ]);

        $job = new RecalculateETAJob('test-id', 'Test reason');

        // Act
        $job->failed($exception);
    }

    /** @test */
    public function it_uses_eta_queue()
    {
        // Arrange
        $job = new RecalculateETAJob('test-id');

        // Act & Assert
        $this->assertEquals('eta', $job->queue);
    }
}