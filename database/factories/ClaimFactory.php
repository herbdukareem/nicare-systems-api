<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\Facility;
use App\Models\Enrollee;
use App\Models\Admission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Claim>
 */
class ClaimFactory extends Factory
{
    protected $model = Claim::class;

    public function definition(): array
    {
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::first() ?? Facility::factory()->create();

        return [
            'claim_number' => Claim::generateClaimNumber(),
            'nicare_number' => $enrollee->enrollee_id,
            'enrollee_name' => $enrollee->first_name . ' ' . $enrollee->last_name,
            'gender' => $enrollee->sex == 1 ? 'Male' : 'Female',
            'plan' => 'basic',
            'marital_status' => 'single',
            'phone_main' => $enrollee->phone,
            'email_main' => $enrollee->email,
            'facility_id' => $facility->id,
            'facility_name' => $facility->name,
            'facility_nicare_code' => $facility->hcp_code,
            'pa_request_type' => 'Initial',
            'priority' => 'Routine',
            'attending_physician_name' => 'Dr. ' . fake()->name(),
            'attending_physician_license' => fake()->numerify('MD######'),
            'attending_physician_specialization' => fake()->randomElement(['General Medicine', 'Surgery', 'Obstetrics']),
            'status' => 'draft',
            'total_amount_claimed' => fake()->randomFloat(2, 10000, 500000),
            'total_amount_approved' => 0,
            'total_amount_paid' => 0,
            // Claims Automation fields
            'admission_id' => null,
            'bundle_amount' => 0,
            'ffs_amount' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'claim_approved',
            'total_amount_approved' => $attributes['total_amount_claimed'] ?? fake()->randomFloat(2, 10000, 500000),
        ]);
    }

    public function withAdmission(): static
    {
        return $this->state(function (array $attributes) {
            $admission = Admission::factory()->create([
                'facility_id' => $attributes['facility_id'],
            ]);
            return [
                'admission_id' => $admission->id,
            ];
        });
    }
}

