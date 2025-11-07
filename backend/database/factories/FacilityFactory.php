<?php

namespace Database\Factories;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $facilityTypes = ['hub', 'depot', 'sorting_center', 'delivery_office', 'pickup_point'];
        
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???') . $this->faker->numerify('##')),
            'name' => $this->faker->company . ' ' . $this->faker->randomElement(['Hub', 'Depot', 'Center']),
            'name_th' => 'ศูนย์' . $this->faker->city,
            'facility_type' => $this->faker->randomElement($facilityTypes),
            'latitude' => $this->faker->latitude(5.0, 20.0), // Thailand latitude range
            'longitude' => $this->faker->longitude(97.0, 106.0), // Thailand longitude range
            'address' => $this->faker->address,
            'timezone' => 'Asia/Bangkok',
            'active' => $this->faker->boolean(90), // 90% active
        ];
    }

    /**
     * Indicate that the facility is a hub.
     */
    public function hub(): static
    {
        return $this->state(fn (array $attributes) => [
            'facility_type' => 'hub',
            'name' => $this->faker->city . ' Hub',
            'name_th' => 'ศูนย์คัดแยก' . $this->faker->city,
        ]);
    }

    /**
     * Indicate that the facility is a delivery office.
     */
    public function deliveryOffice(): static
    {
        return $this->state(fn (array $attributes) => [
            'facility_type' => 'delivery_office',
            'name' => $this->faker->city . ' Delivery Office',
            'name_th' => 'สำนักงานส่ง' . $this->faker->city,
        ]);
    }

    /**
     * Indicate that the facility is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}