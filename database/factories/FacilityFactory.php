<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    public function definition(): array
    {
        // Create LGA and Ward if they don't exist
        $lga = Lga::first() ?? Lga::create([
            'name' => fake()->city(),
            'code' => 'LGA' . fake()->unique()->numerify('###'),
            'zone' => fake()->numberBetween(1, 6),
            'status' => 1,
        ]);

        $ward = Ward::where('lga_id', $lga->id)->first() ?? Ward::create([
            'name' => fake()->streetName(),
            'lga_id' => $lga->id,
            'settlement_type' => 1,
            'status' => 1,
        ]);

        return [
            'hcp_code' => 'HCP' . fake()->unique()->numerify('####'),
            'name' => fake()->company() . ' Hospital',
            'ownership' => fake()->randomElement(['Public', 'Private']),
            'level_of_care' => fake()->randomElement(['Primary', 'Secondary', 'Tertiary']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'lga_id' => $lga->id,
            'ward_id' => $ward->id,
            'capacity' => fake()->numberBetween(50, 500),
            'status' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Primary',
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Secondary',
        ]);
    }

    public function tertiary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Tertiary',
        ]);
    }
}

