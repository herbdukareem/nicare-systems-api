<?php

namespace Database\Factories;

use App\Models\PACode;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PACode>
 */
class PACodeFactory extends Factory
{
    protected $model = PACode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $referral = Referral::factory()->readyForAdmission()->create();
        $user = User::first() ?? User::factory()->create();

        return [
            'pa_code' => 'PA-' . fake()->unique()->numerify('######'),
            'utn' => $referral->utn,
            'referral_id' => $referral->id,
            'pa_type' => fake()->randomElement(['bundle', 'ffs']),
            'admission_id' => null,

            // Enrollee and Facility Information
            'nicare_number' => $referral->nicare_number,
            'enrollee_name' => $referral->enrollee_full_name,
            'facility_name' => $referral->receiving_facility_name,
            'facility_nicare_code' => $referral->receiving_nicare_code,

            // Service Details
            'service_type' => 'General Consultation',
            'service_description' => fake()->sentence(),
            'approved_amount' => fake()->randomFloat(2, 5000, 100000),
            'conditions' => fake()->sentence(),

            // Validity and Status
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'used_at' => null,

            // Usage Tracking
            'usage_count' => 0,
            'max_usage' => 1,
            'usage_notes' => null,

            // Approval Details
            'issued_by' => $user->id,
            'issuer_comments' => fake()->sentence(),

            // Claim Tracking
            'claim_reference' => null,
            'claim_submitted_at' => null,
            'claim_status' => null,
        ];
    }

    /**
     * Indicate that the PA code is for bundle payment.
     */
    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'pa_type' => 'bundle',
        ]);
    }

    /**
     * Indicate that the PA code is for fee-for-service.
     */
    public function ffs(): static
    {
        return $this->state(fn (array $attributes) => [
            'pa_type' => 'ffs',
        ]);
    }

    /**
     * Indicate that the PA code is approved/active.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the PA code is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}

