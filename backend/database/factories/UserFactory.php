<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'avatar' => null,
            'provider' => null,
            'provider_id' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user uses OAuth.
     */
    public function withOAuth(string $provider = 'google'): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'provider_id' => fake()->uuid(),
            'password' => null,
        ]);
    }

    /**
     * Indicate that the user has logged in recently.
     */
    public function recentlyLoggedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_login_at' => now()->subHours(rand(1, 24)),
            'last_login_ip' => fake()->ipv4(),
        ]);
    }
}
