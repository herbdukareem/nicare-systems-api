<?php

namespace Database\Factories;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Referral;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        $referringFacility = Facility::first() ?? Facility::factory()->create();
        $receivingFacility = Facility::where('id', '!=', $referringFacility->id)->first()
            ?? Facility::factory()->create();
        $enrollee = Enrollee::first() ?? Enrollee::factory()->create([
            'facility_id' => $referringFacility->id,
            'lga_id' => $referringFacility->lga_id,
            'ward_id' => $referringFacility->ward_id,
        ]);

        return [
            'enrollee_id' => $enrollee->id,
            'referring_facility_id' => $referringFacility->id,
            'receiving_facility_id' => $receivingFacility->id,
            'referral_code' => 'REF-' . fake()->unique()->numerify('######'),
            'utn' => null,
            'utn_validated' => false,
            'status' => Referral::STATUS_PENDING,
            'presenting_complains' => fake()->paragraph(),
            'reasons_for_referral' => fake()->paragraph(),
            'treatments_given' => fake()->paragraph(),
            'investigations_done' => fake()->paragraph(),
            'examination_findings' => fake()->paragraph(),
            'preliminary_diagnosis' => fake()->sentence(),
            'medical_history' => fake()->optional()->paragraph(),
            'medication_history' => fake()->optional()->paragraph(),
            'severity_level' => fake()->randomElement(['Routine', 'Urgent/Expidited', 'Emergency']),
            'referring_person_name' => fake()->name(),
            'referring_person_specialisation' => 'General Practice',
            'referring_person_cadre' => 'Doctor',
            'contact_person_name' => fake()->optional()->name(),
            'contact_person_phone' => fake()->optional()->phoneNumber(),
            'contact_person_email' => fake()->optional()->safeEmail(),
            'request_date' => now(),
            'approval_date' => null,
            'valid_until' => now()->addDays(30),
            'claim_submitted' => false,
            'claim_submitted_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => Referral::STATUS_APPROVED,
            'utn' => 'UTN-' . now()->format('Ymd') . '-' . fake()->unique()->numerify('###'),
            'approval_date' => now(),
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function utnValidated(): static
    {
        return $this->state(fn () => [
            'utn_validated' => true,
            'utn_validated_at' => now(),
        ]);
    }

    public function readyForAdmission(): static
    {
        return $this->approved()->utnValidated();
    }
}
