<?php

namespace Database\Factories;

use App\Models\Admission;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admission>
 */
class AdmissionFactory extends Factory
{
    protected $model = Admission::class;

    public function definition(): array
    {
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::first() ?? Facility::factory()->create();
        $referral = Referral::factory()->readyForAdmission()->create([
            'nicare_number' => $enrollee->enrollee_id,
            'receiving_facility_id' => $facility->id,
            'receiving_facility_name' => $facility->name,
            'receiving_nicare_code' => $facility->hcp_code,
        ]);

        return [
            'admission_code' => 'ADM' . fake()->unique()->numerify('######'),
            'referral_id' => $referral->id,
            'enrollee_id' => $enrollee->id,
            'nicare_number' => $enrollee->enrollee_id,
            'facility_id' => $facility->id,
            'bundle_id' => null,
            'principal_diagnosis_icd10' => fake()->regexify('[A-Z][0-9]{2}\.[0-9]'),
            'principal_diagnosis_description' => fake()->sentence(3),
            'admission_date' => now(),
            'discharge_date' => null,
            'status' => 'active',
            'ward_type' => fake()->randomElement(['general', 'private', 'icu', 'maternity', 'pediatric']),
            'ward_days' => null,
            'discharge_summary' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'discharge_date' => null,
        ]);
    }

    public function discharged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'discharged',
            'discharge_date' => now(),
            'discharge_summary' => fake()->paragraph(),
        ]);
    }
}

