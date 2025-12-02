<?php

namespace Database\Factories;

use App\Models\Bundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bundle>
 */
class BundleFactory extends Factory
{
    protected $model = Bundle::class;

    public function definition(): array
    {
        $bundleTypes = [
            ['code' => 'MAL', 'name' => 'Malaria Treatment', 'icd' => 'B50'],
            ['code' => 'TYP', 'name' => 'Typhoid Treatment', 'icd' => 'A01'],
            ['code' => 'CSEC', 'name' => 'Cesarean Section', 'icd' => 'O82'],
            ['code' => 'NVD', 'name' => 'Normal Vaginal Delivery', 'icd' => 'O80'],
            ['code' => 'APP', 'name' => 'Appendectomy', 'icd' => 'K35'],
            ['code' => 'HERN', 'name' => 'Hernia Repair', 'icd' => 'K40'],
            ['code' => 'PNEU', 'name' => 'Pneumonia Treatment', 'icd' => 'J18'],
            ['code' => 'DIAB', 'name' => 'Diabetes Management', 'icd' => 'E11'],
        ];

        $bundle = fake()->randomElement($bundleTypes);

        return [
            'bundle_code' => $bundle['code'] . fake()->unique()->numerify('###'),
            'bundle_name' => $bundle['name'],
            'description' => fake()->paragraph(),
            'case_category_id' => null,
            'icd10_code' => $bundle['icd'],
            'bundle_tariff' => fake()->randomFloat(2, 20000, 500000),
            'level_of_care' => fake()->randomElement(['Primary', 'Secondary', 'Tertiary']),
            'status' => true,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYear(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Primary',
            'bundle_tariff' => fake()->randomFloat(2, 5000, 30000),
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Secondary',
            'bundle_tariff' => fake()->randomFloat(2, 30000, 150000),
        ]);
    }

    public function tertiary(): static
    {
        return $this->state(fn (array $attributes) => [
            'level_of_care' => 'Tertiary',
            'bundle_tariff' => fake()->randomFloat(2, 150000, 500000),
        ]);
    }
}

