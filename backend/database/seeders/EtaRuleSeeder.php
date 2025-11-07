<?php

namespace Database\Seeders;

use App\Models\EtaRule;
use Illuminate\Database\Seeder;

class EtaRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'name' => 'Express Service Modifier',
                'rule_type' => 'service_modifier',
                'conditions' => [
                    'service_type' => 'express'
                ],
                'adjustments' => [
                    'multiplier' => 0.5 // 50% faster
                ],
                'priority' => 100,
                'description' => 'Express service delivers 50% faster than standard',
            ],
            [
                'name' => 'Economy Service Modifier',
                'rule_type' => 'service_modifier',
                'conditions' => [
                    'service_type' => 'economy'
                ],
                'adjustments' => [
                    'multiplier' => 1.5 // 50% slower
                ],
                'priority' => 100,
                'description' => 'Economy service takes 50% longer than standard',
            ],
            [
                'name' => 'Weekend Pickup Delay',
                'rule_type' => 'holiday_adjustment',
                'conditions' => [
                    'is_weekend_pickup' => true
                ],
                'adjustments' => [
                    'hours' => 24 // Add 1 day for weekend pickup
                ],
                'priority' => 80,
                'description' => 'Weekend pickups add 1 day to delivery time',
            ],
            [
                'name' => 'Holiday Delivery Delay',
                'rule_type' => 'holiday_adjustment',
                'conditions' => [
                    'is_holiday_delivery' => true
                ],
                'adjustments' => [
                    'days' => 1 // Add 1 day for holiday delivery
                ],
                'priority' => 90,
                'description' => 'Holiday deliveries are delayed by 1 day',
            ],
            [
                'name' => 'Late Cutoff Time',
                'rule_type' => 'cutoff_time',
                'conditions' => [
                    'pickup_hour' => ['gte' => 18] // After 6 PM
                ],
                'adjustments' => [
                    'hours' => 12 // Add 12 hours for late pickup
                ],
                'priority' => 70,
                'description' => 'Pickups after 6 PM are processed next business day',
            ],
            [
                'name' => 'Exception Handling Delay',
                'rule_type' => 'congestion',
                'conditions' => [
                    'has_exceptions' => true
                ],
                'adjustments' => [
                    'days' => 2 // Add 2 days for exception handling
                ],
                'priority' => 60,
                'description' => 'Shipments with exceptions require additional processing time',
            ],
            [
                'name' => 'Hub to Hub Express',
                'rule_type' => 'facility_modifier',
                'conditions' => [
                    'origin_facility_type' => 'hub',
                    'destination_facility_type' => 'hub',
                    'service_type' => 'express'
                ],
                'adjustments' => [
                    'hours' => -6 // 6 hours faster for hub-to-hub express
                ],
                'priority' => 50,
                'description' => 'Hub-to-hub express shipments are 6 hours faster',
            ],
        ];

        foreach ($rules as $rule) {
            EtaRule::create($rule);
        }
    }
}