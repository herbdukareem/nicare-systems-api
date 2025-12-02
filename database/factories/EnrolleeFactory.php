<?php

namespace Database\Factories;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollee>
 */
class EnrolleeFactory extends Factory
{
    protected $model = Enrollee::class;

    public function definition(): array
    {
        $sex = fake()->randomElement([1, 2]); // 1=male, 2=female
        $facility = Facility::first() ?? Facility::factory()->create();
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
        $user = User::first() ?? User::factory()->create();

        return [
            'enrollee_id' => 'NIC' . fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName($sex == 1 ? 'male' : 'female'),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'sex' => $sex,
            'marital_status' => fake()->randomElement([1, 2, 3, 4]), // 1=S,2=M,3=D,4=W
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-1 year'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'address' => fake()->address(),
            'facility_id' => $facility->id,
            'lga_id' => $lga->id,
            'ward_id' => $ward->id,
            'relationship_to_principal' => 1, // Principal
            'created_by' => $user->id,
            'status' => 1, // Active
            'enrollment_date' => fake()->dateTimeBetween('-5 years', 'now'),
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

    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 1,
            'first_name' => fake()->firstName('male'),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'sex' => 2,
            'first_name' => fake()->firstName('female'),
        ]);
    }
}

