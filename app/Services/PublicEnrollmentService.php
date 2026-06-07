<?php

namespace App\Services;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PublicEnrollmentService
{
    public function __construct(private PremiumCoverageService $premiumCoverageService)
    {
    }

    public function submitApplication(array $data): array
    {
        $plan = PremiumPlan::with(['programme', 'benefitPackage'])->findOrFail($data['premium_plan_id']);

        if (!$plan->isSelfEnrollmentEnabled()) {
            throw new RuntimeException('The selected premium plan is not available for self-enrollment.');
        }

        $facility = Facility::findOrFail($data['facility_id']);

        if ((int) $facility->lga_id !== (int) $data['lga_id']) {
            throw new RuntimeException('The selected facility does not belong to the selected LGA.');
        }

        if ((int) $facility->ward_id !== (int) $data['ward_id']) {
            throw new RuntimeException('The selected facility does not belong to the selected ward.');
        }

        return DB::transaction(function () use ($data, $plan) {
            $purchase = null;

            if ($plan->requiresPayment()) {
                $purchase = $this->premiumCoverageService->createPurchase([
                    'premium_plan_id' => $plan->id,
                    'payer_type' => 'individual',
                    'payer_name' => trim($data['first_name'] . ' ' . $data['last_name']),
                    'payer_phone' => $data['phone'] ?? null,
                    'payer_email' => $data['email'] ?? null,
                    'payer_details' => [
                        'channel' => 'self_service_enrollment',
                        'facility_id' => $data['facility_id'],
                        'lga_id' => $data['lga_id'],
                        'ward_id' => $data['ward_id'],
                    ],
                    'payment_method' => 'online_payment',
                    'payment_status' => 'pending',
                    'payment_reference' => $data['payment_reference'] ?: $this->generatedPaymentReference(),
                    'quantity' => 1,
                    'amount' => $plan->amount,
                    'sold_by' => null,
                ]);
            }

            $enrollee = Enrollee::create([
                'enrollee_id' => $this->generatedEnrolleeId(),
                'nin' => $data['nin'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'date_of_birth' => $data['date_of_birth'],
                'sex' => (int) $data['sex'],
                'marital_status' => $data['marital_status'] ?? null,
                'address' => $data['address'] ?? null,
                'facility_id' => $data['facility_id'],
                'lga_id' => $data['lga_id'],
                'ward_id' => $data['ward_id'],
                'insurance_programme_id' => $plan->insurance_programme_id,
                'premium_plan_id' => $plan->id,
                'premium_purchase_id' => $purchase?->id,
                'benefit_package_id' => $plan->benefit_package_id,
                'status' => Enrollee::STATUS_PENDING,
                'relationship_to_principal' => 1,
                'password' => Hash::make($data['password']),
                'enrollment_date' => now(),
                'enrollment_source' => 'self_service',
                'nin_verification_status' => blank($data['nin'] ?? null)
                    ? Enrollee::NIN_VERIFICATION_NOT_PROVIDED
                    : Enrollee::NIN_VERIFICATION_NOT_STARTED,
            ]);

            return [
                'enrollee' => $enrollee->load(['premiumPlan', 'premiumPurchase', 'benefitPackage', 'facility', 'lga', 'ward', 'insuranceProgramme']),
                'purchase' => $purchase?->load(['plan']),
                'requires_payment' => $plan->requiresPayment(),
                'next_steps' => $plan->requiresPayment()
                    ? [
                        'Complete payment using the generated payment reference or your preferred configured payment channel.',
                        'Your application will remain pending until payment is confirmed and an approval officer verifies your NIN.',
                        'Use your enrollee ID after approval to access the enrollee portal with the password you created.',
                    ]
                    : [
                        'Your application has been submitted for approval.',
                        'An approval officer will verify your NIN before activating your coverage.',
                        'Use your enrollee ID after approval to access the enrollee portal with the password you created.',
                    ],
            ];
        });
    }

    private function generatedEnrolleeId(): string
    {
        do {
            $value = 'NGSCHA' . now()->format('ymdHis') . random_int(100, 999);
        } while (Enrollee::where('enrollee_id', $value)->exists());

        return $value;
    }

    private function generatedPaymentReference(): string
    {
        do {
            $reference = 'SELF-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        } while (PremiumPurchase::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
