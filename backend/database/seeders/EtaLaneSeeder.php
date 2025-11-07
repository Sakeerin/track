<?php

namespace Database\Seeders;

use App\Models\EtaLane;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class EtaLaneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some facilities for creating lanes
        $facilities = Facility::active()->get();
        
        if ($facilities->count() < 2) {
            $this->command->warn('Not enough facilities found. Please run FacilitySeeder first.');
            return;
        }

        // Create some sample lanes between major cities
        $lanes = [
            // Bangkok to Chiang Mai
            [
                'service_type' => 'standard',
                'base_hours' => 48, // 2 days
                'min_hours' => 36,
                'max_hours' => 72,
                'day_adjustments' => [
                    'friday' => 12, // Friday pickups take longer due to weekend
                    'saturday' => 24,
                    'sunday' => 24,
                ],
            ],
            [
                'service_type' => 'express',
                'base_hours' => 24, // 1 day
                'min_hours' => 18,
                'max_hours' => 36,
                'day_adjustments' => [
                    'friday' => 6,
                    'saturday' => 12,
                    'sunday' => 12,
                ],
            ],
            [
                'service_type' => 'economy',
                'base_hours' => 96, // 4 days
                'min_hours' => 72,
                'max_hours' => 120,
                'day_adjustments' => [
                    'friday' => 24,
                    'saturday' => 48,
                    'sunday' => 48,
                ],
            ],
        ];

        // Create lanes between first two facilities for each service type
        $origin = $facilities->first();
        $destination = $facilities->skip(1)->first();

        foreach ($lanes as $laneData) {
            EtaLane::create(array_merge($laneData, [
                'origin_facility_id' => $origin->id,
                'destination_facility_id' => $destination->id,
            ]));
        }

        // Create reverse lanes (destination to origin)
        foreach ($lanes as $laneData) {
            EtaLane::create(array_merge($laneData, [
                'origin_facility_id' => $destination->id,
                'destination_facility_id' => $origin->id,
            ]));
        }

        // Create some intra-city lanes (same origin and destination)
        if ($facilities->count() >= 3) {
            $cityFacility = $facilities->skip(2)->first();
            
            $intraCityLanes = [
                [
                    'service_type' => 'standard',
                    'base_hours' => 4, // Same day delivery
                    'min_hours' => 2,
                    'max_hours' => 8,
                ],
                [
                    'service_type' => 'express',
                    'base_hours' => 2, // 2 hour delivery
                    'min_hours' => 1,
                    'max_hours' => 4,
                ],
            ];

            foreach ($intraCityLanes as $laneData) {
                EtaLane::create(array_merge($laneData, [
                    'origin_facility_id' => $cityFacility->id,
                    'destination_facility_id' => $cityFacility->id,
                ]));
            }
        }

        $this->command->info('ETA lanes seeded successfully.');
    }
}