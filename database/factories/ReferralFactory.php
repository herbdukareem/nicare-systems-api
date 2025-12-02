<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $referringFacility = Facility::first() ?? Facility::factory()->create();
        $receivingFacility = Facility::where('id', '!=', $referringFacility->id)->first()
            ?? Facility::factory()->create();

        return [
            'referral_code' => 'REF-' . fake()->unique()->numerify('######'),
            'utn' => null,
            'utn_validated' => false,

            // Referring Provider Details
            'referring_facility_name' => $referringFacility->name,
            'referring_nicare_code' => $referringFacility->hcp_code,
            'referring_address' => $referringFacility->address ?? fake()->address(),
            'referring_phone' => $referringFacility->phone ?? fake()->phoneNumber(),
            'referring_email' => $referringFacility->email,
            'tpa_name' => fake()->company(),

            // Contact Person Details
            'contact_full_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->email(),

            // Receiving Provider Details
            'receiving_facility_name' => $receivingFacility->name,
            'receiving_nicare_code' => $receivingFacility->hcp_code,
            'receiving_address' => $receivingFacility->address ?? fake()->address(),
            'receiving_phone' => $receivingFacility->phone ?? fake()->phoneNumber(),
            'receiving_email' => $receivingFacility->email,

            // Patient/Enrollee Details
            'nicare_number' => 'NIC' . fake()->unique()->numerify('######'),
            'enrollee_full_name' => fake()->name(),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'age' => fake()->numberBetween(1, 90),
            'marital_status' => fake()->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
            'enrollee_category' => 'Principal',
            'enrollee_phone_main' => fake()->phoneNumber(),
            'enrollee_phone_encounter' => fake()->phoneNumber(),
            'enrollee_phone_relation' => fake()->phoneNumber(),
            'enrollee_email' => fake()->email(),
            'programme' => 'BHCPF',
            'organization' => fake()->company(),
            'benefit_plan' => 'Standard',
            'referral_date' => now(),

            // Clinical Justification
            'presenting_complaints' => fake()->paragraph(),
            'reasons_for_referral' => fake()->paragraph(),
            'treatments_given' => fake()->paragraph(),
            'investigations_done' => fake()->paragraph(),
            'examination_findings' => fake()->paragraph(),
            'preliminary_diagnosis' => fake()->sentence(),

            // Basic History
            'medical_history' => fake()->paragraph(),
            'medication_history' => fake()->paragraph(),

            // Severity Level
            'severity_level' => fake()->randomElement(['emergency', 'urgent', 'routine']),

            // Referring Personnel Details
            'personnel_full_name' => fake()->name(),
            'personnel_specialization' => 'General Medicine',
            'personnel_cadre' => 'Doctor',
            'personnel_phone' => fake()->phoneNumber(),
            'personnel_email' => fake()->email(),

            // Status and Processing
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the referral is approved with a UTN.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'utn' => 'UTN-' . now()->format('Ymd') . '-' . fake()->unique()->numerify('###'),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the UTN has been validated.
     */
    public function utnValidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'utn_validated' => true,
            'utn_validated_at' => now(),
        ]);
    }

    /**
     * Indicate that the referral is approved and UTN validated.
     */
    public function readyForAdmission(): static
    {
        return $this->approved()->utnValidated();
    }
}

