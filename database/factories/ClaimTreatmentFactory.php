<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\ClaimTreatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimTreatment>
 */
class ClaimTreatmentFactory extends Factory
{
    protected $model = ClaimTreatment::class;

    public function definition(): array
    {
        $serviceTypes = ['professional_service', 'medication', 'diagnostic', 'consumable', 'procedure'];
        $serviceType = fake()->randomElement($serviceTypes);
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(2, 500, 50000);

        return [
            'claim_id' => Claim::factory(),
            'service_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'service_type' => $serviceType,
            'service_code' => strtoupper(fake()->lexify('???')) . fake()->numerify('###'),
            'service_description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $quantity * $unitPrice,
            'approved_benefit_fee' => null,
            'doctor_validated' => false,
            'doctor_validated_at' => null,
            'doctor_validated_by' => null,
            'pharmacist_validated' => false,
            'pharmacist_validated_at' => null,
            'pharmacist_validated_by' => null,
            'tariff_validated' => false,
            'tariff_amount' => null,
            // Claims Automation fields
            'admission_id' => null,
            'pa_code_line_item_id' => null,
            'tariff_type' => null,
            'is_principal_bundle' => false,
            'is_ffs_topup' => false,
            'ffs_reason' => null,
            'linked_diagnosis_code' => null,
            'pa_sequence' => null,
        ];
    }

    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => [
            'tariff_type' => 'bundle',
            'is_principal_bundle' => true,
            'is_ffs_topup' => false,
        ]);
    }

    public function ffs(): static
    {
        return $this->state(fn (array $attributes) => [
            'tariff_type' => 'ffs',
            'is_principal_bundle' => false,
            'is_ffs_topup' => true,
            'ffs_reason' => fake()->randomElement(['complication', 'secondary_diagnosis', 'extended_stay', 'additional_medication']),
        ]);
    }

    public function medication(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'medication',
            'service_description' => fake()->randomElement(['Paracetamol', 'Amoxicillin', 'Artemether', 'Metformin', 'Amlodipine']),
        ]);
    }

    public function procedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'professional_service',
            'service_description' => fake()->randomElement(['Surgery', 'Consultation', 'Delivery', 'Dressing', 'Injection']),
        ]);
    }

    public function diagnostic(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'diagnostic',
            'service_description' => fake()->randomElement(['Blood Test', 'X-Ray', 'Ultrasound', 'CT Scan', 'MRI']),
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'doctor_validated' => true,
            'doctor_validated_at' => now(),
            'tariff_validated' => true,
            'tariff_amount' => $attributes['total_amount'] ?? fake()->randomFloat(2, 1000, 50000),
        ]);
    }
}

