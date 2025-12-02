<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimDiagnosis>
 */
class ClaimDiagnosisFactory extends Factory
{
    protected $model = ClaimDiagnosis::class;

    public function definition(): array
    {
        $diagnoses = [
            ['code' => 'B50.9', 'description' => 'Plasmodium falciparum malaria, unspecified'],
            ['code' => 'A01.0', 'description' => 'Typhoid fever'],
            ['code' => 'O82.0', 'description' => 'Delivery by elective cesarean section'],
            ['code' => 'O80', 'description' => 'Single spontaneous delivery'],
            ['code' => 'J18.9', 'description' => 'Pneumonia, unspecified organism'],
            ['code' => 'K35.8', 'description' => 'Acute appendicitis, other and unspecified'],
            ['code' => 'E11.9', 'description' => 'Type 2 diabetes mellitus without complications'],
            ['code' => 'I10', 'description' => 'Essential (primary) hypertension'],
        ];

        $diagnosis = fake()->randomElement($diagnoses);

        return [
            'claim_id' => Claim::factory(),
            'type' => 'primary',
            'icd_10_code' => $diagnosis['code'],
            'icd_10_description' => $diagnosis['description'],
            'illness_description' => fake()->sentence(),
            'doctor_validated' => false,
            'doctor_validated_at' => null,
            'doctor_validated_by' => null,
            'doctor_validation_comments' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => [
            'type' => 'primary',
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn () => [
            'type' => 'secondary',
        ]);
    }

    public function complication(): static
    {
        return $this->state(fn () => [
            'type' => 'complication',
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'doctor_validated' => true,
            'doctor_validated_at' => now(),
        ]);
    }
}

