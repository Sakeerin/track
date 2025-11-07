<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some facilities for relationships
        $facilities = Facility::all();
        
        if ($facilities->isEmpty()) {
            $this->command->warn('No facilities found. Please run FacilitySeeder first.');
            return;
        }

        // Create shipments with different statuses
        $this->createShipmentsWithEvents($facilities);
        
        // Create some subscriptions
        $this->createSubscriptions();
    }

    /**
     * Create shipments with realistic event sequences
     */
    private function createShipmentsWithEvents($facilities): void
    {
        $hubs = $facilities->where('facility_type', 'hub');
        $deliveryOffices = $facilities->where('facility_type', 'delivery_office');

        // Create delivered shipments
        for ($i = 0; $i < 10; $i++) {
            $shipment = Shipment::factory()->create([
                'origin_facility_id' => $hubs->random()->id,
                'destination_facility_id' => $deliveryOffices->random()->id,
                'current_status' => 'delivered',
            ]);

            $this->createEventSequence($shipment, 'delivered', $facilities);
        }

        // Create in-transit shipments
        for ($i = 0; $i < 15; $i++) {
            $shipment = Shipment::factory()->create([
                'origin_facility_id' => $hubs->random()->id,
                'destination_facility_id' => $deliveryOffices->random()->id,
                'current_status' => 'in_transit',
            ]);

            $this->createEventSequence($shipment, 'in_transit', $facilities);
        }

        // Create out for delivery shipments
        for ($i = 0; $i < 8; $i++) {
            $shipment = Shipment::factory()->create([
                'origin_facility_id' => $hubs->random()->id,
                'destination_facility_id' => $deliveryOffices->random()->id,
                'current_status' => 'out_for_delivery',
            ]);

            $this->createEventSequence($shipment, 'out_for_delivery', $facilities);
        }

        // Create exception shipments
        for ($i = 0; $i < 5; $i++) {
            $shipment = Shipment::factory()->create([
                'origin_facility_id' => $hubs->random()->id,
                'destination_facility_id' => $deliveryOffices->random()->id,
                'current_status' => 'exception',
            ]);

            $this->createEventSequence($shipment, 'exception', $facilities);
        }
    }

    /**
     * Create realistic event sequence for a shipment
     */
    private function createEventSequence(Shipment $shipment, string $finalStatus, $facilities): void
    {
        $baseTime = now()->subDays(rand(1, 7));
        $eventTime = $baseTime;

        // Always start with pickup
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'PICKUP',
            'event_time' => $eventTime,
            'facility_id' => $shipment->origin_facility_id,
            'description' => 'Package picked up from sender',
            'source' => 'handheld',
        ]);

        $eventTime = $eventTime->addHours(rand(2, 6));

        // Add in transit event
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'IN_TRANSIT',
            'event_time' => $eventTime,
            'facility_id' => $facilities->random()->id,
            'description' => 'Package in transit to destination',
            'source' => 'partner_api',
        ]);

        $eventTime = $eventTime->addHours(rand(4, 12));

        // Add hub arrival
        Event::factory()->create([
            'shipment_id' => $shipment->id,
            'event_code' => 'AT_HUB',
            'event_time' => $eventTime,
            'facility_id' => $facilities->where('facility_type', 'hub')->random()->id,
            'description' => 'Package arrived at sorting hub',
            'source' => 'handheld',
        ]);

        // Continue based on final status
        switch ($finalStatus) {
            case 'delivered':
                $eventTime = $eventTime->addHours(rand(6, 24));
                
                Event::factory()->create([
                    'shipment_id' => $shipment->id,
                    'event_code' => 'OUT_FOR_DELIVERY',
                    'event_time' => $eventTime,
                    'facility_id' => $shipment->destination_facility_id,
                    'description' => 'Package out for delivery',
                    'source' => 'handheld',
                ]);

                $eventTime = $eventTime->addHours(rand(2, 8));
                
                Event::factory()->create([
                    'shipment_id' => $shipment->id,
                    'event_code' => 'DELIVERED',
                    'event_time' => $eventTime,
                    'facility_id' => $shipment->destination_facility_id,
                    'description' => 'Package delivered successfully',
                    'source' => 'handheld',
                ]);
                break;

            case 'out_for_delivery':
                $eventTime = $eventTime->addHours(rand(6, 24));
                
                Event::factory()->create([
                    'shipment_id' => $shipment->id,
                    'event_code' => 'OUT_FOR_DELIVERY',
                    'event_time' => $eventTime,
                    'facility_id' => $shipment->destination_facility_id,
                    'description' => 'Package out for delivery',
                    'source' => 'handheld',
                ]);
                break;

            case 'exception':
                $eventTime = $eventTime->addHours(rand(6, 24));
                
                Event::factory()->create([
                    'shipment_id' => $shipment->id,
                    'event_code' => 'OUT_FOR_DELIVERY',
                    'event_time' => $eventTime,
                    'facility_id' => $shipment->destination_facility_id,
                    'description' => 'Package out for delivery',
                    'source' => 'handheld',
                ]);

                $eventTime = $eventTime->addHours(rand(2, 6));
                
                Event::factory()->create([
                    'shipment_id' => $shipment->id,
                    'event_code' => 'EXCEPTION',
                    'event_time' => $eventTime,
                    'facility_id' => $shipment->destination_facility_id,
                    'description' => 'Delivery exception - address incomplete',
                    'remarks' => 'Customer contact required',
                    'source' => 'handheld',
                ]);
                break;
        }

        // Update shipment current status based on latest event
        $shipment->updateCurrentStatus();
    }

    /**
     * Create subscriptions for some shipments
     */
    private function createSubscriptions(): void
    {
        $shipments = Shipment::limit(20)->get();

        foreach ($shipments as $shipment) {
            // 70% chance of having at least one subscription
            if (rand(1, 100) <= 70) {
                $subscriptionCount = rand(1, 3);
                
                for ($i = 0; $i < $subscriptionCount; $i++) {
                    Subscription::factory()->create([
                        'shipment_id' => $shipment->id,
                    ]);
                }
            }
        }
    }
}