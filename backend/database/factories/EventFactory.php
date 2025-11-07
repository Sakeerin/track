<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Facility;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventCodes = ['PICKUP', 'IN_TRANSIT', 'AT_HUB', 'OUT_FOR_DELIVERY', 'DELIVERED', 'EXCEPTION'];
        $sources = ['handheld', 'partner_api', 'batch_upload', 'manual'];
        
        $eventCode = $this->faker->randomElement($eventCodes);
        
        return [
            'shipment_id' => Shipment::factory(),
            'event_id' => $this->faker->unique()->uuid,
            'event_code' => $eventCode,
            'event_time' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'facility_id' => Facility::factory(),
            'location_id' => null,
            'description' => $this->getDescriptionForEventCode($eventCode),
            'remarks' => $this->faker->optional()->sentence,
            'raw_payload' => [
                'original_event_code' => $eventCode,
                'partner_id' => $this->faker->randomElement(['PARTNER_A', 'PARTNER_B', 'INTERNAL']),
                'scanner_id' => $this->faker->optional()->bothify('SC-####'),
            ],
            'source' => $this->faker->randomElement($sources),
        ];
    }

    /**
     * Get description for event code
     */
    private function getDescriptionForEventCode(string $eventCode): string
    {
        $descriptions = [
            'PICKUP' => 'Package picked up from sender',
            'IN_TRANSIT' => 'Package in transit to destination',
            'AT_HUB' => 'Package arrived at sorting hub',
            'OUT_FOR_DELIVERY' => 'Package out for delivery',
            'DELIVERED' => 'Package delivered successfully',
            'EXCEPTION' => 'Delivery exception occurred',
        ];

        return $descriptions[$eventCode] ?? 'Event occurred';
    }

    /**
     * Indicate that the event is a pickup event.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_code' => 'PICKUP',
            'description' => 'Package picked up from sender',
        ]);
    }

    /**
     * Indicate that the event is a delivery event.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_code' => 'DELIVERED',
            'description' => 'Package delivered successfully',
        ]);
    }

    /**
     * Indicate that the event is an exception.
     */
    public function exception(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_code' => 'EXCEPTION',
            'description' => 'Delivery exception - address incomplete',
            'remarks' => 'Customer contact required',
        ]);
    }

    /**
     * Indicate that the event is from a handheld scanner.
     */
    public function fromHandheld(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'handheld',
            'raw_payload' => array_merge($attributes['raw_payload'] ?? [], [
                'scanner_id' => $this->faker->bothify('SC-####'),
                'operator_id' => $this->faker->numerify('OP###'),
            ]),
        ]);
    }

    /**
     * Indicate that the event is from a partner API.
     */
    public function fromPartnerApi(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'partner_api',
            'raw_payload' => array_merge($attributes['raw_payload'] ?? [], [
                'partner_id' => $this->faker->randomElement(['PARTNER_A', 'PARTNER_B']),
                'api_version' => 'v1.0',
            ]),
        ]);
    }
}