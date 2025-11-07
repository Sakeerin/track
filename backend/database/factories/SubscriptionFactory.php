<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channels = ['email', 'sms', 'line', 'webhook'];
        $eventCodes = ['PICKUP', 'IN_TRANSIT', 'AT_HUB', 'OUT_FOR_DELIVERY', 'DELIVERED', 'EXCEPTION'];
        
        $channel = $this->faker->randomElement($channels);
        
        return [
            'shipment_id' => Shipment::factory(),
            'channel' => $channel,
            'destination' => $this->getDestinationForChannel($channel),
            'events' => $this->faker->randomElements($eventCodes, $this->faker->numberBetween(2, 5)),
            'active' => $this->faker->boolean(85), // 85% active
            'consent_given' => $this->faker->boolean(90), // 90% with consent
            'consent_ip' => $this->faker->ipv4,
            'consent_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'unsubscribe_token' => $this->faker->sha256,
        ];
    }

    /**
     * Get appropriate destination for channel
     */
    private function getDestinationForChannel(string $channel): string
    {
        return match ($channel) {
            'email' => $this->faker->email,
            'sms' => '+66' . $this->faker->numerify('#########'),
            'line' => 'line_user_' . $this->faker->bothify('????####'),
            'webhook' => $this->faker->url,
            default => $this->faker->email,
        };
    }

    /**
     * Indicate that the subscription is for email notifications.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
            'destination' => $this->faker->email,
        ]);
    }

    /**
     * Indicate that the subscription is for SMS notifications.
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'sms',
            'destination' => '+66' . $this->faker->numerify('#########'),
        ]);
    }

    /**
     * Indicate that the subscription is for LINE notifications.
     */
    public function line(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'line',
            'destination' => 'line_user_' . $this->faker->bothify('????####'),
        ]);
    }

    /**
     * Indicate that the subscription is active with consent.
     */
    public function activeWithConsent(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
            'consent_given' => true,
            'consent_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the subscription is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}