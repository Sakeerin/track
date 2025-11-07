<?php

namespace Database\Factories;

use App\Models\EtaLane;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EtaLane>
 */
class EtaLaneFactory extends Factory
{
    protected $model = EtaLane::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceType = $this->faker->randomElement(['standard', 'express', 'economy']);
        $baseHours = $this->getBaseHoursForService($serviceType);

        return [
            'origin_facility_id' => Facility::factory(),
            'destination_facility_id' => Facility::factory(),
            'service_type' => $serviceType,
            'base_hours' => $baseHours,
            'min_hours' => (int) ($baseHours * 0.7),
            'max_hours' => (int) ($baseHours * 1.5),
            'day_adjustments' => $this->generateDayAdjustments(),
            'active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Get base hours based on service type
     */
    private function getBaseHoursForService(string $serviceType): int
    {
        return match ($serviceType) {
            'express' => $this->faker->numberBetween(12, 24),
            'standard' => $this->faker->numberBetween(24, 72),
            'economy' => $this->faker->numberBetween(72, 120),
            default => 48,
        };
    }

    /**
     * Generate day adjustments
     */
    private function generateDayAdjustments(): ?array
    {
        if ($this->faker->boolean(30)) { // 30% chance of having day adjustments
            return [
                'friday' => $this->faker->numberBetween(0, 12),
                'saturday' => $this->faker->numberBetween(12, 24),
                'sunday' => $this->faker->numberBetween(12, 24),
            ];
        }

        return null;
    }

    /**
     * Create an active lane
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Create an inactive lane
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Create an express service lane
     */
    public function express(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'express',
            'base_hours' => $this->faker->numberBetween(12, 24),
            'min_hours' => 8,
            'max_hours' => 36,
        ]);
    }

    /**
     * Create a standard service lane
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'standard',
            'base_hours' => $this->faker->numberBetween(24, 72),
            'min_hours' => 18,
            'max_hours' => 96,
        ]);
    }

    /**
     * Create an economy service lane
     */
    public function economy(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'economy',
            'base_hours' => $this->faker->numberBetween(72, 120),
            'min_hours' => 48,
            'max_hours' => 168,
        ]);
    }

    /**
     * Create a same-day delivery lane
     */
    public function sameDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'express',
            'base_hours' => $this->faker->numberBetween(2, 8),
            'min_hours' => 1,
            'max_hours' => 12,
        ]);
    }
}