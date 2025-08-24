<?php

namespace Database\Factories;

use App\Models\Premium;
use App\Models\User;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;

class PremiumFactory extends Factory
{
    protected $model = Premium::class;

    public function definition(): array
    {
        $pinData = Premium::generatePin();
        $dateGenerated = $this->faker->dateTimeBetween('-6 months', 'now');
        $dateExpired = (clone $dateGenerated)->modify('+1 year');

        return [
            'pin' => $pinData['pin'],
            'pin_raw' => $pinData['pin_raw'],
            'serial_no' => Premium::generateSerialNumber(),
            'pin_type' => $this->faker->randomElement(['individual', 'family', 'group']),
            'pin_category' => $this->faker->randomElement(['formal', 'informal', 'vulnerable', 'retiree']),
            'benefit_type' => $this->faker->randomElement(['basic', 'standard', 'premium']),
            'amount' => $this->faker->randomElement([5000, 8000, 15000, 25000]),
            'date_generated' => $dateGenerated,
            'date_expired' => $dateExpired,
            'status' => $this->faker->randomElement(['available', 'used', 'expired']),
            'payment_id' => $this->faker->optional()->uuid(),
            'request_id' => $this->faker->uuid(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'date_used' => null,
            'used_by' => null,
            'agent_reg_number' => null,
            'lga_id' => null,
            'ward_id' => null,
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'date_used' => $this->faker->dateTimeBetween($attributes['date_generated'], 'now'),
            'used_by' => User::factory(),
            'agent_reg_number' => 'AG' . $this->faker->unique()->numerify('######'),
            'lga_id' => Lga::factory(),
            'ward_id' => Ward::factory(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'date_expired' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}