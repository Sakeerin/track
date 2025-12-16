<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['sent', 'failed', 'throttled']);
        
        return [
            'subscription_id' => Subscription::factory(),
            'event_id' => Event::factory(),
            'channel' => $this->faker->randomElement(['email', 'sms', 'line', 'webhook']),
            'destination' => $this->faker->email,
            'status' => $status,
            'error_message' => $status === 'failed' ? $this->faker->sentence : null,
            'sent_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'delivered_at' => $status === 'sent' && $this->faker->boolean(80) 
                ? $this->faker->dateTimeBetween('-30 days', 'now') 
                : null,
            'metadata' => [
                'event_code' => $this->faker->randomElement(['PickedUp', 'InTransit', 'Delivered']),
                'tracking_number' => 'TH' . $this->faker->numerify('##########'),
            ],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $this->faker->sentence,
            'delivered_at' => null,
        ]);
    }

    public function throttled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'throttled',
            'error_message' => null,
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'delivered_at' => $this->faker->dateTimeBetween($attributes['sent_at'] ?? '-30 days', 'now'),
        ]);
    }
}
