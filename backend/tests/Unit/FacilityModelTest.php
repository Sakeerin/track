<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_facility_can_be_created_with_required_fields()
    {
        $facility = Facility::create([
            'code' => 'TEST01',
            'name' => 'Test Facility',
            'facility_type' => 'hub',
        ]);

        $this->assertDatabaseHas('facilities', [
            'code' => 'TEST01',
            'name' => 'Test Facility',
            'facility_type' => 'hub',
            'active' => true, // default value
        ]);

        $this->assertTrue($facility->active);
        $this->assertEquals('Asia/Bangkok', $facility->timezone);
    }

    public function test_facility_casts_coordinates_to_decimal()
    {
        $facility = Facility::factory()->create([
            'latitude' => '13.7563000',
            'longitude' => '100.5018000',
        ]);

        $this->assertIsFloat($facility->latitude);
        $this->assertIsFloat($facility->longitude);
        $this->assertEquals(13.7563, $facility->latitude);
        $this->assertEquals(100.5018, $facility->longitude);
    }

    public function test_facility_has_origin_shipments_relationship()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create([
            'origin_facility_id' => $facility->id,
        ]);

        $this->assertTrue($facility->originShipments->contains($shipment));
        $this->assertCount(1, $facility->originShipments);
    }

    public function test_facility_has_destination_shipments_relationship()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create([
            'destination_facility_id' => $facility->id,
        ]);

        $this->assertTrue($facility->destinationShipments->contains($shipment));
        $this->assertCount(1, $facility->destinationShipments);
    }

    public function test_facility_has_current_shipments_relationship()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create([
            'current_location_id' => $facility->id,
        ]);

        $this->assertTrue($facility->currentShipments->contains($shipment));
        $this->assertCount(1, $facility->currentShipments);
    }

    public function test_facility_has_events_relationship()
    {
        $facility = Facility::factory()->create();
        $shipment = Shipment::factory()->create();
        $event = Event::factory()->create([
            'shipment_id' => $shipment->id,
            'facility_id' => $facility->id,
        ]);

        $this->assertTrue($facility->events->contains($event));
        $this->assertCount(1, $facility->events);
    }

    public function test_active_scope_filters_active_facilities()
    {
        $activeFacility = Facility::factory()->create(['active' => true]);
        $inactiveFacility = Facility::factory()->create(['active' => false]);

        $activeFacilities = Facility::active()->get();

        $this->assertTrue($activeFacilities->contains($activeFacility));
        $this->assertFalse($activeFacilities->contains($inactiveFacility));
    }

    public function test_of_type_scope_filters_by_facility_type()
    {
        $hub = Facility::factory()->create(['facility_type' => 'hub']);
        $depot = Facility::factory()->create(['facility_type' => 'depot']);

        $hubs = Facility::ofType('hub')->get();

        $this->assertTrue($hubs->contains($hub));
        $this->assertFalse($hubs->contains($depot));
    }

    public function test_facility_code_must_be_unique()
    {
        Facility::factory()->create(['code' => 'UNIQUE01']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Facility::factory()->create(['code' => 'UNIQUE01']);
    }
}