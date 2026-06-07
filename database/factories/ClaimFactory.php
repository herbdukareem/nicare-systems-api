<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\Enrollee;
use App\Models\Facility;
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
        $facility = Facility::first() ?? Facility::factory()->create();
        $enrollee = Enrollee::where('facility_id', $facility->id)->first()
            ?? Enrollee::factory()->create(['facility_id' => $facility->id]);

        return [
            'enrollee_id' => $enrollee->id,
            'facility_id' => $facility->id,
            'claim_number' => Claim::generateClaimNumber(),
            'status' => 'DRAFT',
            'service_date' => now(),
            'claim_date' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'DRAFT',
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => 'SUBMITTED',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'APPROVED',
            'approved_at' => now(),
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
                'enrollee_id' => $admission->enrollee_id,
                'referral_id' => $admission->referral_id,
                'service_date' => $admission->admission_date ?? now(),
            ];
        });
    }
}
