<?php

namespace Database\Factories;

use App\Models\EtaRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EtaRule>
 */
class EtaRuleFactory extends Factory
{
    protected $model = EtaRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ruleTypes = ['service_modifier', 'holiday_adjustment', 'cutoff_time', 'congestion'];
        $ruleType = $this->faker->randomElement($ruleTypes);

        return [
            'name' => $this->faker->unique()->words(3, true),
            'rule_type' => $ruleType,
            'conditions' => $this->generateConditions($ruleType),
            'adjustments' => $this->generateAdjustments(),
            'priority' => $this->faker->numberBetween(1, 100),
            'active' => $this->faker->boolean(80), // 80% chance of being active
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Generate conditions based on rule type
     */
    private function generateConditions(string $ruleType): array
    {
        switch ($ruleType) {
            case 'service_modifier':
                return [
                    'service_type' => $this->faker->randomElement(['standard', 'express', 'economy'])
                ];
            
            case 'holiday_adjustment':
                return [
                    'is_holiday_delivery' => true
                ];
            
            case 'cutoff_time':
                return [
                    'pickup_hour' => ['gte' => $this->faker->numberBetween(16, 20)]
                ];
            
            case 'congestion':
                return [
                    'has_exceptions' => true
                ];
            
            default:
                return [];
        }
    }

    /**
     * Generate adjustments
     */
    private function generateAdjustments(): array
    {
        $adjustmentType = $this->faker->randomElement(['hours', 'days', 'multiplier']);
        
        switch ($adjustmentType) {
            case 'hours':
                return ['hours' => $this->faker->numberBetween(-12, 48)];
            
            case 'days':
                return ['days' => $this->faker->numberBetween(-1, 3)];
            
            case 'multiplier':
                return ['multiplier' => $this->faker->randomFloat(2, 0.5, 2.0)];
            
            default:
                return ['hours' => 24];
        }
    }

    /**
     * Create an active rule
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Create an inactive rule
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Create a service modifier rule
     */
    public function serviceModifier(string $serviceType = 'express'): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'service_modifier',
            'conditions' => ['service_type' => $serviceType],
            'adjustments' => ['multiplier' => 0.7],
        ]);
    }

    /**
     * Create a holiday adjustment rule
     */
    public function holidayAdjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'holiday_adjustment',
            'conditions' => ['is_holiday_delivery' => true],
            'adjustments' => ['days' => 1],
        ]);
    }
}