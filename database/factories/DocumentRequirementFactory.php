<?php

namespace Database\Factories;

use App\Models\DocumentRequirement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentRequirement>
 */
class DocumentRequirementFactory extends Factory
{
    protected $model = DocumentRequirement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_type' => fake()->randomElement(['referral', 'pa_code']),
            'document_type' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_required' => fake()->boolean(30), // 30% chance of being required
            'allowed_file_types' => 'pdf,jpg,jpeg,png',
            'max_file_size_mb' => fake()->randomElement([2, 5, 10]),
            'display_order' => fake()->numberBetween(1, 10),
            'status' => true,
        ];
    }

    /**
     * For referral requests
     */
    public function forReferral(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_type' => 'referral',
        ]);
    }

    /**
     * For PA code requests
     */
    public function forPACode(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_type' => 'pa_code',
        ]);
    }

    /**
     * Mark as required
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Mark as optional
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    /**
     * Mark as inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}

