<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceTypes = ['standard', 'express', 'economy', 'same_day'];
        $statuses = ['created', 'picked_up', 'in_transit', 'at_hub', 'out_for_delivery', 'delivered'];

        return [
            'tracking_number' => 'TH' . $this->faker->unique()->numerify('##########'),
            'reference_number' => $this->faker->optional()->bothify('REF-####-????'),
            'service_type' => $this->faker->randomElement($serviceTypes),
            'origin_facility_id' => Facility::factory(),
            'destination_facility_id' => Facility::factory(),
            'current_status' => $this->faker->randomElement($statuses),
            'current_location_id' => null, // Will be set by events
            'estimated_delivery' => $this->faker->optional()->dateTimeBetween('now', '+7 days'),
        ];
    }

    /**
     * Indicate that the shipment is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_status' => 'delivered',
            'estimated_delivery' => $this->faker->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    /**
     * Indicate that the shipment is in transit.
     */
    public function inTransit(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_status' => 'in_transit',
            'estimated_delivery' => $this->faker->dateTimeBetween('now', '+5 days'),
        ]);
    }

    /**
     * Indicate that the shipment has an exception.
     */
    public function withException(): static
    {
        return $this->state(fn(array $attributes) => [
            'current_status' => 'exception',
        ]);
    }

    /**
     * Indicate that the shipment is express service.
     */
    public function express(): static
    {
        return $this->state(fn(array $attributes) => [
            'service_type' => 'express',
            'estimated_delivery' => $this->faker->dateTimeBetween('now', '+2 days'),
        ]);
    }
}
